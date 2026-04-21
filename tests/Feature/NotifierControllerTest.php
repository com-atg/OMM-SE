<?php

use App\Mail\EvaluationNotification;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\mock;

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

function destScholarRecord(array $overrides = []): array
{
    return array_merge([
        'record_id' => '10',
        'first_name' => 'Catherine',
        'last_name' => 'Chin',
        'goes_by' => 'Cat',
        'email' => 'catherine@example.com',
    ], $overrides);
}

function mockServices(array $evalRecord, array $allEvals, ?array $destRecord): void
{
    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evalRecord);
    $source->shouldReceive('getScholarEvals')->andReturn($allEvals);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findScholarByDatatelId')->with('1')->andReturn($destRecord);

    if ($destRecord) {
        $destination->shouldReceive('updateScholarRecord')->andReturn('1');
    }
}

// ─── Webhook token authentication ─────────────────────────────────────────────

test('returns 403 when webhook token is invalid', function () {
    config(['redcap.webhook_secret' => 'correct-secret']);

    $this->postJson('/notify?token=wrong-token', ['record' => '1'])
        ->assertForbidden();
});

test('returns 403 when webhook token is missing', function () {
    config(['redcap.webhook_secret' => 'correct-secret']);

    $this->postJson('/notify', ['record' => '1'])
        ->assertForbidden();
});

