<?php

use App\Services\RedcapDestinationService;
use Illuminate\Support\Facades\Cache;

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
            'first_name' => 'Catherine',
            'last_name' => 'Chin',
            'goes_by' => 'Cat',
            'spring_nu_teaching' => '2', 'spring_avg_teaching' => '88.5',
            'spring_nu_clinic' => '1', 'spring_avg_clinic' => '92.0',
            'spring_nu_research' => '0', 'spring_avg_research' => '',
            'spring_nu_didactics' => '0', 'spring_avg_didactics' => '',
            'spring_nu_comments' => '2',
            'fall_nu_teaching' => '1', 'fall_avg_teaching' => '75.0',
            'fall_nu_clinic' => '0',
            'fall_nu_research' => '0',
            'fall_nu_didactics' => '0',
        ],
        [
            'record_id' => '11',
            'first_name' => 'Ava',
            'last_name' => 'Adams',
            'goes_by' => '',
        ],
    ];
}

it('renders the picker with a sorted roster and no selection', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn(scholarRoster());

    $response = get('/scholar');

    $response->assertOk()
        ->assertViewIs('scholar')
        ->assertSee('Select a scholar')
        ->assertSee('Ava Adams', false)
        ->assertSee('Cat Chin', false);

    expect($response->viewData('selected'))->toBeNull()
        ->and($response->viewData('semesters'))->toBe([]);

    // Roster sorted alphabetically by name.
    $roster = $response->viewData('roster');
    expect($roster[0]['name'])->toBe('Ava Adams')
        ->and($roster[1]['name'])->toBe('Cat Chin');
});

it('renders per-semester eval counts when a scholar is selected', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn(scholarRoster());

    $response = get('/scholar?id=10');

    $response->assertOk()->assertSee('Cat Chin', false);

    $semesters = $response->viewData('semesters');

    expect($semesters)->toHaveCount(2)
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
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn(scholarRoster());

    $response = get('/scholar?id=999');

    $response->assertOk();
    expect($response->viewData('selected'))->toBeNull();
});
