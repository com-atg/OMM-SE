<?php

use App\Enums\Role;
use App\Jobs\ImportScholarsJob;
use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\RedcapDestinationService;
use Illuminate\Support\Facades\Cache;

it('imports scholars from redcap and writes progress to cache', function () {
    $mapping = ProjectMapping::factory()->create([
        'graduation_year' => 2029,
    ]);

    User::factory()->create(['email' => 'existing@example.com']);

    $records = [
        ['record_id' => '1', 'first_name' => 'Alice', 'goes_by' => '', 'last_name' => 'Andrews', 'email' => 'alice@example.com'],
        ['record_id' => '2', 'first_name' => 'Bob', 'goes_by' => 'Bobby', 'last_name' => 'Brown', 'email' => 'EXISTING@example.com'],
        ['record_id' => '3', 'first_name' => 'Carol', 'goes_by' => '', 'last_name' => 'Carter', 'email' => ''],
    ];

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getStudentsByGraduationYear')->with(2029)->andReturn($records);
    app()->instance(RedcapDestinationService::class, $destination);

    $jobId = 'test-job-id';
    Cache::put(ImportScholarsJob::cacheKey($jobId), ImportScholarsJob::initialState($jobId, $mapping->id), now()->addMinutes(60));

    (new ImportScholarsJob($jobId, $mapping->id))->handle($destination);

    $state = Cache::get(ImportScholarsJob::cacheKey($jobId));

    expect($state['status'])->toBe('complete')
        ->and($state['total_fetched'])->toBe(3)
        ->and($state['processed'])->toBe(3)
        ->and(count($state['created']))->toBe(1)
        ->and(count($state['skipped']))->toBe(1)
        ->and(count($state['missing_email']))->toBe(1)
        ->and($state['created'][0]['email'])->toBe('alice@example.com');

    $alice = User::where('email', 'alice@example.com')->first();
    expect($alice)->not->toBeNull()
        ->and($alice->role)->toBe(Role::Student)
        ->and($alice->name)->toBe('Alice Andrews');
});

it('buckets records with malformed emails into failed without aborting the import', function () {
    $mapping = ProjectMapping::factory()->create(['graduation_year' => 2030]);

    $records = [
        ['record_id' => '1', 'first_name' => 'Alice', 'goes_by' => '', 'last_name' => 'Andrews', 'email' => 'alice@example.com'],
        ['record_id' => '2', 'first_name' => 'Bogus', 'goes_by' => '', 'last_name' => 'Bee', 'email' => 'not-an-email'],
        ['record_id' => '3', 'first_name' => 'Carol', 'goes_by' => '', 'last_name' => 'Carter', 'email' => 'carol@example.com'],
    ];

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getStudentsByGraduationYear')->with(2030)->andReturn($records);
    app()->instance(RedcapDestinationService::class, $destination);

    $jobId = 'invalid-email-job';
    Cache::put(ImportScholarsJob::cacheKey($jobId), ImportScholarsJob::initialState($jobId, $mapping->id), now()->addMinutes(60));

    (new ImportScholarsJob($jobId, $mapping->id))->handle($destination);

    $state = Cache::get(ImportScholarsJob::cacheKey($jobId));

    expect($state['status'])->toBe('complete')
        ->and(count($state['created']))->toBe(2)
        ->and(count($state['failed']))->toBe(1)
        ->and($state['failed'][0]['record_id'])->toBe('2')
        ->and($state['failed'][0]['reason'])->toBe('Invalid email format');
});

it('marks the cache state as failed via the failed() handler when the queue worker abandons the job', function () {
    $mapping = ProjectMapping::factory()->create();
    $jobId = 'abandoned-job';
    Cache::put(ImportScholarsJob::cacheKey($jobId), ImportScholarsJob::initialState($jobId, $mapping->id), now()->addMinutes(60));

    (new ImportScholarsJob($jobId, $mapping->id))->failed(new RuntimeException('Queue worker crashed'));

    $state = Cache::get(ImportScholarsJob::cacheKey($jobId));

    expect($state['status'])->toBe('failed')
        ->and($state['error'])->toBe('Queue worker crashed');
});

it('marks the cache state as failed when the destination service throws', function () {
    $mapping = ProjectMapping::factory()->create();

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getStudentsByGraduationYear')->andThrow(new RuntimeException('REDCap unreachable'));
    app()->instance(RedcapDestinationService::class, $destination);

    $jobId = 'failed-job';
    Cache::put(ImportScholarsJob::cacheKey($jobId), ImportScholarsJob::initialState($jobId, $mapping->id), now()->addMinutes(60));

    (new ImportScholarsJob($jobId, $mapping->id))->handle($destination);

    $state = Cache::get(ImportScholarsJob::cacheKey($jobId));

    expect($state['status'])->toBe('failed')
        ->and($state['error'])->toBe('REDCap unreachable');
});