test('accepts webhook when token matches secret', function () {
    Mail::fake();
    config(['redcap.webhook_secret' => 'correct-secret']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn([]);

    $this->postJson('/notify?token=correct-secret', ['record' => '1'])
        ->assertSuccessful();
});

test('bypasses token check when webhook_secret is not configured', function () {
    Mail::fake();
    config(['redcap.webhook_secret' => '']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn([]);

    $this->postJson('/notify', ['record' => '1'])->assertSuccessful();
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

    $this->postJson('/notify', ['record' => '999'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('returns 200 when eval record is missing student', function () {
    Mail::fake();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn(
        sourceEvalRecord('A', ['student' => ''])
    );

    $this->postJson('/notify', ['record' => '1'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('returns 200 when eval record has unknown semester code', function () {
    Mail::fake();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn(
        sourceEvalRecord('A', ['semester' => '9'])
    );

    $this->postJson('/notify', ['record' => '1'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('returns 200 when no matching destination scholar record exists', function () {
    Mail::fake();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn(sourceEvalRecord());
    $source->shouldReceive('getScholarEvals')->andReturn([sourceEvalRecord()]);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findScholarByDatatelId')->andReturn(null);

    $this->postJson('/notify', ['record' => '1'])->assertSuccessful();

    Mail::assertNothingSent();
});

// ─── Happy path ───────────────────────────────────────────────────────────────

test('sends EvaluationNotification email on successful webhook', function () {
    Mail::fake();

    mockServices(sourceEvalRecord(), [sourceEvalRecord()], destScholarRecord());

    $this->postJson('/notify', ['record' => '1'])->assertSuccessful();

    Mail::assertSent(EvaluationNotification::class);
});

test('sends email to scholar address', function () {
    Mail::fake();

    mockServices(sourceEvalRecord(), [sourceEvalRecord()], destScholarRecord());

    $this->postJson('/notify', ['record' => '1']);

    Mail::assertSent(EvaluationNotification::class, function (EvaluationNotification $mail) {
        return $mail->hasTo('catherine@example.com');
    });
});

test('CCs faculty email address on notification', function () {
    Mail::fake();

    mockServices(
        sourceEvalRecord('A', ['faculty_email' => 'faculty@example.com']),
        [sourceEvalRecord()],
        destScholarRecord(),
    );

    $this->postJson('/notify', ['record' => '1']);

    Mail::assertSent(EvaluationNotification::class, function (EvaluationNotification $mail) {
        return $mail->hasCc('faculty@example.com');
    });
});

test('BCCs admin address on notification', function () {
    Mail::fake();

    config(['mail.from.address' => 'admin@example.com']);

    mockServices(sourceEvalRecord(), [sourceEvalRecord()], destScholarRecord());

    $this->postJson('/notify', ['record' => '1']);

    Mail::assertSent(EvaluationNotification::class, function (EvaluationNotification $mail) {
        return $mail->hasBcc('admin@example.com');
    });
});

test('does not send email when scholar has no email address', function () {
    Mail::fake();

    mockServices(sourceEvalRecord(), [sourceEvalRecord()], destScholarRecord(['email' => '']));

    $this->postJson('/notify', ['record' => '1'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('does not send email when scholar email address is malformed', function () {
    Mail::fake();

    mockServices(sourceEvalRecord(), [sourceEvalRecord()], destScholarRecord(['email' => 'not-an-email']));

    $this->postJson('/notify', ['record' => '1'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('does not CC when faculty email is malformed', function () {
    Mail::fake();

    mockServices(
        sourceEvalRecord('A', ['faculty_email' => 'bad-email']),
        [sourceEvalRecord()],
        destScholarRecord(),
    );

    $this->postJson('/notify', ['record' => '1']);

    Mail::assertSent(EvaluationNotification::class, function (EvaluationNotification $mail) {
        return ! $mail->hasCc('bad-email');
    });
});

// ─── Aggregate logic ─────────────────────────────────────────────────────────

test('aggregates scores from multiple evals of the same category', function () {
    Mail::fake();

    $evals = [
        sourceEvalRecord('A', ['teaching_score' => '80.00']),
        sourceEvalRecord('A', ['teaching_score' => '100.00']),
    ];

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evals[0]);
    $source->shouldReceive('getScholarEvals')->andReturn($evals);

    $capturedPayload = null;

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findScholarByDatatelId')->andReturn(destScholarRecord());
    $destination->shouldReceive('updateScholarRecord')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn('1');

    $this->postJson('/notify', ['record' => '1']);

    expect($capturedPayload)
        ->toHaveKey('spring_nu_teaching', 2)
        ->toHaveKey('spring_avg_teaching', 90.0);
});

test('skips scores outside 0–100 range in aggregation', function () {
    Mail::fake();

    $evals = [
        sourceEvalRecord('A', ['teaching_score' => '80.00']),
        sourceEvalRecord('A', ['teaching_score' => '-5.00']),   // invalid — below 0
        sourceEvalRecord('A', ['teaching_score' => '150.00']),  // invalid — above 100
    ];

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evals[0]);
    $source->shouldReceive('getScholarEvals')->andReturn($evals);

    $capturedPayload = null;

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findScholarByDatatelId')->andReturn(destScholarRecord());
    $destination->shouldReceive('updateScholarRecord')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn('1');

    $this->postJson('/notify', ['record' => '1']);

    // Only the valid 80.00 score should be counted.
    expect($capturedPayload)
        ->toHaveKey('spring_nu_teaching', 1)
        ->toHaveKey('spring_avg_teaching', 80.0);
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
    $source->shouldReceive('getScholarEvals')->andReturn($evals);

    $capturedPayload = null;

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findScholarByDatatelId')->andReturn(destScholarRecord());
    $destination->shouldReceive('updateScholarRecord')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn('1');

    $this->postJson('/notify', ['record' => '1']);

    expect($capturedPayload)
        ->toHaveKey('spring_nu_comments', 2)
        ->toHaveKey('spring_comments');

    // New format: "Faculty; Date; Comment" per line.
    expect($capturedPayload['spring_comments'])
        ->toContain('Dr. A;')
        ->toContain('First comment.')
        ->toContain('Dr. B;')
        ->toContain('Second comment.');
});

test('sets count to zero and omits avg when no evals exist for a category', function () {
    Mail::fake();

    // Only a Teaching eval — Clinic/Research/Didactics should have nu=0, no avg key.
    $evals = [sourceEvalRecord('A', ['teaching_score' => '85.00'])];

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evals[0]);
    $source->shouldReceive('getScholarEvals')->andReturn($evals);

    $capturedPayload = null;

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findScholarByDatatelId')->andReturn(destScholarRecord());
    $destination->shouldReceive('updateScholarRecord')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn('1');

    $this->postJson('/notify', ['record' => '1']);

    expect($capturedPayload)
        ->toHaveKey('spring_nu_clinic', 0)
        ->not->toHaveKey('spring_avg_clinic');
});

// ─── Semester routing ─────────────────────────────────────────────────────────

test('uses fall semester fields when semester code is 2', function () {
    Mail::fake();

    $evalRecord = sourceEvalRecord('A', ['semester' => '2', 'teaching_score' => '75.00']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getRecord')->andReturn($evalRecord);
    $source->shouldReceive('getScholarEvals')->andReturn([$evalRecord]);

    $capturedPayload = null;

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('findScholarByDatatelId')->andReturn(destScholarRecord());
    $destination->shouldReceive('updateScholarRecord')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn('1');

    $this->postJson('/notify', ['record' => '1']);

    expect($capturedPayload)
        ->toHaveKey('fall_nu_teaching', 1)
        ->toHaveKey('fall_avg_teaching', 75.0)
        ->not->toHaveKey('spring_nu_teaching');
});

// ─── Email preview route ──────────────────────────────────────────────────────

test('email preview route returns a successful response', function () {
    $this->get('/test/email')->assertSuccessful();
});
