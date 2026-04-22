<?php

use App\Services\RedcapDestinationService;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\get;
use function Pest\Laravel\mock;

beforeEach(function () {
    Cache::flush();
    asService();
});

function destRoster(): array
{
    return [
        [
            'record_id' => '1',
            'spring_nu_teaching' => '2', 'spring_avg_teaching' => '88.5',
            'spring_nu_clinic' => '1', 'spring_avg_clinic' => '92.0',
            'fall_nu_research' => '1', 'fall_avg_research' => '75.0',
            'fall_nu_didactics' => '', 'fall_avg_didactics' => '',
        ],
        [
            'record_id' => '2',
            'spring_nu_teaching' => '1', 'spring_avg_teaching' => '65.0',
            'fall_nu_clinic' => '2', 'fall_avg_clinic' => '80.0',
        ],
        [
            'record_id' => '3',
            // Scholar with no evaluations yet.
        ],
    ];
}

it('renders the dashboard with aggregated stats', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn(destRoster());

    $response = get('/');

    $response->assertOk()
        ->assertViewIs('dashboard')
        ->assertSee('OMM Scholar Evaluations');

    $stats = $response->viewData('stats');

    expect($stats['kpis']['total_scholars'])->toBe(3)
        ->and($stats['kpis']['total_evals'])->toBe(7)
        ->and($stats['kpis']['scholars_evaluated'])->toBe(2)
        ->and($stats['kpis']['scholars_without_evals'])->toBe(1)
        ->and($stats['kpis']['overall_avg'])->toBeFloat()
        ->and($stats['category_labels'])->toBe(['Teaching', 'Clinic', 'Research', 'Didactics'])
        ->and($stats['volume_by_semester']['spring'])->toBe([3, 1, 0, 0])
        ->and($stats['volume_by_semester']['fall'])->toBe([0, 2, 1, 0])
        ->and($stats['coverage_pct'])->toBeArray()
        ->and($stats['histogram']['labels'])->toHaveCount(5);
});

it('renders gracefully when the destination service throws', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andThrow(new RuntimeException('REDCap down'));

    $response = get('/');

    $response->assertOk()
        ->assertViewIs('dashboard')
        ->assertSee('Dashboard data needs attention')
        ->assertSee('No scholar records are available');

    $stats = $response->viewData('stats');
    expect($stats['has_scholars'])->toBeFalse()
        ->and($stats['has_evals'])->toBeFalse()
        ->and($stats['kpis']['total_scholars'])->toBe(0)
        ->and($stats['kpis']['total_evals'])->toBe(0)
        ->and($stats['fetch_error'])->toContain('Unable to connect to REDCap');
});

it('shows a friendly empty state when the roster returns no records', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([]);

    get('/')
        ->assertOk()
        ->assertSee('No scholar records are available')
        ->assertDontSee('chartAvgByCategory');
});

it('shows the "no evaluations yet" state when scholars exist but have no evals', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([
        ['record_id' => '1', 'first_name' => 'Ava', 'last_name' => 'Adams'],
        ['record_id' => '2', 'first_name' => 'Ben', 'last_name' => 'Brown'],
    ]);

    $response = get('/');

    $response->assertOk()
        ->assertSee('No evaluations recorded yet')
        ->assertDontSee('chartAvgByCategory');

    $stats = $response->viewData('stats');
    expect($stats['has_scholars'])->toBeTrue()
        ->and($stats['has_evals'])->toBeFalse()
        ->and($stats['kpis']['total_scholars'])->toBe(2);
});

it('does not cache failed dashboard fetches as empty data', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andThrow(new RuntimeException('REDCap down'));

    get('/')->assertOk()
        ->assertSee('Dashboard data needs attention');

    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn(destRoster());

    $response = get('/');

    $response->assertOk()
        ->assertDontSee('Dashboard data needs attention');

    expect($response->viewData('stats')['kpis']['total_scholars'])->toBe(3)
        ->and($response->viewData('stats')['kpis']['total_evals'])->toBe(7);
});
