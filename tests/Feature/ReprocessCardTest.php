<?php

use App\Jobs\ProcessSourceProjectJob;
use App\Models\ProjectMapping;
use App\Services\RedcapDestinationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    asService();

    $destination = $this->mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12', '13']);
});

it('renders with defaults when an active source project exists', function () {
    ProjectMapping::factory()->active()->create(['redcap_pid' => 1846]);

    Livewire::test('admin.reprocess-card')
        ->assertSet('activeOnly', true)
        ->assertSet('batch', null)
        ->assertSet('jobId', null)
        ->assertSet('hasActiveSource', true)
        ->assertSet('availableBatches', ['12', '13'])
        ->assertSee('Re-process Evaluations')
        ->assertSee('Re-process now');
});

it('shows a warning when no active source project is configured', function () {
    Livewire::test('admin.reprocess-card')
        ->assertSet('hasActiveSource', false)
        ->assertSee('No active source project is configured.');
});

it('dispatches the job with default filters and seeds cache state', function () {
    Bus::fake();
    $mapping = ProjectMapping::factory()->active()->create([
        'redcap_pid' => 1846,
        'redcap_token' => '78A933EF74A3B5B1DBAB2E313942CDA6',
    ]);

    $component = Livewire::test('admin.reprocess-card')
        ->call('startReprocess')
        ->assertHasNoErrors()
        ->assertSet('jobId', fn ($id) => is_string($id) && $id !== '');

    Bus::assertDispatched(ProcessSourceProjectJob::class, function (ProcessSourceProjectJob $job) use ($component, $mapping): bool {
        return $job->jobId === $component->get('jobId')
            && $job->pid === '1846'
            && $job->projectMappingId === $mapping->id
            && $job->activeOnly === true
            && $job->batch === null;
    });

    $state = Cache::get(ProcessSourceProjectJob::cacheKey($component->get('jobId')));
    expect($state)->toBeArray()
        ->and($state['status'])->toBe('pending')
        ->and($state['filter_active_only'])->toBeTrue()
        ->and($state['filter_batch'])->toBeNull();
});

it('dispatches the job with batch filter when set', function () {
    Bus::fake();
    $mapping = ProjectMapping::factory()->active()->create(['redcap_pid' => 1846]);

    Livewire::test('admin.reprocess-card')
        ->set('activeOnly', false)
        ->set('batch', '13')
        ->call('startReprocess')
        ->assertHasNoErrors();

    Bus::assertDispatched(ProcessSourceProjectJob::class, function (ProcessSourceProjectJob $job) use ($mapping): bool {
        return $job->projectMappingId === $mapping->id
            && $job->activeOnly === false
            && $job->batch === '13';
    });
});

it('rejects an unknown batch value', function () {
    Bus::fake();
    ProjectMapping::factory()->active()->create(['redcap_pid' => 1846]);

    Livewire::test('admin.reprocess-card')
        ->set('batch', 'nope')
        ->call('startReprocess')
        ->assertHasErrors(['batch'])
        ->assertSet('jobId', null);

    Bus::assertNotDispatched(ProcessSourceProjectJob::class);
});

it('does not dispatch when there is no active source project', function () {
    Bus::fake();

    Livewire::test('admin.reprocess-card')
        ->call('startReprocess')
        ->assertSet('jobId', null);

    Bus::assertNotDispatched(ProcessSourceProjectJob::class);
});

it('reflects completed cache state on the polled component', function () {
    Bus::fake();
    ProjectMapping::factory()->active()->create(['redcap_pid' => 1846]);

    $component = Livewire::test('admin.reprocess-card')->call('startReprocess');
    $jobId = $component->get('jobId');

    Cache::put(
        ProcessSourceProjectJob::cacheKey($jobId),
        array_merge(ProcessSourceProjectJob::initialState($jobId, '1846', true, null), [
            'status' => 'complete',
            'total_groups' => 5,
            'processed_groups' => 5,
            'updated' => 4,
            'unchanged' => 1,
            'failed' => 0,
        ]),
        now()->addMinutes(ProcessSourceProjectJob::TTL_MINUTES),
    );

    $component->call('syncState')
        ->assertSee('Re-processing complete')
        ->assertSee('Run again');
});

it('keeps the result visible after the cache entry is evicted', function () {
    Bus::fake();
    ProjectMapping::factory()->active()->create(['redcap_pid' => 1846]);

    $component = Livewire::test('admin.reprocess-card')->call('startReprocess');
    $jobId = $component->get('jobId');

    Cache::put(
        ProcessSourceProjectJob::cacheKey($jobId),
        array_merge(ProcessSourceProjectJob::initialState($jobId, '1846', true, null), [
            'status' => 'complete',
            'total_groups' => 3,
            'processed_groups' => 3,
            'updated' => 2,
            'unchanged' => 1,
            'failed' => 0,
        ]),
        now()->addMinutes(ProcessSourceProjectJob::TTL_MINUTES),
    );

    $component->call('syncState');

    Cache::forget(ProcessSourceProjectJob::cacheKey($jobId));

    $component->call('$refresh')
        ->assertSee('Re-processing complete')
        ->assertSee('Run again');
});
