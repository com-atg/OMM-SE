<?php

use App\Livewire\Dashboard;
use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\mock;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(function () {
    Cache::flush();
});

it('stores a compact relative intended URL before SAML login', function () {
    get('/student?id=10')
        ->assertRedirect(route('saml.login'))
        ->assertSessionHas('url.intended', '/student?id=10');
});

it('falls back to dashboard when the intended URL is too large', function () {
    get('/student?'.http_build_query(['payload' => str_repeat('x', 2100)]))
        ->assertRedirect(route('saml.login'))
        ->assertSessionHas('url.intended', route('dashboard', absolute: false));
});

// ─── Dashboard ───────────────────────────────────────────────────────────────

it('redirects students from dashboard to their student page', function () {
    asStudent('10');

    get('/')->assertRedirect(route('student'));
});

it('lets service users view the dashboard', function () {
    asService();
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn([]);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([]);

    get('/')->assertOk();
});

it('lets admin users view the dashboard', function () {
    asAdmin();
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn([]);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([]);

    get('/')->assertOk();
});

it('lets faculty users view a scoped dashboard', function () {
    asFaculty('smith@example.com', 'Dr. Smith');
    ProjectMapping::factory()->active()->create(['redcap_pid' => 1846, 'redcap_token' => 'SOURCE_TOKEN']);

    $source = mock(RedcapSourceService::class);
    $source->shouldReceive('getCompletedEvaluationRecords')
        ->with('SOURCE_TOKEN')
        ->andReturn([
            [
                'record_id' => '101',
                'student' => '100',
                'semester' => '1',
                'eval_category' => 'A',
                'faculty' => 'Dr. Smith',
                'faculty_email' => 'smith@example.com',
                'teaching_score' => '90',
                'date_lab' => '2026-04-01',
            ],
            [
                'record_id' => '102',
                'student' => '200',
                'semester' => '1',
                'eval_category' => 'A',
                'faculty' => 'Dr. Jones',
                'faculty_email' => 'jones@example.com',
                'teaching_score' => '70',
                'date_lab' => '2026-04-01',
            ],
        ]);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('availableBatches')->andReturn([]);
    $destination->shouldReceive('studentMapByDatatelId')->andReturn([
        '100' => ['record_id' => '10', 'datatelid' => '100', 'is_active' => '1', 'cohort_start_term' => 'Spring', 'cohort_start_year' => '2026'],
        '200' => ['record_id' => '20', 'datatelid' => '200', 'is_active' => '1', 'cohort_start_term' => 'Spring', 'cohort_start_year' => '2026'],
    ]);

    get('/')->assertOk();

    $stats = Livewire::test(Dashboard::class)->viewData('stats');
    expect($stats['kpis']['total_evals'])->toBe(1)
        ->and($stats['kpis']['total_students'])->toBe(1);
});

// ─── Student page — student scoping ──────────────────────────────────────────

it('shows a student only their own record and locks the picker', function () {
    asStudent('10');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
        ['record_id' => '11', 'first_name' => 'Ava', 'last_name' => 'Adams'],
    ]);
    $destination->shouldReceive('finalScoreFormulas')->andReturn([]);

    $response = get('/student');

    $response->assertOk();
    expect($response->viewData('lock_selection'))->toBeTrue()
        ->and($response->viewData('roster'))->toBe([])
        ->and($response->viewData('selected')['record_id'])->toBe('10');
});

it('returns 404 when a student has no matching record', function () {
    asStudent('999');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);

    get('/student')->assertNotFound();
});

it('ignores ?id query string for students and forces their own record', function () {
    asStudent('10');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
        ['record_id' => '11', 'first_name' => 'Ava', 'last_name' => 'Adams'],
    ]);
    $destination->shouldReceive('finalScoreFormulas')->andReturn([]);

    $response = get('/student?id=11');

    expect($response->viewData('selected')['record_id'])->toBe('10');
});

// ─── Token URL ───────────────────────────────────────────────────────────────

it('service user can access any student via token URL', function () {
    asService();

    $target = User::factory()->student()->create(['redcap_record_id' => '10']);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);
    $destination->shouldReceive('finalScoreFormulas')->andReturn([]);

    get(route('student.token', $target->public_token))->assertOk();
});

it('admin user can access any student via token URL', function () {
    asAdmin();

    $target = User::factory()->student()->create(['redcap_record_id' => '10']);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);
    $destination->shouldReceive('finalScoreFormulas')->andReturn([]);

    get(route('student.token', $target->public_token))->assertOk();
});

