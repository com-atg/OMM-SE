<?php

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
    ProjectMapping::factory()->active()->create([
        'redcap_pid' => 1846,
        'redcap_token' => 'CURRENT_PROJECT_TOKEN',
    ]);
});

function facultySourceRecords(): array
{
    return [
        [
            'record_id' => '101',
            'student' => '100',
            'semester' => '1',
            'eval_category' => 'A',
            'faculty' => 'Dr. Smith',
            'faculty_email' => 'smith@example.com',
            'date_lab' => '2026-04-01',
            'teaching_score' => '92.50',
            'small' => '6',
            'large' => '5',
            'knowledge' => '6',
            'studevals' => '5',
            'profess' => '6',
            'comments' => 'Strong teaching session.',
            'omm_evaluation_complete' => '2',
        ],
        [
            'record_id' => '102',
            'student' => '200',
            'semester' => '2',
            'eval_category' => 'B',
            'faculty' => 'Dr. Jones',
            'date_lab' => '2026-04-02',
            'clinical_performance_score' => '85.00',
            'omm_evaluation_complete' => '2',
        ],
        [
            'record_id' => '103',
            'student' => '100',
            'semester' => '2',
            'eval_category' => 'D',
            'faculty' => 'Dr. Smith',
            'faculty_email' => 'smith@example.com',
            'date_lab' => '2026-04-03',
            'didactic_total_score' => '88.00',
            'comments' => '',
            'omm_evaluation_complete' => '2',
        ],
    ];
}

function facultyStudentMap(): array
{
    return [
        '100' => [
            'record_id' => '10',
            'datatelid' => '100',
            'first_name' => 'Catherine',
            'last_name' => 'Chin',
            'goes_by' => 'Cat',
            'is_active' => '1',
            'batch' => '12',
        ],
        '200' => [
            'record_id' => '20',
            'datatelid' => '200',
            'first_name' => 'Ava',
            'last_name' => 'Adams',
            'goes_by' => '',
            'is_active' => '1',
            'batch' => '12',
        ],
    ];
}

it('renders the faculty page for users who can view all students', function () {
    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getCompletedEvaluationRecords')
        ->with('CURRENT_PROJECT_TOKEN')
        ->andReturn(facultySourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12']);
    $destination->shouldReceive('studentMapByDatatelId')->andReturn(facultyStudentMap());

    get('/faculty')
        ->assertOk()
        ->assertViewIs('faculty')
        ->assertSee('Faculty Evaluation Activity')
        ->assertSee('Faculty scope', false)
        ->assertSee('Dr. Jones', false)
        ->assertSee('Dr. Smith', false);
});

it('uses the active source project token to fetch evaluation records', function () {
    ProjectMapping::factory()->create([
        'redcap_pid' => 1847,
        'redcap_token' => 'INACTIVE_TOKEN',
    ]);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getCompletedEvaluationRecords')
        ->with('CURRENT_PROJECT_TOKEN')
        ->andReturn(facultySourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12']);
    $destination->shouldReceive('studentMapByDatatelId')->andReturn(facultyStudentMap());

    get('/faculty')
        ->assertOk()
        ->assertSee('Dr. Smith', false);
});

it('forbids students from the faculty page', function () {
    asStudent('10');

    get('/faculty')->assertForbidden();
});

it('lets faculty view only their own evaluations without the faculty selector', function () {
    asFaculty('smith@example.com', 'Dr. Smith');

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getCompletedEvaluationRecords')->andReturn(facultySourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12']);
    $destination->shouldReceive('studentMapByDatatelId')->andReturn(facultyStudentMap());

    get('/faculty')
        ->assertOk()
        ->assertSee('Dr. Smith', false)
        ->assertSee('Cat Chin', false)
        ->assertSee('Teaching', false)
        ->assertSee('Active only', false)
        ->assertSee('Batch', false)
        ->assertDontSee('Choose a faculty member', false)
        ->assertDontSee('Select faculty...', false)
        ->assertDontSee('Dr. Jones', false)
        ->assertDontSee('Ava Adams', false);
});

it('lets faculty narrow their roster with the batch filter', function () {
    asFaculty('smith@example.com', 'Dr. Smith');

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getCompletedEvaluationRecords')->andReturn(facultySourceRecords());

    $studentMap = facultyStudentMap();
    $studentMap['100']['batch'] = '12';
    $studentMap['200']['batch'] = '13';

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12', '13']);
    $destination->shouldReceive('studentMapByDatatelId')->andReturn($studentMap);

    Livewire::test('faculty-detail')
        ->assertSee('Cat Chin')
        ->set('selectedBatch', '13')
        ->assertSee('Active only', false)
        ->assertDontSee('Cat Chin');
});

it('prevents faculty from opening another faculty evaluation through livewire', function () {
    asFaculty('smith@example.com', 'Dr. Smith');

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getCompletedEvaluationRecords')->andReturn(facultySourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12']);
    $destination->shouldReceive('studentMapByDatatelId')->andReturn(facultyStudentMap());

    Livewire::test('faculty-detail')
        ->call('openEvaluation', '102')
        ->assertSet('detailModalOpen', true)
        ->assertDontSee('Ava Adams')
        ->assertDontSee('#102');
});

it('updates evaluations when a faculty member is selected', function () {
    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getCompletedEvaluationRecords')->andReturn(facultySourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12']);
    $destination->shouldReceive('studentMapByDatatelId')->andReturn(facultyStudentMap());

    Livewire::test('faculty-detail')
        ->assertSee('Select faculty')
        ->assertDontSee('Completed Evaluations')
        ->set('selectedFaculty', 'Dr. Smith')
        ->assertSet('selectedFaculty', 'Dr. Smith')
        ->assertSee('Faculty Profile')
        ->assertSee('Cat Chin')
        ->assertSee('Teaching')
        ->assertSee('Didactics')
        ->assertSee('92.50%')
        ->assertDontSee('Ava Adams');
});

it('opens a modal with specific evaluation details', function () {
    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getCompletedEvaluationRecords')->andReturn(facultySourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12']);
    $destination->shouldReceive('studentMapByDatatelId')->andReturn(facultyStudentMap());

    Livewire::test('faculty-detail')
        ->set('selectedFaculty', 'Dr. Smith')
        ->call('openEvaluation', '101')
        ->assertSet('detailModalOpen', true)
        ->assertSet('selectedRecordId', '101')
        ->assertSee('Evaluation Detail')
        ->assertSee('Individual / Small Group Teaching')
        ->assertSee('Strong teaching session.')
        ->assertSee('#101');
});

it('always uses the active source project token regardless of inactive mappings', function () {
    ProjectMapping::factory()->create([
        'redcap_pid' => 1847,
        'redcap_token' => 'INACTIVE_TOKEN',
    ]);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getCompletedEvaluationRecords')->with('CURRENT_PROJECT_TOKEN')->andReturn(facultySourceRecords());

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn(['12']);
    $destination->shouldReceive('studentMapByDatatelId')->andReturn(facultyStudentMap());

    Livewire::test('faculty-detail')
        ->assertSee('Dr. Smith');
});

it('centers the faculty and modal table columns', function () {
    $view = file_get_contents(resource_path('views/livewire/faculty-detail.blade.php'));

    expect($view)
        ->toContain('align="center">Student')
        ->toContain('align="center">Semester')
        ->toContain('align="center">Category')
        ->toContain('align="center">Score')
        ->toContain('align="center">Date')
        ->toContain('align="center">Criterion')
        ->toContain('align="center">{{ $evaluation[\'student_name\'] }}')
        ->toContain('align="center">{{ $criterion[\'label\'] }}');
});
