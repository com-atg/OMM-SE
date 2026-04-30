<?php

use App\Services\RedcapDestinationService;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\get;
use function Pest\Laravel\mock;

beforeEach(function () {
    Cache::flush();
    asService();
});

function studentRoster(): array
{
    return [
        [
            'record_id' => '10',
            'datatelid' => '1234567',
            'first_name' => 'Catherine',
            'last_name' => 'Chin',
            'goes_by' => 'Cat',
            'is_active' => '1',
            'cohort_start_term' => 'Spring',
            'cohort_start_year' => '2026',
            'sem1_nu_teaching' => '2', 'sem1_avg_teaching' => '88.5',
            'sem1_nu_clinic' => '1', 'sem1_avg_clinic' => '92.0',
            'sem1_nu_research' => '0', 'sem1_avg_research' => '',
            'sem1_nu_didactics' => '0', 'sem1_avg_didactics' => '',
            'sem1_nu_comments' => '2',
            'sem1_leadership' => '8',
            'sem2_nu_teaching' => '1', 'sem2_avg_teaching' => '75.0',
            'sem2_final_score' => '91.25',
            'sem2_leadership' => '9',
            'sem2_nu_clinic' => '0',
            'sem2_nu_research' => '0',
            'sem2_nu_didactics' => '0',
        ],
        [
            'record_id' => '11',
            'datatelid' => '7654321',
            'first_name' => 'Ava',
            'last_name' => 'Adams',
            'goes_by' => '',
            'is_active' => '1',
            'cohort_start_term' => 'Fall',
            'cohort_start_year' => '2026',
        ],
    ];
}

it('renders the picker with a sorted roster and no selection', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->twice()->andReturn(studentRoster());
    $destination->shouldReceive('availableBatches')->andReturn([]);

    $response = get('/student');

    $response->assertOk()
        ->assertViewIs('student')
        ->assertSee('Select a student')
        ->assertSee('OMM ACE', false)
        ->assertSee('data-flux-select-native', false)
        ->assertDontSee('scriptModule', false)
        ->assertDontSee('student-detail.js', false)
        ->assertDontSee('livewire.min.js', false)
        ->assertDontSee('flux.min.js', false)
        ->assertDontSee('View', false)
        ->assertDontSee('Clear', false)
        ->assertSee('Ava Adams', false)
        ->assertSee('Cat Chin', false);

    expect($response->viewData('selected'))->toBeNull()
        ->and($response->viewData('semesters'))->toBe([]);

    // Roster sorted alphabetically by name.
    $roster = $response->viewData('roster');
    expect($roster[0]['name'])->toBe('Ava Adams')
        ->and($roster[1]['name'])->toBe('Cat Chin');
});

it('bundles the Livewire and Flux runtimes through Vite', function () {
    $entrypoint = file_get_contents(resource_path('js/app.js'));

    expect($entrypoint)
        ->toContain('livewire.esm')
        ->toContain('flux-pro/dist/flux.module.js')
        ->toContain('student-detail-charts')
        ->toContain('Livewire.start()');

    expect(file_get_contents(resource_path('js/student-detail-charts.js')))
        ->toContain('renderStudentCharts')
        ->toContain('bootStudentDetailCharts')
        ->not->toContain('data-student-chart="weights"')
        ->not->toContain("type: 'doughnut'");
});

it('uses a path-safe Livewire config for the Vite runtime', function () {
    $shell = file_get_contents(resource_path('views/components/app-shell.blade.php'));

    expect($shell)
        ->toContain('window.livewireScriptConfig')
        ->toContain('@livewireScriptConfig')
        ->toContain('parse_url(config(\'app.url\'), PHP_URL_PATH)')
        ->toContain('app()->isProduction()')
        ->toContain('runtime.livewire')
        ->not->toContain('@livewireScripts')
        ->not->toContain('@fluxScripts');
});

it('ignores configured app url paths for local livewire endpoints', function () {
    config(['app.url' => 'http://localhost:8000/omm_ace']);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->twice()->andReturn(studentRoster());
    $destination->shouldReceive('availableBatches')->andReturn([]);

    get('/student')
        ->assertOk()
        ->assertSee('/livewire-', false)
        ->assertDontSee('/omm_ace/livewire-', false);
});