it('student can access their own token URL', function () {
    $student = asStudent('10');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);
    $destination->shouldReceive('finalScoreFormulas')->andReturn([]);

    get(route('student.token', $student->public_token))->assertOk();
});

it('student cannot access another student token URL', function () {
    asStudent('10');

    $other = User::factory()->student()->create(['redcap_record_id' => '11']);

    get(route('student.token', $other->public_token))->assertForbidden();
});

it('token URL returns 404 for an unknown token', function () {
    asService();

    get(route('student.token', '00000000-0000-0000-0000-000000000000'))->assertNotFound();
});

it('student page hides the shareable URL for students', function () {
    $student = asStudent('10');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);
    $destination->shouldReceive('finalScoreFormulas')->andReturn([]);

    get('/student')->assertOk()->assertDontSee($student->public_token);
});

// ─── Process — Service-only ──────────────────────────────────────────────────

it('forbids admins from running the process endpoint', function () {
    asAdmin();

    get('/process/1846')->assertForbidden();
});

it('forbids students from the process endpoint', function () {
    asStudent('10');

    get('/process/1846')->assertForbidden();
});

it('forbids faculty from the student page process endpoint and user management', function () {
    asFaculty('smith@example.com', 'Dr. Smith');

    get('/student')->assertForbidden();
    get('/process/1846')->assertForbidden();
    get('/admin/users')->assertForbidden();
});

// ─── Admin users — Service-only ──────────────────────────────────────────────

it('forbids admins from user management', function () {
    asAdmin();

    get('/admin/users')->assertForbidden();
});

it('forbids students from user management', function () {
    asStudent('10');

    get('/admin/users')->assertForbidden();
});

it('lets service users view user management', function () {
    asService();

    get('/admin/users')->assertOk()->assertSee('User Management');
});

it('lets service users create users', function () {
    asService();

    get('/admin/users/create')->assertOk()->assertSee('Add User');
});

it('blocks service users from deleting themselves', function () {
    $me = asService();

    delete(route('admin.users.destroy', $me))->assertForbidden();
});

// ─── Admin settings — view (Service + Admin), write (Service only) ───────────

it('lets service users view admin settings index', function () {
    asService();

    get(route('admin.settings.index'))->assertOk();
});

it('lets admin users view admin settings index', function () {
    asAdmin();

    get(route('admin.settings.index'))->assertOk();
});

it('forbids faculty from admin settings index', function () {
    asFaculty('smith@example.com', 'Dr. Smith');

    get(route('admin.settings.index'))->assertForbidden();
});

it('forbids students from admin settings index', function () {
    asStudent('10');

    get(route('admin.settings.index'))->assertForbidden();
});

it('forbids admins from creating a source project', function () {
    asAdmin();

    get(route('admin.settings.source-project.create'))->assertForbidden();
    post(route('admin.settings.project-mappings.store'), [
        'redcap_pid' => 9999,
        'redcap_token' => str_repeat('A', 32),
    ])->assertForbidden();
});

it('forbids admins from activating a project mapping', function () {
    asAdmin();

    $mapping = ProjectMapping::factory()->create();

    post(route('admin.settings.project-mappings.activate', $mapping))->assertForbidden();
});

it('forbids admins from editing or updating a project mapping', function () {
    asAdmin();

    $mapping = ProjectMapping::factory()->create();

    get(route('admin.settings.project-mappings.edit', $mapping))->assertForbidden();
    patch(route('admin.settings.project-mappings.update', $mapping), [
        'redcap_pid' => 9999,
    ])->assertForbidden();
});

it('forbids admins from destroying a project mapping', function () {
    asAdmin();

    $mapping = ProjectMapping::factory()->create();

    delete(route('admin.settings.project-mappings.destroy', $mapping))->assertForbidden();
});

it('forbids admins from importing students for a mapping', function () {
    asAdmin();

    $mapping = ProjectMapping::factory()->create();

    get(route('admin.settings.project-mappings.import-students', $mapping))->assertForbidden();
});

it('forbids faculty from any admin settings write action', function () {
    asFaculty('smith@example.com', 'Dr. Smith');
    $mapping = ProjectMapping::factory()->create();

    get(route('admin.settings.source-project.create'))->assertForbidden();
    post(route('admin.settings.project-mappings.activate', $mapping))->assertForbidden();
    delete(route('admin.settings.project-mappings.destroy', $mapping))->assertForbidden();
});
