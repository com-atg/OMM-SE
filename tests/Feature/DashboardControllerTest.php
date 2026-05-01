<?php

use App\Livewire\Dashboard;
use App\Models\ProjectMapping;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

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
            'is_active' => '1',
            'batch' => '12',
            'sem1_nu_teaching' => '2', 'sem1_avg_teaching' => '88.5',
            'sem1_nu_clinic' => '1', 'sem1_avg_clinic' => '92.0',
            'sem2_nu_research' => '1', 'sem2_avg_research' => '75.0',
            'sem2_nu_didactics' => '', 'sem2_avg_didactics' => '',
        ],
        [
            'record_id' => '2',
            'is_active' => '1',
            'batch' => '12',
            'sem1_nu_teaching' => '1', 'sem1_avg_teaching' => '65.0',
            'sem2_nu_clinic' => '2', 'sem2_avg_clinic' => '80.0',
        ],
        [
            'record_id' => '3',
            'is_active' => '1',
            'batch' => '12',
            // Student with no evaluations yet.
        ],
    ];
}

it('renders the dashboard with aggregated stats', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12']);
    $destination->shouldReceive('getAllStudentRecords')->andReturn(destRoster());

    get('/')->assertOk()
        ->assertViewIs('dashboard')
        ->assertSee('OMM Student Evaluations')
        ->assertDontSee('Student detail')
        ->assertDontSee('Manage users');

    $stats = Livewire::test(Dashboard::class)->viewData('stats');

    expect($stats['kpis']['total_students'])->toBe(3)
        ->and($stats['kpis']['total_evals'])->toBe(7)
        ->and($stats['kpis']['students_evaluated'])->toBe(2)
        ->and($stats['kpis']['students_without_evals'])->toBe(1)
        ->and($stats['kpis']['overall_avg'])->toBeFloat()
        ->and($stats['category_labels'])->toBe(['Teaching', 'Clinic', 'Research', 'Didactics'])
        ->and($stats['volume_by_semester']['sem1'])->toBe([3, 1, 0, 0])
        ->and($stats['volume_by_semester']['sem2'])->toBe([0, 2, 1, 0])
        ->and($stats['volume_by_semester']['sem3'])->toBe([0, 0, 0, 0])
        ->and($stats['volume_by_semester']['sem4'])->toBe([0, 0, 0, 0])
        ->and($stats['coverage_pct'])->toBeArray()
        ->and($stats['histogram']['labels'])->toHaveCount(5);
});

it('centers dashboard category detail table columns', function () {
    $dashboard = file_get_contents(resource_path('views/livewire/dashboard.blade.php'));

    expect($dashboard)
        ->toContain('align="center">Category')
        ->toContain('align="center">Avg score')
        ->toContain('align="center">Coverage')
        ->toContain('container:class="mt-5 w-full" class="w-full min-w-[760px]')
        ->toContain('align="center">{{ $slotLabels[$slot] }}');
});

it('uses a categorical y axis for the coverage chart', function () {
    $dashboard = file_get_contents(resource_path('views/livewire/dashboard.blade.php'));

    expect($dashboard)
        ->toContain("indexAxis: 'y'")
        ->toContain("label: '% of Students'")
        ->toContain("type: 'category'")
        ->toContain("type: 'linear'")
        ->toContain('min: 0')
        ->toContain('offset: false')
        ->toContain('grid: { ...baseOptions.scales.x.grid, offset: false }')
        ->toContain("text: '% of roster'");
});

it('guards each dashboard chart canvas with wire:ignore so Livewire morphs do not blank charts on filter toggle', function () {
    $dashboard = file_get_contents(resource_path('views/livewire/dashboard.blade.php'));

    expect($dashboard)
        ->toContain('wire:ignore><canvas id="chartAvgByCategory"')
        ->toContain('wire:ignore><canvas id="chartVolumeBySemester"')
        ->toContain('wire:ignore><canvas id="chartScoreDistribution"')
        ->toContain('wire:ignore><canvas id="chartCoverage"')
        ->toContain('requestAnimationFrame(function ()');
});

it('labels dashboard charts with concise metric definitions', function () {
    $dashboard = file_get_contents(resource_path('views/livewire/dashboard.blade.php'));

    expect($dashboard)
        ->toContain('Evaluation-weighted average score on a 0-100 scale.')
        ->toContain('Completed evaluation count by category and semester.')
        ->toContain('Student category averages grouped into score bands.')
        ->toContain('Students with 1+ eval in that category divided by total roster.')
        ->toContain("text: 'Avg score (0-100)'")
        ->toContain("text: '# evaluations'")
        ->toContain("text: '# student-category averages'");
});

it('renders gracefully when the destination service throws', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn([]);
    $destination->shouldReceive('getAllStudentRecords')->andThrow(new RuntimeException('REDCap down'));

    get('/')->assertOk()
        ->assertViewIs('dashboard')
        ->assertSee('Dashboard data needs attention')
        ->assertSee('No student records are available');

    $stats = Livewire::test(Dashboard::class)->viewData('stats');
    expect($stats['has_students'])->toBeFalse()
        ->and($stats['has_evals'])->toBeFalse()
        ->and($stats['kpis']['total_students'])->toBe(0)
        ->and($stats['kpis']['total_evals'])->toBe(0)
        ->and($stats['fetch_error'])->toContain('Unable to connect to REDCap');
});

it('shows a friendly empty state when the roster returns no records', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn([]);
    $destination->shouldReceive('getAllStudentRecords')->once()->andReturn([]);

    get('/')
        ->assertOk()
        ->assertSee('No student records are available')
        ->assertDontSee('chartAvgByCategory');
});

