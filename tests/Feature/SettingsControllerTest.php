<?php

use App\Enums\Role;
use App\Jobs\ProcessSourceProjectJob;
use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\RedcapDestinationService;
use Illuminate\Support\Facades\Bus;

use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(function () {
    asService();
});

it('shows settings in the dashboard nav for service and admin users', function () {
    get(route('dashboard'))
        ->assertOk()
        ->assertSee('Settings');

    asAdmin();

    get(route('dashboard'))
        ->assertOk()
        ->assertSee('Settings');

    asStudent();

    get(route('dashboard'))
        ->assertRedirect(route('student'));
});

it('allows admins to view settings', function () {
    asAdmin();

    get(route('admin.settings.index'))->assertOk();
});

it('restricts settings from students', function () {
    asStudent();

    get(route('admin.settings.index'))->assertForbidden();
});

it('hides settings write controls from admins', function () {
    ProjectMapping::factory()->create([
        'academic_year' => '2024-2025',
        'graduation_year' => 2027,
        'redcap_pid' => 1845,
    ]);
    ProjectMapping::factory()->create([
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
        'redcap_pid' => 1846,
    ])->delete();

    asAdmin();

    get(route('admin.settings.index'))
        ->assertOk()
        ->assertDontSee('Add a New Academic Year')
        ->assertDontSee('Edit project mapping')
        ->assertDontSee('Delete project mapping')
        ->assertDontSee('Restore')
        ->assertSee('Re-process');
});

it('forbids admins from mutating project mappings', function () {
    $projectMapping = ProjectMapping::factory()->create();

    asAdmin();

    post(route('admin.settings.project-mappings.store'), [
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
        'redcap_pid' => 1846,
        'redcap_token' => '78A933EF74A3B5B1DBAB2E313942CDA6',
    ])->assertForbidden();

    get(route('admin.settings.project-mappings.edit', $projectMapping))->assertForbidden();

    patch(route('admin.settings.project-mappings.update', $projectMapping), [
        'academic_year' => '2026-2027',
        'graduation_year' => 2029,
        'redcap_pid' => 1847,
        'redcap_token' => 'NEWTOKEN',
    ])->assertForbidden();

    delete(route('admin.settings.project-mappings.destroy', $projectMapping))->assertForbidden();
});

it('shows current project from the largest active graduation year', function () {
    ProjectMapping::factory()->create([
        'academic_year' => '2024-2025',
        'graduation_year' => 2027,
    ]);
    ProjectMapping::factory()->create([
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
    ]);

    get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Academic Year 2025-2026 (Class of 2028)');
});

it('does not use a soft-deleted mapping as the current project', function () {
    ProjectMapping::factory()->create([
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
    ])->delete();
    ProjectMapping::factory()->create([
        'academic_year' => '2024-2025',
        'graduation_year' => 2027,
    ]);

    get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Academic Year 2024-2025 (Class of 2027)');
});

it('creates a project mapping', function () {
    $response = post(route('admin.settings.project-mappings.store'), [
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
        'redcap_pid' => 1846,
        'redcap_token' => '78A933EF74A3B5B1DBAB2E313942CDA6',
    ]);

    $projectMapping = ProjectMapping::first();
    expect($projectMapping)->not->toBeNull();

    $response->assertRedirect(route('admin.settings.project-mappings.import-students', $projectMapping));

    expect($projectMapping)->not->toBeNull()
        ->and($projectMapping->academic_year)->toBe('2025-2026')
        ->and($projectMapping->graduation_year)->toBe(2028)
        ->and($projectMapping->redcap_pid)->toBe(1846)
        ->and($projectMapping->redcap_token)->toBe('78A933EF74A3B5B1DBAB2E313942CDA6');
});

it('renders graduating year and pid as text inputs', function () {
    get(route('admin.settings.new-academic-year'))
        ->assertOk()
        ->assertSee('name="graduation_year"', false)
        ->assertSee('id="graduation_year"', false)
        ->assertSee('type="text"', false)
        ->assertSee('name="redcap_pid"', false)
        ->assertSee('id="redcap_pid"', false);
});

it('validates project mapping uniqueness among active records', function () {
    ProjectMapping::factory()->create([
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
        'redcap_pid' => 1846,
    ]);

    post(route('admin.settings.project-mappings.store'), [
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
        'redcap_pid' => 1846,
        'redcap_token' => '78A933EF74A3B5B1DBAB2E313942CDA6',
    ])->assertSessionHasErrors(['academic_year', 'graduation_year', 'redcap_pid']);
});

it('updates a project mapping while preserving the token when blank', function () {
    $projectMapping = ProjectMapping::factory()->create([
        'redcap_token' => 'OLDTOKEN',
    ]);

    from(route('admin.settings.project-mappings.edit', $projectMapping))
        ->patch(route('admin.settings.project-mappings.update', $projectMapping), [
            'academic_year' => '2026-2027',
            'graduation_year' => 2029,
            'redcap_pid' => 1847,
            'redcap_token' => '',
        ])
        ->assertRedirect(route('admin.settings.index'));

    $projectMapping->refresh();

    expect($projectMapping->academic_year)->toBe('2026-2027')
        ->and($projectMapping->graduation_year)->toBe(2029)
        ->and($projectMapping->redcap_pid)->toBe(1847)
        ->and($projectMapping->redcap_token)->toBe('OLDTOKEN');
});

it('can replace the project mapping token', function () {
    $projectMapping = ProjectMapping::factory()->create([
        'redcap_token' => 'OLDTOKEN',
    ]);

    patch(route('admin.settings.project-mappings.update', $projectMapping), [
        'academic_year' => $projectMapping->academic_year,
        'graduation_year' => $projectMapping->graduation_year,
        'redcap_pid' => $projectMapping->redcap_pid,
        'redcap_token' => 'NEWTOKEN',
    ]);

    expect($projectMapping->fresh()->redcap_token)->toBe('NEWTOKEN');
});

