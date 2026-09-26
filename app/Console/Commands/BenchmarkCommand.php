<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Replays the key pages of every role through the full HTTP stack and reports
 * response time and database query count against a budget. Run it after
 * `zimrent:seed-demo` to see how the app behaves with a production-sized data set.
 */
class BenchmarkCommand extends Command
{
    protected $signature = 'zimrent:benchmark
        {--runs=5 : Requests per page (the median is reported)}
        {--budget-ms=500 : Flag pages slower than this}
        {--budget-queries=40 : Flag pages running more queries than this}
        {--profile= : Profile a single page (e.g. /owner/properties) and list its slowest and most repeated queries}
        {--as= : Email of the user to profile the page as (default: guest)}';

    protected $description = 'Measure response time and query count of the main pages for guests, tenants, owners and admins';

    private int $queries = 0;

    /** @var array<int, array{0: float, 1: string}> */
    private array $log = [];

    public function handle(Kernel $kernel): int
    {
        DB::listen(function ($query) {
            $this->queries++;
            $this->log[] = [$query->time, $query->sql];
        });

        if ($this->option('profile')) {
            return $this->profile($kernel, (string) $this->option('profile'), $this->option('as'));
        }

        $tenant = $this->busiestTenant();
        $owner = $this->busiestOwner();
        $admin = User::where('email', 'admin@system.local')->first();
        $listing = Property::where('status', 'available')->orderByDesc('id')->value('id');

        if (! $tenant || ! $owner || ! $admin || ! $listing) {
            $this->error('Seed the database first (php artisan db:seed, then php artisan zimrent:seed-demo).');

            return self::FAILURE;
        }

        $this->line(sprintf('Data set: %s listings, %s users. Tenant: %s. Owner: %s (%d listings).',
            number_format(Property::count()), number_format(User::count()), $tenant->email, $owner->email,
            Property::where('owner_id', $owner->id)->count()));

        $scenarios = [
            ['Guest', null, '/'],
            ['Guest', null, '/?city=Harare&bedrooms=3&max_price=1500'],
            ['Guest', null, '/?q=3+bed+flat+in+borrowdale'],
            ['Guest', null, '/?view=map'],
            ['Guest', null, '/?sort=price_asc&page=20'],
            ['Guest', null, "/properties/{$listing}"],
            ['Guest', null, '/search/suggestions?q=bor'],
            ['Guest', null, '/help/tenant'],
            ['Tenant', $tenant, '/'],
            ['Tenant', $tenant, '/tenant'],
            ['Tenant', $tenant, '/tenant/favourites'],
            ['Tenant', $tenant, '/tenant/enquiries'],
            ['Tenant', $tenant, '/tenant/applications'],
            ['Tenant', $tenant, '/tenant/saved-searches'],
            ['Tenant', $tenant, "/properties/{$listing}"],
            ['Owner', $owner, '/owner'],
            ['Owner', $owner, '/owner/properties'],
            ['Owner', $owner, '/owner/enquiries'],
            ['Owner', $owner, '/owner/interests'],
            ['Owner', $owner, '/owner/applications'],
            ['Owner', $owner, '/owner/viewings'],
            ['Owner', $owner, '/owner/analytics'],
            ['Admin', $admin, '/admin/dashboard'],
            ['Admin', $admin, '/admin/marketplace/analytics'],
            ['Admin', $admin, '/admin/marketplace/reports'],
            ['Admin', $admin, '/auth/users'],
            ['Admin', $admin, '/admin/help'],
        ];

        $runs = max(1, (int) $this->option('runs'));
        $budgetMs = (int) $this->option('budget-ms');
        $budgetQueries = (int) $this->option('budget-queries');
        $rows = [];
        $failures = 0;

        foreach ($scenarios as [$role, $user, $url]) {
            $timings = [];
            $queryCounts = [];
            $status = null;
            // One warm-up request so first-hit costs (config, route and view caches) are not counted.
            for ($run = 0; $run <= $runs; $run++) {
                [$ms, $count, $status] = $this->request($kernel, $user, $url);
                if ($run > 0) {
                    $timings[] = $ms;
                    $queryCounts[] = $count;
                }
            }
            sort($timings);
            $median = $timings[intdiv(count($timings), 2)];
            $queries = max($queryCounts);
            $ok = $status === 200 && $median <= $budgetMs && $queries <= $budgetQueries;
            $failures += $ok ? 0 : 1;
            $rows[] = [$role, $url, $status, sprintf('%.0f', $median), sprintf('%.0f', max($timings)), $queries, $ok ? 'ok' : 'SLOW'];
        }

        $this->table(['Role', 'Page', 'Status', 'Median ms', 'Max ms', 'Queries', 'Result'], $rows);
        $this->line("Budget: {$budgetMs} ms and {$budgetQueries} queries per page, {$runs} runs each.");

        if ($failures > 0) {
            $this->warn("{$failures} page(s) over budget or not returning 200.");

            return self::FAILURE;
        }

        $this->info('All pages within budget.');

        return self::SUCCESS;
    }

