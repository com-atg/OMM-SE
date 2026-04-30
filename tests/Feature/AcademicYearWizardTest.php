<?php

use App\Jobs\ImportScholarsJob;
use App\Models\ProjectMapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    asService();
});

it('saves a project mapping marked active', function () {
    Livewire::test('admin.academic-year-wizard')
        ->set('redcap_pid', '2200')
        ->set('redcap_token', 'TESTTOKEN1234')
        ->call('saveProjectMapping')
        ->assertHasNoErrors()
        ->assertSet('savedProjectMappingId', fn ($id) => is_int($id) && $id > 0);

    $mapping = ProjectMapping::firstOrFail();

    expect($mapping->redcap_pid)->toBe(2200)
        ->and($mapping->redcap_token)->toBe('TESTTOKEN1234')
        ->and($mapping->is_active)->toBeTrue();
});

it('rejects invalid project mapping input without saving', function () {
    Livewire::test('admin.academic-year-wizard')
        ->set('redcap_pid', '')
        ->set('redcap_token', '')
        ->call('saveProjectMapping')
        ->assertHasErrors(['redcap_pid', 'redcap_token'])
        ->assertSet('savedProjectMappingId', null);

    expect(ProjectMapping::count())->toBe(0);
});

it('deactivates the previously active mapping when a new one is saved', function () {
    $previous = ProjectMapping::factory()->active()->create();

    Livewire::test('admin.academic-year-wizard')
        ->set('redcap_pid', '3300')
        ->set('redcap_token', 'NEWTOKEN')
        ->call('saveProjectMapping')
        ->assertHasNoErrors();

    expect($previous->fresh()->is_active)->toBeFalse();
    expect(ProjectMapping::where('redcap_pid', 3300)->first()->is_active)->toBeTrue();
});

it('starts the import job once the project mapping is saved', function () {
    Bus::fake();

    $component = Livewire::test('admin.academic-year-wizard');

    $component->call('startImport')->assertSet('importJobId', null);
    Bus::assertNotDispatched(ImportScholarsJob::class);

    $component
        ->set('redcap_pid', '2200')
        ->set('redcap_token', 'TESTTOKEN1234')
        ->call('saveProjectMapping')
        ->call('startImport')
        ->assertSet('importJobId', fn ($id) => is_string($id) && $id !== '')
        ->assertSet('importExpanded', true);

    Bus::assertDispatched(ImportScholarsJob::class, function (ImportScholarsJob $job) use ($component) {
        return $job->jobId === $component->get('importJobId')
            && $job->projectMappingId === $component->get('savedProjectMappingId');
    });

    $cacheState = Cache::get(ImportScholarsJob::cacheKey($component->get('importJobId')));
    expect($cacheState)->toBeArray()
        ->and($cacheState['status'])->toBe('pending');
});