it('soft deletes and restores a project mapping', function () {
    $projectMapping = ProjectMapping::factory()->create();

    delete(route('admin.settings.project-mappings.destroy', $projectMapping))
        ->assertRedirect(route('admin.settings.index'));

    expect(ProjectMapping::find($projectMapping->id))->toBeNull()
        ->and(ProjectMapping::onlyTrashed()->find($projectMapping->id))->not->toBeNull();

    post(route('admin.settings.project-mappings.restore', $projectMapping->id))
        ->assertRedirect(route('admin.settings.index'));

    expect(ProjectMapping::find($projectMapping->id))->not->toBeNull();
});

it('shows deleted project mappings in the restore section', function () {
    ProjectMapping::factory()->create([
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
        'redcap_pid' => 1846,
    ])->delete();

    get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Deleted project mappings')
        ->assertSee('2025-2026')
        ->assertSee('1846');
});

it('dispatches a re-process job for a mapped project', function () {
    Bus::fake();
    $projectMapping = ProjectMapping::factory()->create([
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
        'redcap_pid' => 1846,
        'redcap_token' => '78A933EF74A3B5B1DBAB2E313942CDA6',
    ]);

    post(route('admin.settings.project-mappings.process', $projectMapping))
        ->assertOk()
        ->assertSee('Processing Source Project');

    Bus::assertDispatched(ProcessSourceProjectJob::class, function (ProcessSourceProjectJob $job): bool {
        return $job->pid === '1846'
            && $job->sourceToken === '78A933EF74A3B5B1DBAB2E313942CDA6';
    });
});

it('shows the new academic year prep page with the next graduating year filled into the SQL', function () {
    ProjectMapping::factory()->create([
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
        'redcap_pid' => 1846,
    ]);

    get(route('admin.settings.new-academic-year'))
        ->assertOk()
        ->assertSee('Prepare for a New Academic Year')
        ->assertSee('OMM ACE List')
        ->assertSee('PID')
        ->assertSee('2115')
        ->assertSee("e.value='2029'");
});

it('falls back to graduating year 2028 when no project mappings exist', function () {
    get(route('admin.settings.new-academic-year'))
        ->assertOk()
        ->assertSee("e.value='2028'");
});

it('forbids non-service users from viewing the new academic year prep page', function () {
    asAdmin();

    get(route('admin.settings.new-academic-year'))->assertForbidden();
});

it('redirects to scholar import after creating a project mapping and imports filtered students', function () {
    $destination = $this->mock(RedcapDestinationService::class);
    $destination->shouldReceive('getStudentsByGraduationYear')
        ->once()
        ->with(2030)
        ->andReturn([
            ['record_id' => '10', 'datatelid' => 'D10', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'goes_by' => '', 'email' => 'ada@example.com', 'year' => '2030'],
            ['record_id' => '11', 'datatelid' => 'D11', 'first_name' => 'Grace', 'last_name' => 'Hopper', 'goes_by' => 'Amazing Grace', 'email' => 'grace@example.com', 'year' => '2030'],
            ['record_id' => '12', 'datatelid' => 'D12', 'first_name' => 'NoEmail', 'last_name' => 'Person', 'goes_by' => '', 'email' => '', 'year' => '2030'],
        ]);

    User::factory()->create(['email' => 'grace@example.com', 'role' => Role::Student]);

    from(route('admin.settings.index'))
        ->post(route('admin.settings.project-mappings.store'), [
            'academic_year' => '2027-2028',
            'graduation_year' => 2030,
            'redcap_pid' => 9999,
            'redcap_token' => 'TOKEN1234567890',
        ])
        ->assertRedirect();

    $mapping = ProjectMapping::where('redcap_pid', 9999)->firstOrFail();

    get(route('admin.settings.project-mappings.import-students', $mapping))
        ->assertOk()
        ->assertSee('Scholar Users Imported')
        ->assertSee('OMM ACE List')
        ->assertSee('ada@example.com')
        ->assertSee('Amazing Grace Hopper')
        ->assertSee('Records Missing Email');

    expect(User::where('email', 'ada@example.com')->first())
        ->not->toBeNull()
        ->role->toBe(Role::Student);
    expect(User::where('email', 'ada@example.com')->first()->name)->toBe('Ada Lovelace');
    expect(User::where('email', 'ada@example.com')->first()->redcap_record_id)->toBe('10');
    expect(User::where('email', 'grace@example.com')->count())->toBe(1);
});

it('forbids non-service users from running scholar import', function () {
    $mapping = ProjectMapping::factory()->create([
        'academic_year' => '2026-2027',
        'graduation_year' => 2029,
        'redcap_pid' => 1900,
    ]);

    asAdmin();

    get(route('admin.settings.project-mappings.import-students', $mapping))->assertForbidden();
});

it('allows admins to re-process a mapped project', function () {
    Bus::fake();
    $projectMapping = ProjectMapping::factory()->create([
        'academic_year' => '2025-2026',
        'graduation_year' => 2028,
        'redcap_pid' => 1846,
        'redcap_token' => '78A933EF74A3B5B1DBAB2E313942CDA6',
    ]);

    asAdmin();

    post(route('admin.settings.project-mappings.process', $projectMapping))
        ->assertOk()
        ->assertSee('Processing Source Project');

    Bus::assertDispatched(ProcessSourceProjectJob::class, function (ProcessSourceProjectJob $job): bool {
        return $job->pid === '1846'
            && $job->sourceToken === '78A933EF74A3B5B1DBAB2E313942CDA6';
    });
});
