<?php

use App\Mail\EvaluationNotification;
use App\Models\ProjectMapping;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\mock;

beforeEach(function () {
    // Treat the test environment as local so the webhook secret bypass applies.
    // Tests that exercise non-local secret behavior override this explicitly.
    $this->app->detectEnvironment(fn (): string => 'local');

    ProjectMapping::factory()->create([
        'redcap_pid' => 1846,
        'redcap_token' => 'PID_TOKEN_1846',
        'is_active' => true,
    ]);
});

afterEach(function () {
    $this->app->detectEnvironment(fn (): string => 'testing');
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function sourceEvalRecord(string $category = 'A', array $overrides = []): array
{
    return array_merge([
        'record_id' => '1',
        'date_lab' => '04-16-2026',
        'semester' => '1',
        'student' => '1',
        'eval_category' => $category,
        'teaching_score' => '90.00',
        'clinical_performance_score' => '83.93',
        'research_total_score' => '80.00',
        'didactic_total_score' => '76.67',
        'comments' => '',
        'faculty' => 'Dr. Smith',
        'faculty_email' => 'faculty@example.com',
    ], $overrides);
}

function destStudentRecord(array $overrides = []): array
{
    return array_merge([
        'record_id' => '10',
        'first_name' => 'Catherine',
        'last_name' => 'Chin',
        'goes_by' => 'Cat',
        'email' => 'catherine@example.com',
        'cohort_start_term' => 'Spring',
        'cohort_start_year' => '2026',
    ], $overrides);
}

function mockServices(array $evalRecord, array $allEvals, ?array $destRecord): void
{
    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evalRecord);
    $source->shouldReceive('getStudentEvals')->andReturn($allEvals);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findStudentByDatatelId')->with('1')->andReturn($destRecord);

    if ($destRecord) {
        $destination->shouldReceive('updateStudentRecord')->andReturn('1');
    }
}

// ─── Webhook token authentication ─────────────────────────────────────────────

test('returns 403 when webhook token is invalid', function () {
    config(['redcap.webhook_secret' => 'correct-secret']);

    $this->postJson('/notify?token=wrong-token', ['record' => '1', 'project_id' => '1846'])
        ->assertForbidden();
});

test('returns 403 when webhook token is missing', function () {
    config(['redcap.webhook_secret' => 'correct-secret']);

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846'])
        ->assertForbidden();
});

test('accepts webhook when token matches secret', function () {
    Mail::fake();
    config(['redcap.webhook_secret' => 'correct-secret']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn([]);

    $this->postJson('/notify?token=correct-secret', ['record' => '1', 'project_id' => '1846'])
        ->assertSuccessful();
});

test('bypasses token check in local environment when webhook_secret is not configured', function () {
    Mail::fake();
    config(['redcap.webhook_secret' => '']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn([]);

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846'])->assertSuccessful();
});

test('rejects webhook requests in non-local environments when webhook_secret is not configured', function () {
    $this->app->detectEnvironment(fn (): string => 'production');
    config(['redcap.webhook_secret' => '']);

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846'])
        ->assertForbidden();
});

// ─── Edge cases ──────────────────────────────────────────────────────────────

test('returns 200 when record param is missing from webhook', function () {
    Mail::fake();

    $this->postJson('/notify')->assertSuccessful();

    Mail::assertNothingSent();
});

test('returns 200 when source record is not found', function () {
    Mail::fake();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn([]);

    $this->postJson('/notify', ['record' => '999', 'project_id' => '1846'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('returns 200 when eval record is missing student', function () {
    Mail::fake();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn(
        sourceEvalRecord('A', ['student' => ''])
    );

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('returns 200 when eval record has unknown semester code', function () {
    Mail::fake();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn(
        sourceEvalRecord('A', ['semester' => '9'])
    );

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('returns 200 when no matching destination student record exists', function () {
    Mail::fake();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn(sourceEvalRecord());
    $source->shouldReceive('getStudentEvals')->andReturn([sourceEvalRecord()]);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findStudentByDatatelId')->andReturn(null);

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('returns 200 when eval falls outside scholar cohort window', function () {
    Mail::fake();

    // Eval in 2030 but cohort starts Spring 2026 → slot would be > 4.
    $evalRecord = sourceEvalRecord('A', ['date_lab' => '2030-04-15', 'semester' => '1']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evalRecord);
    // getStudentEvals should NOT be called — we reject before fetching.

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findStudentByDatatelId')
        ->with('1')
        ->andReturn(destStudentRecord());
    $destination->shouldNotReceive('updateStudentRecord');

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846'])->assertSuccessful();

    Mail::assertNothingSent();
});

// ─── Happy path ───────────────────────────────────────────────────────────────

test('sends EvaluationNotification email on successful webhook', function () {
    Mail::fake();

    mockServices(sourceEvalRecord(), [sourceEvalRecord()], destStudentRecord());

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846'])->assertSuccessful();

    Mail::assertSent(EvaluationNotification::class);
});

test('uses active source project token regardless of webhook project id', function () {
    Mail::fake();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')
        ->once()
        ->with('1', 'PID_TOKEN_1846')
        ->andReturn(sourceEvalRecord());
    $source->shouldReceive('getStudentEvals')
        ->once()
        ->with('1', '1', 2026, 'PID_TOKEN_1846')
        ->andReturn([sourceEvalRecord()]);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findStudentByDatatelId')->with('1')->andReturn(destStudentRecord());
    $destination->shouldReceive('updateStudentRecord')->once()->andReturn('1');

    $this->postJson('/notify', [
        'record' => '1',
        'project_id' => '1846',
    ])->assertSuccessful();

    Mail::assertSent(EvaluationNotification::class);
});

test('sends email to student address', function () {
    Mail::fake();

    mockServices(sourceEvalRecord(), [sourceEvalRecord()], destStudentRecord());

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846']);

    Mail::assertSent(EvaluationNotification::class, function (EvaluationNotification $mail) {
        return $mail->hasTo('catherine@example.com');
    });
});

test('CCs faculty email address on notification', function () {
    Mail::fake();

    mockServices(
        sourceEvalRecord('A', ['faculty_email' => 'faculty@example.com']),
        [sourceEvalRecord()],
        destStudentRecord(),
    );

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846']);

    Mail::assertSent(EvaluationNotification::class, function (EvaluationNotification $mail) {
        return $mail->hasCc('faculty@example.com');
    });
});

test('BCCs admin address on notification', function () {
    Mail::fake();

    config(['mail.from.address' => 'admin@example.com']);

    mockServices(sourceEvalRecord(), [sourceEvalRecord()], destStudentRecord());

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846']);

    Mail::assertSent(EvaluationNotification::class, function (EvaluationNotification $mail) {
        return $mail->hasBcc('admin@example.com');
    });
});

test('does not send email when student has no email address', function () {
    Mail::fake();

    mockServices(sourceEvalRecord(), [sourceEvalRecord()], destStudentRecord(['email' => '']));

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('does not send email when student email address is malformed', function () {
    Mail::fake();

    mockServices(sourceEvalRecord(), [sourceEvalRecord()], destStudentRecord(['email' => 'not-an-email']));

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('does not CC when faculty email is malformed', function () {
    Mail::fake();

    mockServices(
        sourceEvalRecord('A', ['faculty_email' => 'bad-email']),
        [sourceEvalRecord()],
        destStudentRecord(),
    );

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846']);

    Mail::assertSent(EvaluationNotification::class, function (EvaluationNotification $mail) {
        return ! $mail->hasCc('bad-email');
    });
});

// ─── Aggregate logic ─────────────────────────────────────────────────────────

test('aggregates scores from multiple evals of the same category into the slot', function () {
    Mail::fake();

    $evals = [
        sourceEvalRecord('A', ['teaching_score' => '80.00']),
        sourceEvalRecord('A', ['teaching_score' => '100.00']),
    ];

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evals[0]);
    $source->shouldReceive('getStudentEvals')->andReturn($evals);

    $capturedPayload = null;

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findStudentByDatatelId')->andReturn(destStudentRecord());
    $destination->shouldReceive('updateStudentRecord')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn('1');

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846']);

    // Spring 2026 eval for Spring-2026 cohort = slot 1 = sem1.
    expect($capturedPayload)
        ->toHaveKey('sem1_nu_teaching', 2)
        ->toHaveKey('sem1_avg_teaching', 90.0);
});

test('skips scores outside 0–100 range in aggregation', function () {
    Mail::fake();

    $evals = [
        sourceEvalRecord('A', ['teaching_score' => '80.00']),
        sourceEvalRecord('A', ['teaching_score' => '-5.00']),
        sourceEvalRecord('A', ['teaching_score' => '150.00']),
    ];

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evals[0]);
    $source->shouldReceive('getStudentEvals')->andReturn($evals);

    $capturedPayload = null;

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findStudentByDatatelId')->andReturn(destStudentRecord());
    $destination->shouldReceive('updateStudentRecord')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn('1');

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846']);

    expect($capturedPayload)
        ->toHaveKey('sem1_nu_teaching', 1)
        ->toHaveKey('sem1_avg_teaching', 80.0);
});

test('aggregates comments count and concatenated text', function () {
    Mail::fake();

    $evals = [
        sourceEvalRecord('A', ['comments' => 'First comment.', 'faculty' => 'Dr. A']),
        sourceEvalRecord('A', ['comments' => 'Second comment.', 'faculty' => 'Dr. B']),
        sourceEvalRecord('A', ['comments' => '']),
    ];

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evals[0]);
    $source->shouldReceive('getStudentEvals')->andReturn($evals);

    $capturedPayload = null;

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findStudentByDatatelId')->andReturn(destStudentRecord());
    $destination->shouldReceive('updateStudentRecord')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn('1');

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846']);

    expect($capturedPayload)
        ->toHaveKey('sem1_nu_comments', 2)
        ->toHaveKey('sem1_comments');

    expect($capturedPayload['sem1_comments'])
        ->toContain('Dr. A;')
        ->toContain('First comment.')
        ->toContain('Dr. B;')
        ->toContain('Second comment.');
});

test('sets count to zero and omits avg when no evals exist for a category', function () {
    Mail::fake();

    $evals = [sourceEvalRecord('A', ['teaching_score' => '85.00'])];

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evals[0]);
    $source->shouldReceive('getStudentEvals')->andReturn($evals);

    $capturedPayload = null;

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findStudentByDatatelId')->andReturn(destStudentRecord());
    $destination->shouldReceive('updateStudentRecord')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn('1');

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846']);

    expect($capturedPayload)
        ->toHaveKey('sem1_nu_clinic', 0)
        ->not->toHaveKey('sem1_avg_clinic');
});

// ─── Slot routing ─────────────────────────────────────────────────────────────

test('routes Fall 2027 eval into sem3 for a Fall 2026 cohort', function () {
    Mail::fake();

    $evalRecord = sourceEvalRecord('A', [
        'semester' => '2',
        'date_lab' => '2027-10-15',
        'teaching_score' => '75.00',
    ]);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evalRecord);
    $source->shouldReceive('getStudentEvals')->andReturn([$evalRecord]);

    $capturedPayload = null;

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findStudentByDatatelId')->andReturn(
        destStudentRecord(['cohort_start_term' => 'Fall', 'cohort_start_year' => '2026']),
    );
    $destination->shouldReceive('updateStudentRecord')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn('1');

    $this->postJson('/notify', ['record' => '1', 'project_id' => '1846']);

    expect($capturedPayload)
        ->toHaveKey('sem3_nu_teaching', 1)
        ->toHaveKey('sem3_avg_teaching', 75.0)
        ->not->toHaveKey('sem1_nu_teaching');
});

// ─── Email preview route ──────────────────────────────────────────────────────

test('email preview route is only registered in the local environment', function () {
    expect(Route::has('test.email'))->toBeFalse();
    $this->get('/test/email')->assertNotFound();
});
