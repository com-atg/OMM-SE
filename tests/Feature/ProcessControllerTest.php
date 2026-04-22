<?php

use App\Jobs\ProcessSourceProjectJob;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\get;
use function Pest\Laravel\mock;
use function Pest\Laravel\post;

beforeEach(function () {
    Cache::flush();
    $_ENV['REDCAP_TOKEN_PID_1846'] = 'TEST_TOKEN_ABC';
    putenv('REDCAP_TOKEN_PID_1846=TEST_TOKEN_ABC');
    asService();
});

afterEach(function () {
    unset($_ENV['REDCAP_TOKEN_PID_1846']);
    putenv('REDCAP_TOKEN_PID_1846');
});

// ─── One-click run (REDCAP_SOURCE_TOKEN) ─────────────────────────────────────

it('triggers the source project job and renders the process view', function () {
    config(['redcap.source_token' => 'SOURCE_TOKEN_TEST']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->andReturn([]);
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('scholarMapByDatatelId')->andReturn([]);

    $response = post(route('process.run'));

    $response->assertOk()->assertViewIs('process')->assertSee('Source Project');

    $jobId = $response->viewData('jobId');
    $state = Cache::get(ProcessSourceProjectJob::cacheKey($jobId));

    expect($state['pid'])->toBe('source')
        ->and($state['status'])->toBeIn(['pending', 'running', 'complete']);
});

it('returns 503 when REDCAP_SOURCE_TOKEN is not configured', function () {
    config(['redcap.source_token' => '']);

    post(route('process.run'))->assertStatus(503);
});

it('forbids admin users from triggering the run', function () {
    asAdmin();

    post(route('process.run'))->assertForbidden();
});

// ─── Route guards ────────────────────────────────────────────────────────────

it('returns 404 for a non-numeric PID', function () {
    get('/process/abc')->assertNotFound();
});

it('returns 404 when no token is configured for the PID', function () {
    get('/process/9999')->assertNotFound();
});

// ─── Kickoff ─────────────────────────────────────────────────────────────────

it('seeds cache state and schedules the job when kicked off', function () {
    // Swap the source service so the after-response job can't actually call REDCap.
    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->andReturn([]);
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('scholarMapByDatatelId')->andReturn([]);

    $response = get('/process/1846');

    $response->assertOk()->assertViewIs('process')->assertSee('Project');

    $jobId = $response->viewData('jobId');
    expect($jobId)->toBeString()->not->toBe('');

    $state = Cache::get(ProcessSourceProjectJob::cacheKey($jobId));
    expect($state)->toBeArray()
        ->and($state['pid'])->toBe('1846')
        // The after-response job runs synchronously on the sync queue during test
        // teardown, so we accept either the seeded 'pending' state or the
        // 'complete' state when it finishes immediately.
        ->and($state['status'])->toBeIn(['pending', 'running', 'complete']);
});

// ─── Status endpoint ─────────────────────────────────────────────────────────

it('returns 404 for an unknown job id', function () {
    get('/process/status/unknown-id')->assertNotFound();
});

it('returns the current state of a known job', function () {
    $jobId = 'test-job-123';
    Cache::put(ProcessSourceProjectJob::cacheKey($jobId), [
        'job_id' => $jobId,
        'status' => 'running',
        'processed_groups' => 3,
        'total_groups' => 10,
        'updated' => 2,
    ], 600);

    $response = get('/process/status/'.$jobId);

    $response->assertOk()->assertJson([
        'status' => 'running',
        'processed_groups' => 3,
        'total_groups' => 10,
        'updated' => 2,
    ]);
});

// ─── Job execution ───────────────────────────────────────────────────────────

it('aggregates source records and pushes updates for matched scholars without emailing', function () {
    Mail::fake();

    $sourceRecords = [
        [
            'record_id' => '1', 'student' => '100', 'semester' => '1',
            'eval_category' => 'A', 'teaching_score' => '90.0', 'comments' => 'Great',
            'faculty' => 'Dr. Smith',
        ],
        [
            'record_id' => '2', 'student' => '100', 'semester' => '1',
            'eval_category' => 'A', 'teaching_score' => '80.0',
        ],
        [
            'record_id' => '3', 'student' => '100', 'semester' => '2',
            'eval_category' => 'B', 'clinical_performance_score' => '85.0',
        ],
        // Unknown scholar — destination map has no match.
        [
            'record_id' => '4', 'student' => '999', 'semester' => '1',
            'eval_category' => 'A', 'teaching_score' => '70.0',
        ],
        // Missing required field — skipped during grouping.
        [
            'record_id' => '5', 'student' => '', 'semester' => '1',
            'eval_category' => 'A',
        ],
    ];

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->with('TOKEN_X')->andReturn($sourceRecords);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('scholarMapByDatatelId')->once()->andReturn([
        '100' => ['record_id' => '10', 'datatelid' => '100', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);
    $destination->shouldReceive('updateScholarRecord')->twice()->andReturn('1');

    $job = new ProcessSourceProjectJob('job-xyz', '1846', 'TOKEN_X');
    $job->handle($destination, $source);

    $state = Cache::get(ProcessSourceProjectJob::cacheKey('job-xyz'));
    expect($state['status'])->toBe('complete')
        ->and($state['total_records'])->toBe(5)
        ->and($state['total_groups'])->toBe(3)
        ->and($state['processed_groups'])->toBe(3)
        ->and($state['updated'])->toBe(2)
        ->and($state['unchanged'])->toBe(0)
        ->and($state['failed'])->toBe(0)
        ->and($state['skip_reasons']['scholar_not_found'])->toBe(1)
        ->and($state['skip_reasons']['missing_required_fields'])->toBe(1);

    Mail::assertNothingSent();
});

it('does not update destination records when aggregate values are unchanged', function () {
    $sourceRecords = [
        [
            'record_id' => '1',
            'student' => '100',
            'semester' => '1',
            'eval_category' => 'A',
            'teaching_score' => '80.0',
            'date_lab' => '2026-04-01',
            'faculty' => 'Dr. Smith',
        ],
    ];

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->with('TOKEN_X')->andReturn($sourceRecords);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('scholarMapByDatatelId')->once()->andReturn([
        '100' => [
            'record_id' => '10',
            'datatelid' => '100',
            'spring_nu_teaching' => '1',
            'spring_avg_teaching' => '80',
            'spring_dates_teaching' => 'Dr. Smith, 4/1/2026',
            'spring_nu_clinic' => '0',
            'spring_nu_research' => '0',
            'spring_nu_didactics' => '0',
            'spring_nu_comments' => '0',
        ],
    ]);
    $destination->shouldReceive('updateScholarRecord')->never();

    $job = new ProcessSourceProjectJob('job-unchanged', '1846', 'TOKEN_X');
    $job->handle($destination, $source);

    $state = Cache::get(ProcessSourceProjectJob::cacheKey('job-unchanged'));
    expect($state['status'])->toBe('complete')
        ->and($state['processed_groups'])->toBe(1)
        ->and($state['updated'])->toBe(0)
        ->and($state['unchanged'])->toBe(1)
        ->and($state['failed'])->toBe(0);
});

it('marks the job failed when the source export throws', function () {
    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->andThrow(new RuntimeException('boom'));

    $destination = mock(RedcapDestinationService::class);

    $job = new ProcessSourceProjectJob('job-err', '1846', 'TOKEN_X');
    $job->handle($destination, $source);

    $state = Cache::get(ProcessSourceProjectJob::cacheKey('job-err'));
    expect($state['status'])->toBe('failed')
        ->and($state['error'])->toBe('boom');
});
