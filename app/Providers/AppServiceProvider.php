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
            URL::forceRootUrl(config('app.url'));
        }

        Gate::define('view-student-page', fn (User $user) => $user->isStudent() || $user->canViewAllStudents());
        Gate::define('view-dashboard', fn (User $user) => $user->canViewDashboard());
        Gate::define('view-all-students', fn (User $user) => $user->canViewAllStudents());
        Gate::define('view-faculty-detail', fn (User $user) => $user->canViewFacultyDetail());
        Gate::define('run-process', fn (User $user) => $user->isService());
        Gate::define('manage-users', fn (User $user) => $user->canManageUsers());
        Gate::define('manage-settings', fn (User $user) => $user->canViewSettings());
        Gate::define('manage-settings-records', fn (User $user) => $user->canManageSettingsRecords());
    }
}
