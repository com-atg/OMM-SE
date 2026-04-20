<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\SamlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotifierController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ScholarController;
use App\Http\Middleware\RequireSamlAuth;
use App\Http\Middleware\VerifyWebhookToken;
use App\Mail\EvaluationNotification;
use Illuminate\Support\Facades\Route;

// SAML SSO (Okta)
Route::get('/saml/login', [SamlController::class, 'login'])->name('saml.login');
Route::post('/saml/acs', [SamlController::class, 'acs'])->name('saml.acs');
Route::match(['get', 'post'], '/saml/logout', [SamlController::class, 'logout'])->name('saml.logout');
Route::get('/saml/metadata', [SamlController::class, 'metadata'])->name('saml.metadata');

Route::middleware(RequireSamlAuth::class)->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/scholar', ScholarController::class)->name('scholar');

    // Bulk aggregation for a source REDCap project identified by PID.
    // Token resolved from .env as REDCAP_TOKEN_PID_{pid}.
    Route::middleware('can:run-process')->group(function () {
        Route::get('/process/{pid}', [ProcessController::class, 'show'])
            ->whereNumber('pid')
            ->name('process');
        Route::get('/process/status/{jobId}', [ProcessController::class, 'status'])
            ->name('process.status');
    });

    // User management (Service-only).
    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
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
