<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\SamlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotifierController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ScholarController;
use App\Http\Middleware\RequireSamlAuth;
use App\Http\Middleware\VerifyWebhookToken;
use App\Mail\EvaluationNotification;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// SAML SSO (Okta)
Route::get('/saml/login', [SamlController::class, 'login'])->name('saml.login');
Route::post('/saml/acs', [SamlController::class, 'acs'])->name('saml.acs');
Route::match(['get', 'post'], '/saml/logout', [SamlController::class, 'logout'])->name('saml.logout');
Route::get('/signed-out', fn () => view('auth.signed-out'))->name('saml.signed-out');
Route::get('/saml/metadata', [SamlController::class, 'metadata'])
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->name('saml.metadata');

Route::middleware(RequireSamlAuth::class)->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/scholar', ScholarController::class)->name('scholar');
    Route::get('/scholar/{token}', [ScholarController::class, 'show'])
        ->where('token', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}')
        ->name('scholar.token');

    // Bulk aggregation for a source REDCap project identified by PID.
    // Token resolved from .env as REDCAP_TOKEN_PID_{pid}.
    Route::middleware('can:run-process')->group(function () {
        Route::post('/process/run', [ProcessController::class, 'run'])->name('process.run');
        Route::get('/process/{pid}', [ProcessController::class, 'show'])
            ->whereNumber('pid')
            ->name('process');
        Route::get('/process/status/{jobId}', [ProcessController::class, 'status'])
            ->name('process.status');
    });

    // Stop impersonation — must be outside can:manage-users since the impersonated user may lack that gate.
    Route::post('/impersonate/stop', [AdminUserController::class, 'stopImpersonation'])->name('users.impersonate.stop');

    // User management (Service-only).
    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        // Static routes before {user} wildcard to avoid conflicts.
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::post('/users/import', [AdminUserController::class, 'import'])->name('users.import');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{id}/restore', [AdminUserController::class, 'restore'])->name('users.restore');
        Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');
    });

    // Settings.
    Route::middleware('can:manage-settings')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/project-mappings/{projectMapping}/process', [SettingsController::class, 'process'])->name('settings.project-mappings.process');

        Route::middleware('can:manage-settings-records')->group(function () {
            Route::post('/settings/project-mappings', [SettingsController::class, 'store'])->name('settings.project-mappings.store');
            Route::get('/settings/project-mappings/{projectMapping}/edit', [SettingsController::class, 'edit'])->name('settings.project-mappings.edit');
            Route::patch('/settings/project-mappings/{projectMapping}', [SettingsController::class, 'update'])->name('settings.project-mappings.update');
            Route::delete('/settings/project-mappings/{projectMapping}', [SettingsController::class, 'destroy'])->name('settings.project-mappings.destroy');
            Route::post('/settings/project-mappings/{id}/restore', [SettingsController::class, 'restore'])->name('settings.project-mappings.restore');
        });
    });
});

// REDCap webhook — triggered when an evaluation record is saved in PID 1846.
// Append ?token=<WEBHOOK_SECRET> to the DET URL configured in REDCap.
Route::any('/notify', NotifierController::class)
    ->middleware(VerifyWebhookToken::class)
    ->name('notify');

// Email preview for local development — shows a Teaching (A) evaluation stub.
Route::get('/test/email', function () {
    $evalRecord = array_merge(
        array_fill_keys(['small', 'large', 'knowledge', 'studevals', 'profess'], '4'),
        [
            'record_id' => '1',
            'date_lab' => '04-16-2026',
            'semester' => '1',
            'student' => '1',
            'eval_category' => 'A',
            'teaching_score' => '83.33',
            'comments' => 'Great enthusiasm during the small group session. Keep up the excellent work!',
            'faculty' => 'Dr. Smith',
            'faculty_email' => 'faculty@example.com',
        ]
    );

    $scholarRecord = [
        'record_id' => '1',
        'first_name' => 'Catherine',
        'last_name' => 'Chin',
        'goes_by' => 'Cat',
        'email' => 'catherine@example.com',
    ];

    $aggregates = [
        'semester' => 'spring',
        'by_category' => [
            'teaching' => ['nu' => 1, 'avg' => 83.33],
            'clinic' => ['nu' => 0, 'avg' => null],
            'research' => ['nu' => 0, 'avg' => null],
            'didactics' => ['nu' => 0, 'avg' => null],
        ],
        'fields' => [],
    ];

    return new EvaluationNotification(
        evalRecord: $evalRecord,
        scholarRecord: $scholarRecord,
        semester: 'spring',
        aggregates: $aggregates,
        evalCategory: 'A',
    );
})->name('test.email');
