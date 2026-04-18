<?php

use App\Http\Controllers\NotifierController;
use App\Http\Middleware\VerifyWebhookToken;
use App\Mail\EvaluationNotification;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
