<?php

use App\Enums\WeightCategory;
use App\Jobs\ImportScholarsJob;
use App\Models\CategoryWeight;
use App\Models\ProjectMapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    asService();
});

it('saves a project mapping and unlocks the weights step', function () {
    Livewire::test('admin.academic-year-wizard', ['nextGraduationYear' => 2029])
        ->set('academic_year', '2025-2026')
        ->set('graduation_year', '2029')
        ->set('redcap_pid', '2200')
        ->set('redcap_token', 'TESTTOKEN1234')
        ->call('saveProjectMapping')
        ->assertHasNoErrors()
        ->assertSet('savedProjectMappingId', fn ($id) => is_int($id) && $id > 0);

    $mapping = ProjectMapping::firstOrFail();

    expect($mapping->academic_year)->toBe('2025-2026')
        ->and($mapping->graduation_year)->toBe(2029)
        ->and($mapping->redcap_pid)->toBe(2200)
        ->and($mapping->redcap_token)->toBe('TESTTOKEN1234');
});

it('rejects invalid project mapping input without saving', function () {
    Livewire::test('admin.academic-year-wizard', ['nextGraduationYear' => 2029])
        ->set('academic_year', 'bad-format')
        ->set('graduation_year', 'NaN')
        ->set('redcap_pid', '')
        ->set('redcap_token', '')
        ->call('saveProjectMapping')
        ->assertHasErrors(['academic_year', 'graduation_year', 'redcap_pid', 'redcap_token'])
        ->assertSet('savedProjectMappingId', null);

    expect(ProjectMapping::count())->toBe(0);
});

it('saves category weights only when they sum to 100', function () {
    $component = Livewire::test('admin.academic-year-wizard', ['nextGraduationYear' => 2029])
        ->set('academic_year', '2025-2026')
        ->set('graduation_year', '2029')
        ->set('redcap_pid', '2200')
        ->set('redcap_token', 'TESTTOKEN1234')
        ->call('saveProjectMapping');

    $component
        ->set('weights.teaching', '10')
        ->set('weights.clinic', '10')
        ->set('weights.research', '10')
        ->set('weights.didactics', '10')
        ->set('weights.leadership', '10')
        ->call('saveWeights')
        ->assertHasErrors('weights.total')
        ->assertSet('weightsSaved', false);

    expect(CategoryWeight::count())->toBe(0);

    $component
        ->set('weights.teaching', '40')
        ->set('weights.clinic', '20')
        ->set('weights.research', '20')
        ->set('weights.didactics', '10')
        ->set('weights.leadership', '10')
        ->call('saveWeights')
        ->assertHasNoErrors()
        ->assertSet('weightsSaved', true);

    $mapping = ProjectMapping::firstOrFail();

    expect($mapping->categoryWeights()->count())->toBe(5)
        ->and((float) $mapping->categoryWeights()->where('category', WeightCategory::Teaching)->value('weight'))->toBe(40.0);
});

it('refuses to save weights before the project mapping is saved', function () {
    Livewire::test('admin.academic-year-wizard', ['nextGraduationYear' => 2029])
        ->set('weights.teaching', '40')
        ->set('weights.clinic', '20')
        ->set('weights.research', '20')
        ->set('weights.didactics', '10')
        ->set('weights.leadership', '10')
        ->call('saveWeights')
        ->assertSet('weightsSaved', false);

    expect(CategoryWeight::count())->toBe(0);
});

it('starts the import job only after step 5 and step 6 are saved', function () {
    Bus::fake();

    $component = Livewire::test('admin.academic-year-wizard', ['nextGraduationYear' => 2029])
        ->set('academic_year', '2025-2026')
        ->set('graduation_year', '2029')
        ->set('redcap_pid', '2200')
        ->set('redcap_token', 'TESTTOKEN1234')
        ->call('saveProjectMapping');

    $component->call('startImport')->assertSet('importJobId', null);
    Bus::assertNotDispatched(ImportScholarsJob::class);

    $component
        ->set('weights.teaching', '40')
        ->set('weights.clinic', '20')
        ->set('weights.research', '20')
        ->set('weights.didactics', '10')
        ->set('weights.leadership', '10')
        ->call('saveWeights')
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