    private function profile(Kernel $kernel, string $url, ?string $email): int
    {
        $user = $email ? User::where('email', $email)->first() : null;
        if ($email && ! $user) {
            $this->error("No user with email {$email}.");

            return self::FAILURE;
        }

        $this->request($kernel, $user, $url);
        [$ms, $count, $status] = $this->request($kernel, $user, $url);

        $this->line(sprintf('%s as %s: status %d, %.0f ms, %d queries, %.0f ms in SQL.',
            $url, $user?->email ?? 'guest', $status, $ms, $count, array_sum(array_column($this->log, 0))));

        $slowest = $this->log;
        usort($slowest, fn ($a, $b) => $b[0] <=> $a[0]);
        $this->table(['ms', 'Slowest queries'], array_map(fn ($q) => [sprintf('%.1f', $q[0]), mb_strimwidth($q[1], 0, 180, '…')], array_slice($slowest, 0, 8)));

        $repeated = [];
        foreach ($this->log as [, $sql]) {
            $shape = preg_replace('/\d+/', '?', $sql);
            $repeated[$shape] = ($repeated[$shape] ?? 0) + 1;
        }
        arsort($repeated);
        $this->table(['Times', 'Most repeated query shapes'], collect($repeated)->take(5)->map(fn ($n, $sql) => [$n, mb_strimwidth($sql, 0, 180, '…')])->values()->all());

        return self::SUCCESS;
    }

    /**
     * @return array{0: float, 1: int, 2: int}
     */
    private function request(Kernel $kernel, ?User $user, string $url): array
    {
        $guard = Auth::guard('web');
        $user ? $guard->setUser($user) : $guard->forgetUser();

        $this->queries = 0;
        $this->log = [];
        $request = Request::create($url, 'GET');
        $started = hrtime(true);
        $response = $kernel->handle($request);
        $ms = (hrtime(true) - $started) / 1e6;
        $kernel->terminate($request, $response);

        return [$ms, $this->queries, $response->getStatusCode()];
    }

    private function busiestTenant(): ?User
    {
        $id = DB::table('property_favourites')
            ->join('auth_user_roles', 'auth_user_roles.user_id', '=', 'property_favourites.user_id')
            ->join('auth_roles', 'auth_roles.id', '=', 'auth_user_roles.role_id')
            ->where('auth_roles.name', 'Tenant')
            ->select('property_favourites.user_id', DB::raw('count(*) as total'))
            ->groupBy('property_favourites.user_id')
            ->orderByDesc('total')
            ->value('property_favourites.user_id');

        return $id ? User::find($id) : User::where('email', 'tenant@dzimba.local')->first();
    }

    private function busiestOwner(): ?User
    {
        $id = Property::select('owner_id', DB::raw('count(*) as total'))
            ->groupBy('owner_id')
            ->orderByDesc('total')
            ->value('owner_id');

        return $id ? User::find($id) : null;
    }
}