it('shows the "no evaluations yet" state when students exist but have no evals', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn([]);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([
        ['record_id' => '1', 'first_name' => 'Ava', 'last_name' => 'Adams', 'is_active' => '1'],
        ['record_id' => '2', 'first_name' => 'Ben', 'last_name' => 'Brown', 'is_active' => '1'],
    ]);

    get('/')->assertOk()
        ->assertSee('No evaluations recorded yet')
        ->assertDontSee('chartAvgByCategory');

    $stats = Livewire::test(Dashboard::class)->viewData('stats');
    expect($stats['has_students'])->toBeTrue()
        ->and($stats['has_evals'])->toBeFalse()
        ->and($stats['kpis']['total_students'])->toBe(2);
});

it('filters destination roster to active students by default and respects batch selection', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12', '13']);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([
        ['record_id' => '1', 'is_active' => '1', 'batch' => '12', 'sem1_nu_teaching' => '1', 'sem1_avg_teaching' => '90'],
        ['record_id' => '2', 'is_active' => '0', 'batch' => '12', 'sem1_nu_teaching' => '1', 'sem1_avg_teaching' => '70'],
        ['record_id' => '3', 'is_active' => '1', 'batch' => '13', 'sem1_nu_teaching' => '1', 'sem1_avg_teaching' => '80'],
    ]);

    $defaultStats = Livewire::test(Dashboard::class)->viewData('stats');
    expect($defaultStats['kpis']['total_students'])->toBe(2);

    $allStats = Livewire::test(Dashboard::class)->set('activeOnly', false)->viewData('stats');
    expect($allStats['kpis']['total_students'])->toBe(3);

    $batchStats = Livewire::test(Dashboard::class)->set('selectedBatch', '13')->viewData('stats');
    expect($batchStats['kpis']['total_students'])->toBe(1);
});

it('applies the active and batch filters to the faculty dashboard path', function () {
    asFaculty('smith@example.com', 'Dr. Smith');

    ProjectMapping::factory()->active()->create([
        'redcap_pid' => 1846,
        'redcap_token' => 'CURRENT_PROJECT_TOKEN',
    ]);

    $sourceRecords = [
        ['record_id' => '101', 'student' => '100', 'semester' => '1', 'eval_category' => 'A', 'faculty' => 'Dr. Smith', 'faculty_email' => 'smith@example.com', 'date_lab' => '2026-04-01', 'teaching_score' => '90', 'small' => '6', 'large' => '5', 'knowledge' => '6', 'studevals' => '5', 'profess' => '6', 'omm_evaluation_complete' => '2'],
        ['record_id' => '102', 'student' => '200', 'semester' => '1', 'eval_category' => 'A', 'faculty' => 'Dr. Smith', 'faculty_email' => 'smith@example.com', 'date_lab' => '2026-04-02', 'teaching_score' => '80', 'small' => '6', 'large' => '5', 'knowledge' => '6', 'studevals' => '5', 'profess' => '6', 'omm_evaluation_complete' => '2'],
        ['record_id' => '103', 'student' => '300', 'semester' => '1', 'eval_category' => 'A', 'faculty' => 'Dr. Smith', 'faculty_email' => 'smith@example.com', 'date_lab' => '2026-04-03', 'teaching_score' => '70', 'small' => '6', 'large' => '5', 'knowledge' => '6', 'studevals' => '5', 'profess' => '6', 'omm_evaluation_complete' => '2'],
    ];

    $studentMap = [
        '100' => ['record_id' => '10', 'datatelid' => '100', 'is_active' => '1', 'batch' => '12', 'cohort_start_term' => 'Fall', 'cohort_start_year' => '2025'],
        '200' => ['record_id' => '20', 'datatelid' => '200', 'is_active' => '1', 'batch' => '13', 'cohort_start_term' => 'Fall', 'cohort_start_year' => '2025'],
        '300' => ['record_id' => '30', 'datatelid' => '300', 'is_active' => '0', 'batch' => '12', 'cohort_start_term' => 'Fall', 'cohort_start_year' => '2025'],
    ];

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getCompletedEvaluationRecords')->andReturn($sourceRecords);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12', '13']);
    $destination->shouldReceive('studentMapByDatatelId')->andReturn($studentMap);

    $defaultStats = Livewire::test(Dashboard::class)->viewData('stats');
    expect($defaultStats['kpis']['total_students'])->toBe(2);

    $allStats = Livewire::test(Dashboard::class)->set('activeOnly', false)->viewData('stats');
    expect($allStats['kpis']['total_students'])->toBe(3);

    $batchStats = Livewire::test(Dashboard::class)
        ->set('activeOnly', false)
        ->set('selectedBatch', '12')
        ->viewData('stats');
    expect($batchStats['kpis']['total_students'])->toBe(2);

    $batchActiveStats = Livewire::test(Dashboard::class)
        ->set('activeOnly', true)
        ->set('selectedBatch', '12')
        ->viewData('stats');
    expect($batchActiveStats['kpis']['total_students'])->toBe(1);
});

it('does not cache failed dashboard fetches as empty data', function () {
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn([]);
    $destination->shouldReceive('getAllStudentRecords')->andThrow(new RuntimeException('REDCap down'));

    get('/')->assertOk()
        ->assertSee('Dashboard data needs attention');

    Cache::flush();

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12']);
    $destination->shouldReceive('getAllStudentRecords')->andReturn(destRoster());

    get('/')->assertOk()
        ->assertDontSee('Dashboard data needs attention');

    $stats = Livewire::test(Dashboard::class)->viewData('stats');
    expect($stats['kpis']['total_students'])->toBe(3)
        ->and($stats['kpis']['total_evals'])->toBe(7);
});
