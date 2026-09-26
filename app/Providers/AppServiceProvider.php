<?php

namespace App\Providers;

use App\Services\ConfigurationService;
use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per application so repeated rule reads are memoised.
        $this->app->singleton(ConfigurationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Any change to a rule's cache entry (Configuration Centre save,
        // seeding, a manual Cache::forget) invalidates the in-memory copy.
        Event::listen([KeyForgotten::class, KeyWritten::class], function (KeyForgotten|KeyWritten $event) {
            if (str_starts_with($event->key, ConfigurationService::CACHE_PREFIX) && $this->app->resolved(ConfigurationService::class)) {
                $this->app->make(ConfigurationService::class)->forgetMemo($event->key);
            }
        });
        Event::listen(CacheFlushed::class, function () {
            if ($this->app->resolved(ConfigurationService::class)) {
                $this->app->make(ConfigurationService::class)->forgetMemo();
            }
        });
    }
}