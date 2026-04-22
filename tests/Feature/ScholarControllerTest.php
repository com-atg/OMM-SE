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

function scholarRoster(): array
{
    return [
        [
            'record_id' => '10',
            'datatelid' => '1234567',
            'first_name' => 'Catherine',
            'last_name' => 'Chin',
            'goes_by' => 'Cat',
            'spring_nu_teaching' => '2', 'spring_avg_teaching' => '88.5',
            'spring_nu_clinic' => '1', 'spring_avg_clinic' => '92.0',
            'spring_nu_research' => '0', 'spring_avg_research' => '',
            'spring_nu_didactics' => '0', 'spring_avg_didactics' => '',
            'spring_nu_comments' => '2',
            'fall_nu_teaching' => '1', 'fall_avg_teaching' => '75.0',
            'fall_final_score' => '91.25',
            'fall_nu_clinic' => '0',
            'fall_nu_research' => '0',
            'fall_nu_didactics' => '0',
        ],
        [
            'record_id' => '11',
            'datatelid' => '7654321',
            'first_name' => 'Ava',
            'last_name' => 'Adams',
            'goes_by' => '',
        ],
    ];
}

it('renders the picker with a sorted roster and no selection', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->twice()->andReturn(scholarRoster());

    $response = get('/scholar');

    $response->assertOk()
        ->assertViewIs('scholar')
        ->assertSee('Select a scholar')
        ->assertSee('NYITCOM', false)
        ->assertSee('data-flux-select-native', false)
        ->assertDontSee('scriptModule', false)
        ->assertDontSee('scholar-detail.js', false)
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
        ->toContain('scholar-detail-charts')
        ->toContain('Livewire.start()');

    expect(file_get_contents(resource_path('js/scholar-detail-charts.js')))
        ->toContain('renderScholarCharts')
        ->toContain('bootScholarDetailCharts');
});

it('uses a path-safe Livewire config for the Vite runtime', function () {
    $shell = file_get_contents(resource_path('views/components/app-shell.blade.php'));

    expect($shell)
        ->toContain('window.livewireScriptConfig')
        ->toContain('@livewireScriptConfig')
        ->toContain('parse_url(config(\'app.url\'), PHP_URL_PATH)')
        ->toContain('runtime.livewire')
        ->not->toContain('@livewireScripts')
        ->not->toContain('@fluxScripts');
});

it('serves runtime fallback assets without js or css route extensions', function () {
    get(route('runtime.livewire', absolute: false))->assertOk();
    get(route('runtime.flux', absolute: false))->assertOk();
    get(route('runtime.scholar-detail-charts', absolute: false))->assertOk();
    get(route('runtime.flux-styles', absolute: false))->assertOk();
});

it('renders per-semester eval counts when a scholar is selected', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->twice()->andReturn(scholarRoster());

    $response = get('/scholar?id=10');

    $response->assertOk()
        ->assertSee('Cat Chin', false)
        ->assertSee('Scholar Profile', false)
        ->assertSee('Final Grade', false)
        ->assertSee('91.25', false)
        ->assertSee('https://guru.nyit.edu/GuruAdmin/StudentOverview/StudentPhotoImageHandler.ashx?id=1234567', false);

    $semesters = $response->viewData('semesters');
    $selected = $response->viewData('selected');

    expect($selected['datatelid'])->toBe('1234567')
        ->and($selected['photo_url'])->toBe('https://guru.nyit.edu/GuruAdmin/StudentOverview/StudentPhotoImageHandler.ashx?id=1234567')
        ->and($semesters)->toHaveCount(2)
        ->and($semesters[0]['slug'])->toBe('spring')
        ->and($semesters[0]['counts'])->toBe([2, 1, 0, 0])
        ->and($semesters[0]['averages'])->toBe([88.5, 92.0, null, null])
        ->and($semesters[0]['total'])->toBe(3)
        ->and($semesters[1]['slug'])->toBe('fall')
        ->and($semesters[1]['counts'])->toBe([1, 0, 0, 0])
        ->and($semesters[1]['total'])->toBe(1);
});

it('ignores an unknown scholar id', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->twice()->andReturn(scholarRoster());

    $response = get('/scholar?id=999');

    $response->assertOk();
    expect($response->viewData('selected'))->toBeNull();
});

it('updates the scholar detail component when the selection changes', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->twice()->andReturn(scholarRoster());

    Livewire::test('scholar-detail')
        ->assertSee('Select a scholar')
        ->assertDontSee('Scholar Profile')
        ->set('selectedId', '10')
        ->assertSet('selectedId', '10')
        ->assertSee('Cat Chin')
        ->assertSee('Final Grade')
        ->assertSee('91.25');
});

it('shows a pending final grade when no final score exists', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn(scholarRoster());

    Livewire::test('scholar-detail', ['initialSelectedId' => '11'])
        ->assertSee('Ava Adams')
        ->assertSee('Final Grade')
        ->assertSee('Pending')
        ->assertSee('Final grade is not available yet.');
});

it('hides student-only navigation and sharing controls on the scholar view', function () {
    $student = asStudent('10');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->twice()->andReturn(scholarRoster());

    $response = get('/scholar');

    $response->assertOk()
        ->assertSee('Cat Chin', false)
        ->assertDontSee('Shareable Link', false)
        ->assertDontSee(route('scholar.token', $student->public_token), false)
        ->assertDontSee('href="'.route('dashboard', absolute: false).'"', false);

    expect($response->viewData('shareable_url'))->toBeNull();
});
