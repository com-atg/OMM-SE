<?php

use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\mock;

// Records with two matched scholars and one skipped (missing student field).
function sourceRecords(): array
{
    return [
        ['record_id' => '1', 'student' => '100', 'semester' => '1', 'eval_category' => 'A', 'teaching_score' => '90.0'],
        ['record_id' => '2', 'student' => '100', 'semester' => '2', 'eval_category' => 'B', 'clinical_performance_score' => '85.0'],
        ['record_id' => '3', 'student' => '200', 'semester' => '1', 'eval_category' => 'A', 'teaching_score' => '75.0'],
        // Missing required field — skipped during grouping.
        ['record_id' => '4', 'student' => '', 'semester' => '1', 'eval_category' => 'A'],
    ];
}

function scholarMap(): array
{
    return [
        '100' => ['record_id' => '10', 'datatelid' => '100'],
        '200' => ['record_id' => '20', 'datatelid' => '200'],
    ];
}

beforeEach(function () {
    Cache::flush();
    $_ENV['REDCAP_TOKEN_PID_1846'] = 'PID_TOKEN_XYZ';
    putenv('REDCAP_TOKEN_PID_1846=PID_TOKEN_XYZ');
});

afterEach(function () {
    unset($_ENV['REDCAP_TOKEN_PID_1846']);
    putenv('REDCAP_TOKEN_PID_1846');
});

it('processes records and updates the destination project', function () {
    config(['redcap.source_token' => 'SOURCE_TOKEN']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->with('SOURCE_TOKEN')->andReturn(sourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('scholarMapByDatatelId')->once()->andReturn(scholarMap());
    $destination->shouldReceive('updateScholarRecord')->times(3)->andReturn('1');

    $this->artisan('omm:process-source')
        ->assertSuccessful()
        ->expectsOutputToContain('Processing complete.');
});

it('runs dry-run without calling updateScholarRecord', function () {
    config(['redcap.source_token' => 'SOURCE_TOKEN']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->andReturn(sourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('scholarMapByDatatelId')->once()->andReturn(scholarMap());
    $destination->shouldNotReceive('updateScholarRecord');

    $this->artisan('omm:process-source --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run complete. No changes were written.');
});

it('fails with an error when source token is not configured', function () {
    config(['redcap.source_token' => '']);

    $this->artisan('omm:process-source')
        ->assertFailed()
        ->expectsOutputToContain('REDCAP_SOURCE_TOKEN is not configured');
});

it('fails when the --pid token is not configured', function () {
    // Ensure PID 9999 has no token.
    unset($_ENV['REDCAP_TOKEN_PID_9999']);
    putenv('REDCAP_TOKEN_PID_9999');

    $this->artisan('omm:process-source --pid=9999')
        ->assertFailed()
        ->expectsOutputToContain('No token configured for PID 9999');
});

it('processes using a specific PID token', function () {
    // REDCAP_TOKEN_PID_1846 is already configured in .env (dotenv immutable repository
    // takes precedence over runtime $_ENV changes, so we verify behaviour not token value).
    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->andReturn(sourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('scholarMapByDatatelId')->once()->andReturn(scholarMap());
    $destination->shouldReceive('updateScholarRecord')->times(3)->andReturn('1');

    $this->artisan('omm:process-source --pid=1846')
        ->assertSuccessful()
        ->expectsOutputToContain('Processing complete.');
});

it('returns success with a warning when source returns no records', function () {
    config(['redcap.source_token' => 'SOURCE_TOKEN']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->andReturn([]);

    // Early exit before scholarMapByDatatelId is reached.
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldNotReceive('scholarMapByDatatelId');

    $this->artisan('omm:process-source')
        ->assertSuccessful()
        ->expectsOutputToContain('No source records found');
});

it('invalidates dashboard and scholar caches after a successful run', function () {
    config(['redcap.source_token' => 'SOURCE_TOKEN']);

    Cache::put('dashboard:stats', ['cached' => true], 600);
    Cache::put('destination:all_scholars', ['cached' => true], 600);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->andReturn(sourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('scholarMapByDatatelId')->once()->andReturn(scholarMap());
    $destination->shouldReceive('updateScholarRecord')->andReturn('1');

    $this->artisan('omm:process-source')->assertSuccessful();

    expect(Cache::has('dashboard:stats'))->toBeFalse()
        ->and(Cache::has('destination:all_scholars'))->toBeFalse();
});

it('does not invalidate caches on dry-run', function () {
    config(['redcap.source_token' => 'SOURCE_TOKEN']);

    Cache::put('dashboard:stats', ['cached' => true], 600);
    Cache::put('destination:all_scholars', ['cached' => true], 600);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->andReturn(sourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('scholarMapByDatatelId')->once()->andReturn(scholarMap());
    $destination->shouldNotReceive('updateScholarRecord');

    $this->artisan('omm:process-source --dry-run')->assertSuccessful();

    expect(Cache::has('dashboard:stats'))->toBeTrue()
        ->and(Cache::has('destination:all_scholars'))->toBeTrue();
});
