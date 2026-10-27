<?php

namespace App\Providers;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Policies\ModPolicy;
use App\Policies\ModVersionPolicy;
use App\Services\ModParsing\ArchiveInspector;
use App\Services\ModParsing\ModValidator;
use App\Services\ModPublisher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ArchiveInspector::class);
        $this->app->singleton(ModValidator::class);
        $this->app->singleton(ModPublisher::class);
    }

    public function boot(): void
    {
        Gate::policy(Mod::class, ModPolicy::class);
        Gate::policy(ModVersion::class, ModVersionPolicy::class);

        if (
            filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN)
            || ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://'))
        ) {
            URL::forceScheme('https');
        }
    }
}
