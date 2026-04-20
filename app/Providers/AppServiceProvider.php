<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::define('view-dashboard', fn (User $user) => $user->canViewAllScholars());
        Gate::define('view-all-scholars', fn (User $user) => $user->canViewAllScholars());
        Gate::define('run-process', fn (User $user) => $user->isService());
        Gate::define('manage-users', fn (User $user) => $user->canManageUsers());
    }
}