it('centers evaluation summary table columns', function () {
    $component = file_get_contents(resource_path('views/components/⚡student-detail.blade.php'));

    expect($component)
        ->toContain('align="center">Category')
        ->toContain('align="center">Evals')
        ->toContain('align="center">Avg')
        ->toContain('align="center">{{ $sem[\'category_labels\'][$j] }}')
        ->toContain('align="center">{{ $sem[\'counts\'][$j] }}');
});

it('serves runtime fallback assets without js or css route extensions', function () {
    get(route('runtime.livewire', absolute: false))->assertOk();
    get(route('runtime.flux', absolute: false))->assertOk();
    get(route('runtime.student-detail-charts', absolute: false))->assertOk();
    get(route('runtime.flux-styles', absolute: false))->assertOk();
});

it('renders per-semester eval counts when a student is selected', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->twice()->andReturn(studentRoster());
    $destination->shouldReceive('availableBatches')->andReturn([]);

    $response = get('/student?id=10');

    $response->assertOk()
        ->assertSee('Cat Chin', false)
        ->assertSee('Leadership', false)
        ->assertSee('17/40', false)
        ->assertDontSee('Final Grade', false)
        ->assertDontSee('Weight Distribution', false)
        ->assertDontSee('data-student-chart="weights"', false)
        ->assertDontSee('REDCap formula', false)
        ->assertDontSee('round(([sem1_avg_teaching]*0.25)', false)
        ->assertSee('https://guru.nyit.edu/GuruAdmin/StudentOverview/StudentPhotoImageHandler.ashx?id=1234567', false);

    $semesters = $response->viewData('semesters');
    $selected = $response->viewData('selected');

    expect($selected['datatelid'])->toBe('1234567')
        ->and($selected['photo_url'])->toBe('https://guru.nyit.edu/GuruAdmin/StudentOverview/StudentPhotoImageHandler.ashx?id=1234567')
        ->and($semesters)->toHaveCount(4)
        ->and($semesters[0]['slug'])->toBe('sem1')
        ->and($semesters[0]['label'])->toBe('Spring 2026')
        ->and($semesters[0]['counts'])->toBe([2, 1, 0, 0])
        ->and($semesters[0]['averages'])->toBe([88.5, 92.0, null, null])
        ->and($semesters[0]['total'])->toBe(3)
        ->and($semesters[1]['slug'])->toBe('sem2')
        ->and($semesters[1]['label'])->toBe('Fall 2026')
        ->and($semesters[1]['counts'])->toBe([1, 0, 0, 0])
        ->and($semesters[1]['total'])->toBe(1)
        ->and($semesters[2]['slug'])->toBe('sem3')
        ->and($semesters[3]['slug'])->toBe('sem4');
});

it('ignores an unknown student id', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->twice()->andReturn(studentRoster());
    $destination->shouldReceive('availableBatches')->andReturn([]);

    $response = get('/student?id=999');

    $response->assertOk();
    expect($response->viewData('selected'))->toBeNull();
});

it('updates the student detail component when the selection changes', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->twice()->andReturn(studentRoster());
    $destination->shouldReceive('availableBatches')->andReturn([]);

    Livewire::test('student-detail')
        ->assertSee('Select a student')
        ->assertDontSee('Leadership')
        ->set('selectedId', '10')
        ->assertSet('selectedId', '10')
        ->assertSee('Cat Chin')
        ->assertSee('Leadership');
});

it('renders a student with no final score', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->once()->andReturn(studentRoster());
    $destination->shouldReceive('availableBatches')->andReturn([]);

    Livewire::test('student-detail', ['initialSelectedId' => '11'])
        ->assertSee('Ava Adams')
        ->assertDontSee('Final Grade');
});

it('hides student-only navigation and sharing controls on the student view', function () {
    $student = asStudent('10');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->twice()->andReturn(studentRoster());
    $destination->shouldReceive('availableBatches')->andReturn([]);

    $response = get('/student');

    $response->assertOk()
        ->assertSee('Cat Chin', false)
        ->assertDontSee('Shareable Link', false)
        ->assertDontSee(route('student.token', $student->public_token), false)
        ->assertDontSee('href="'.route('dashboard', absolute: false).'"', false);

    expect($response->viewData('shareable_url'))->toBeNull();
});
