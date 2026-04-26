<?php

use App\Models\ProjectMapping;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\mock;

// Records with two matched students and one skipped (missing student field).
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

function studentMap(): array
{
    return [
        '100' => ['record_id' => '10', 'datatelid' => '100'],
        '200' => ['record_id' => '20', 'datatelid' => '200'],
    ];
}

beforeEach(function () {
    Cache::flush();
});

function defaultMapping(): ProjectMapping
{
    return ProjectMapping::factory()->create([
        'redcap_pid' => 1846,
        'redcap_token' => 'PID_TOKEN_XYZ',
    ]);
}

it('processes records and updates the destination project', function () {
    defaultMapping();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->with('PID_TOKEN_XYZ')->andReturn(sourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('studentMapByDatatelId')->once()->andReturn(studentMap());
    $destination->shouldReceive('updateStudentRecord')->times(3)->andReturn('1');

    $this->artisan('omm:process-source')
        ->assertSuccessful()
        ->expectsOutputToContain('Processing complete.');
});

it('runs dry-run without calling updateStudentRecord', function () {
    defaultMapping();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->andReturn(sourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('studentMapByDatatelId')->once()->andReturn(studentMap());
    $destination->shouldNotReceive('updateStudentRecord');

    $this->artisan('omm:process-source --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run complete. No changes were written.');
});

it('fails with an error when no project mapping is configured', function () {
    $this->artisan('omm:process-source')
        ->assertFailed()
        ->expectsOutputToContain('No project mapping configured');
});

it('fails when the --pid mapping is not configured', function () {
    $this->artisan('omm:process-source --pid=9999')
        ->assertFailed()
        ->expectsOutputToContain('No project mapping found for PID 9999');
});

it('processes using a specific PID mapping', function () {
    defaultMapping();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->with('PID_TOKEN_XYZ')->andReturn(sourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('studentMapByDatatelId')->once()->andReturn(studentMap());
    $destination->shouldReceive('updateStudentRecord')->times(3)->andReturn('1');

    $this->artisan('omm:process-source --pid=1846')
        ->assertSuccessful()
        ->expectsOutputToContain('Processing complete.');
});

it('returns success with a warning when source returns no records', function () {
    defaultMapping();

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->andReturn([]);

    // Early exit before studentMapByDatatelId is reached.
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldNotReceive('studentMapByDatatelId');

    $this->artisan('omm:process-source')
        ->assertSuccessful()
        ->expectsOutputToContain('No source records found');
});

it('invalidates dashboard and student caches after a successful run', function () {
    defaultMapping();

    Cache::put('dashboard:stats', ['cached' => true], 600);
    Cache::put('destination:all_students', ['cached' => true], 600);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->andReturn(sourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('studentMapByDatatelId')->once()->andReturn(studentMap());
    $destination->shouldReceive('updateStudentRecord')->andReturn('1');

    $this->artisan('omm:process-source')->assertSuccessful();

    expect(Cache::has('dashboard:stats'))->toBeFalse()
        ->and(Cache::has('destination:all_students'))->toBeFalse();
});

it('does not invalidate caches on dry-run', function () {
    defaultMapping();

    Cache::put('dashboard:stats', ['cached' => true], 600);
    Cache::put('destination:all_students', ['cached' => true], 600);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('fetchAllRecords')->once()->andReturn(sourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('studentMapByDatatelId')->once()->andReturn(studentMap());
    $destination->shouldNotReceive('updateStudentRecord');

    $this->artisan('omm:process-source --dry-run')->assertSuccessful();

    expect(Cache::has('dashboard:stats'))->toBeTrue()
        ->and(Cache::has('destination:all_students'))->toBeTrue();
});
