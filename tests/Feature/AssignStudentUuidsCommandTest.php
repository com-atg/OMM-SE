<?php

use App\Services\RedcapDestinationService;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\mock;

beforeEach(function () {
    Cache::flush();
});

it('skips records that already have a UUID by default', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->once()->andReturn([
        ['record_id' => '1', 'uuid' => 'existing-uuid-aaa'],
        ['record_id' => '2', 'uuid' => ''],
    ]);
    $destination->shouldReceive('updateStudentRecord')->once()->with(Mockery::on(
        fn ($data) => $data['record_id'] === '2' && isset($data['uuid']) && $data['uuid'] !== ''
    ))->andReturn('1');

    $this->artisan('students:assign-uuids')
        ->assertSuccessful();
});

it('overwrites existing UUIDs when --force is passed', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->once()->andReturn([
        ['record_id' => '1', 'uuid' => 'existing-uuid-aaa'],
        ['record_id' => '2', 'uuid' => 'existing-uuid-bbb'],
    ]);
    $destination->shouldReceive('updateStudentRecord')->twice()->andReturn('1');

    $this->artisan('students:assign-uuids --force')
        ->assertSuccessful();
});

it('does not write anything in dry-run mode', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->once()->andReturn([
        ['record_id' => '1', 'uuid' => ''],
        ['record_id' => '2', 'uuid' => ''],
    ]);
    $destination->shouldNotReceive('updateStudentRecord');

    $this->artisan('students:assign-uuids --dry-run')
        ->assertSuccessful();
});

it('returns success and warns when the roster is empty', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->once()->andReturn([]);

    $this->artisan('students:assign-uuids')
        ->expectsOutputToContain('No student records')
        ->assertSuccessful();
});

it('returns failure exit code when any record update throws', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->once()->andReturn([
        ['record_id' => '1', 'uuid' => ''],
        ['record_id' => '2', 'uuid' => ''],
    ]);
    $destination->shouldReceive('updateStudentRecord')
        ->twice()
        ->andThrow(new RuntimeException('REDCap write failed'));

    $this->artisan('students:assign-uuids')
        ->assertFailed();
});

it('invalidates caches after assigning UUIDs', function () {
    Cache::put('destination:all_students', ['cached'], 600);
    Cache::put('dashboard:stats', ['cached'], 600);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->once()->andReturn([
        ['record_id' => '1', 'uuid' => ''],
    ]);
    $destination->shouldReceive('updateStudentRecord')->once()->andReturn('1');

    $this->artisan('students:assign-uuids')->assertSuccessful();

    expect(Cache::has('destination:all_students'))->toBeFalse()
        ->and(Cache::has('dashboard:stats'))->toBeFalse();
});

it('skips records missing a record_id', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->once()->andReturn([
        ['uuid' => ''],
        ['record_id' => '', 'uuid' => ''],
        ['record_id' => '3', 'uuid' => ''],
    ]);
    $destination->shouldReceive('updateStudentRecord')->once()->andReturn('1');

    $this->artisan('students:assign-uuids')->assertSuccessful();
});
