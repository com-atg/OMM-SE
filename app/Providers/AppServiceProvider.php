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
            URL::forceRootUrl($this->productionRootUrl());
        }

        Gate::define('view-student-page', fn (User $user) => $user->isStudent() || $user->canViewAllStudents());
        Gate::define('view-dashboard', fn (User $user) => $user->canViewDashboard());
        Gate::define('view-all-students', fn (User $user) => $user->canViewAllStudents());
        Gate::define('view-faculty-detail', fn (User $user) => $user->canViewFacultyDetail());
        Gate::define('run-process', fn (User $user) => $user->isService());
        Gate::define('manage-users', fn (User $user) => $user->canManageUsers());
        Gate::define('manage-settings', fn (User $user) => $user->canViewSettings());
        Gate::define('manage-settings-records', fn (User $user) => $user->canManageSettingsRecords());
        Gate::define('edit-email-template', fn (User $user) => $user->isService());
        Gate::define('view-docs', fn (User $user) => $user->isService());
    }

    private function productionRootUrl(): string
    {
        $configuredUrl = rtrim((string) config('app.url'), '/');

        if (! $this->app->bound('request')) {
            return $configuredUrl;
        }

        $request = $this->app['request'];
        $requestBasePath = trim($request->getBaseUrl(), '/');
        $configuredPath = trim((string) parse_url($configuredUrl, PHP_URL_PATH), '/');

        if ($this->app->runningInConsole() && $requestBasePath === '') {
            return $configuredUrl;
        }

        if ($requestBasePath !== '' && $requestBasePath !== $configuredPath) {
            return rtrim($request->root(), '/');
        }

        return $configuredUrl;
    }
}
