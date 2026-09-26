<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Manual\Manual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Keeps the in-app user manuals honest.
 *
 * Structure: every article is well formed, links to a real screen and cites
 * at least one existing feature test that proves what it describes.
 * Reachability: every linked screen actually opens for the role the guide is
 * written for. Access: the Help Center is public, the admin guide is not.
 */
class UserManualTest extends TestCase
{
    use RefreshDatabase;

    /** The demo account each guide is written for (see AuthSeeder). */
    private const GUIDE_USERS = [
        'tenant' => 'tenant@dzimba.local',
        'owner' => 'owner@dzimba.local',
        'admin' => 'admin@system.local',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_every_guide_is_well_formed(): void
    {
        foreach (Manual::GUIDES as $guide) {
            $data = Manual::raw($guide);

            $this->assertSame($guide, $data['key']);
            $this->assertNotEmpty($data['title']);
            $this->assertNotEmpty($data['tagline']);
            $this->assertNotEmpty($data['sections'], "{$guide} guide has no sections.");

            $ids = [];
            foreach ($data['sections'] as $section) {
                $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $section['id']);
                $this->assertNotEmpty($section['title']);
                $this->assertNotEmpty($section['articles'], "{$guide}/{$section['id']} has no articles.");
                $ids[] = $section['id'];

                foreach ($section['articles'] as $article) {
                    $where = "{$guide}/{$article['id']}";
                    $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $article['id'], $where);
                    $this->assertNotEmpty($article['title'], $where);
                    $this->assertTrue(
                        ! empty($article['summary']) || ! empty($article['steps']),
                        "{$where} needs a summary or steps."
                    );
                    $this->assertNotEmpty($article['verified_by'] ?? [], "{$where} does not cite a test that proves it.");
                    $ids[] = $article['id'];
                }
            }

            $this->assertSame(count($ids), count(array_unique($ids)), "{$guide} guide has duplicate section/article ids.");
        }
    }

    public function test_every_cited_test_exists(): void
    {
        foreach ($this->articles() as [$guide, $article]) {
            foreach ($article['verified_by'] as $reference) {
                [$class, $method] = array_pad(explode('::', $reference, 2), 2, null);
                $this->assertTrue(
                    $method !== null && class_exists($class) && method_exists($class, $method),
                    "{$guide}/{$article['id']} cites missing test [{$reference}]."
                );
            }
        }
    }

    public function test_every_link_points_to_a_screen_that_exists(): void
    {
        foreach ($this->articles() as [$guide, $article]) {
            foreach ($article['links'] ?? [] as $link) {
                $route = Route::getRoutes()->getByName($link['route']);
                $where = "{$guide}/{$article['id']} → {$link['route']}";

                $this->assertNotNull($route, "{$where} is not a registered route.");
                $this->assertContains('GET', $route->methods(), "{$where} is not a page (GET) route.");
                $this->assertSame([], $route->parameterNames(), "{$where} needs parameters, so it cannot be linked from the manual.");
                $this->assertNotEmpty($link['label'], $where);
            }
        }
    }

    public function test_every_linked_screen_opens_for_the_role_the_guide_is_written_for(): void
    {
        foreach (self::GUIDE_USERS as $guide => $email) {
            $user = User::where('email', $email)->firstOrFail();
            $routes = [];
            foreach (Manual::raw($guide)['sections'] as $section) {
                foreach ($section['articles'] as $article) {
                    foreach ($article['links'] ?? [] as $link) {
                        $routes[$link['route']] = true;
                    }
                }
            }

            foreach (array_keys($routes) as $name) {
                $this->actingAs($user)
                    ->get(route($name))
                    ->assertOk();
            }
        }
    }

    public function test_help_center_and_public_guides_are_open_to_everyone(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Help/Index')
                ->has('guides', count(Manual::PUBLIC_GUIDES)));

        foreach (Manual::PUBLIC_GUIDES as $guide) {
            $this->get(route('help.show', $guide))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Help/Guide')
                    ->where('guide.key', $guide)
                    ->missing('guide.sections.0.articles.0.verified_by'));
        }
    }

    public function test_admin_guide_is_not_public(): void
    {
        $this->get('/help/admin')->assertNotFound();
        $this->get('/help/anything-else')->assertNotFound();
        $this->get(route('admin.help'))->assertRedirect(route('login'));
    }

    public function test_admin_guide_opens_inside_the_admin_portal_for_admins_only(): void
    {
        foreach (['admin@system.local', 'staff@dzimba.local'] as $email) {
            $this->actingAs(User::where('email', $email)->firstOrFail())
                ->get(route('admin.help'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('Admin/Help')->where('guide.key', 'admin'));
        }

        foreach (['tenant@dzimba.local', 'owner@dzimba.local'] as $email) {
            $this->actingAs(User::where('email', $email)->firstOrFail())
                ->get(route('admin.help'))
                ->assertForbidden();
        }
    }

    /**
     * @return array<int, array{0: string, 1: array}>
     */
    private function articles(): array
    {
        $rows = [];
        foreach (Manual::GUIDES as $guide) {
            foreach (Manual::raw($guide)['sections'] as $section) {
                foreach ($section['articles'] as $article) {
                    $rows[] = [$guide, $article];
                }
            }
        }

        return $rows;
    }
}
