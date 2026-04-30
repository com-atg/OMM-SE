<?php

use App\Enums\Role;
use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\RedcapDestinationService;

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
    ProjectMapping::factory()->active()->create(['redcap_pid' => 1845]);
    ProjectMapping::factory()->create(['redcap_pid' => 1846])->delete();

    asAdmin();

    get(route('admin.settings.index'))
        ->assertOk()
        ->assertDontSee('Configure source project')
        ->assertDontSee('Edit project mapping')
        ->assertDontSee('Delete project mapping')
        ->assertDontSee('Restore')
        ->assertSee('Re-process');
});

it('forbids admins from mutating project mappings', function () {
    $projectMapping = ProjectMapping::factory()->create();

    asAdmin();

    post(route('admin.settings.project-mappings.store'), [
        'redcap_pid' => 1846,
        'redcap_token' => '78A933EF74A3B5B1DBAB2E313942CDA6',
    ])->assertForbidden();

    get(route('admin.settings.project-mappings.edit', $projectMapping))->assertForbidden();

    patch(route('admin.settings.project-mappings.update', $projectMapping), [
        'redcap_pid' => 1847,
        'redcap_token' => 'NEWTOKEN',
    ])->assertForbidden();

    delete(route('admin.settings.project-mappings.destroy', $projectMapping))->assertForbidden();
});

it('shows the active source project on the settings page', function () {
    ProjectMapping::factory()->active()->create(['redcap_pid' => 1846]);

    get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Source Project (PID 1846)');
});

it('does not use a soft-deleted mapping as the active project', function () {
    ProjectMapping::factory()->active()->create(['redcap_pid' => 1846])->delete();
    ProjectMapping::factory()->create(['redcap_pid' => 1845]);

    get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Source Project (PID 1845)');
});

it('creates an active project mapping and deactivates any prior active mapping', function () {
    ProjectMapping::factory()->active()->create(['redcap_pid' => 1500]);

    $response = post(route('admin.settings.project-mappings.store'), [
        'redcap_pid' => 1846,
        'redcap_token' => '78A933EF74A3B5B1DBAB2E313942CDA6',
    ]);

    $projectMapping = ProjectMapping::where('redcap_pid', 1846)->first();
    expect($projectMapping)->not->toBeNull();

    $response->assertRedirect(route('admin.settings.project-mappings.import-students', $projectMapping));

    expect($projectMapping->redcap_pid)->toBe(1846)
        ->and($projectMapping->redcap_token)->toBe('78A933EF74A3B5B1DBAB2E313942CDA6')
        ->and($projectMapping->is_active)->toBeTrue();

    expect(ProjectMapping::where('redcap_pid', 1500)->first()->is_active)->toBeFalse();
});

it('renders the source project create page with PID and token inputs', function () {
    get(route('admin.settings.source-project.create'))
        ->assertOk()
        ->assertSee('wire:model="redcap_pid"', false)
        ->assertSee('id="redcap_pid"', false)
        ->assertSee('wire:model="redcap_token"', false);
});

it('validates project mapping uniqueness among active records', function () {
    ProjectMapping::factory()->create(['redcap_pid' => 1846]);

    post(route('admin.settings.project-mappings.store'), [
        'redcap_pid' => 1846,
        'redcap_token' => '78A933EF74A3B5B1DBAB2E313942CDA6',
    ])->assertSessionHasErrors(['redcap_pid']);
});

it('updates a project mapping while preserving the token when blank', function () {
    $projectMapping = ProjectMapping::factory()->create([
        'redcap_token' => 'OLDTOKEN',
    ]);

    from(route('admin.settings.project-mappings.edit', $projectMapping))
        ->patch(route('admin.settings.project-mappings.update', $projectMapping), [
            'redcap_pid' => 1847,
            'redcap_token' => '',
        ])
        ->assertRedirect(route('admin.settings.index'));

    $projectMapping->refresh();

    expect($projectMapping->redcap_pid)->toBe(1847)
        ->and($projectMapping->redcap_token)->toBe('OLDTOKEN');
});

it('can replace the project mapping token', function () {
    $projectMapping = ProjectMapping::factory()->create([
        'redcap_token' => 'OLDTOKEN',
    ]);

    patch(route('admin.settings.project-mappings.update', $projectMapping), [
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
    ProjectMapping::factory()->create(['redcap_pid' => 1846])->delete();

    get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Deleted project mappings')
        ->assertSee('1846');
});

it('activates a project mapping and deactivates any others', function () {
    $first = ProjectMapping::factory()->active()->create();
    $second = ProjectMapping::factory()->create();

    post(route('admin.settings.project-mappings.activate', $second))
        ->assertRedirect(route('admin.settings.index'));

    expect($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_active)->toBeTrue();
});

it('forbids non-service users from viewing the source project create page', function () {
    asAdmin();

    get(route('admin.settings.source-project.create'))->assertForbidden();
});

it('imports scholars after creating a source project, mirroring cohort fields onto users', function () {
    $destination = $this->mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllStudentRecords')
        ->andReturn([
            ['record_id' => '10', 'datatelid' => 'D10', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'goes_by' => '', 'email' => 'ada@example.com', 'cohort_start_term' => 'Fall', 'cohort_start_year' => '2026', 'batch' => '12', 'is_active' => '1'],
            ['record_id' => '11', 'datatelid' => 'D11', 'first_name' => 'Grace', 'last_name' => 'Hopper', 'goes_by' => 'Amazing Grace', 'email' => 'grace@example.com', 'batch' => '11', 'is_active' => '0'],
            ['record_id' => '12', 'datatelid' => 'D12', 'first_name' => 'NoEmail', 'last_name' => 'Person', 'goes_by' => '', 'email' => ''],
        ]);

    User::factory()->create([
        'email' => 'grace@example.com',
        'role' => Role::Student,
        'batch' => null,
        'is_active' => true,
    ]);

    from(route('admin.settings.index'))
        ->post(route('admin.settings.project-mappings.store'), [
            'redcap_pid' => 9999,
            'redcap_token' => 'TOKEN1234567890',
        ])
        ->assertRedirect();

    $mapping = ProjectMapping::where('redcap_pid', 9999)->firstOrFail();

    get(route('admin.settings.project-mappings.import-students', $mapping))
        ->assertOk()
        ->assertSee('Scholar Users Imported')
        ->assertSee('ada@example.com')
        ->assertSee('Amazing Grace Hopper')
        ->assertSee('Refreshed Existing Users')
        ->assertSee('Records Missing Email');

    $ada = User::where('email', 'ada@example.com')->first();
    expect($ada)->not->toBeNull()
        ->and($ada->role)->toBe(Role::Student)
        ->and($ada->name)->toBe('Ada Lovelace')
        ->and($ada->redcap_record_id)->toBe('10')
        ->and($ada->cohort_start_term)->toBe('Fall')
        ->and($ada->cohort_start_year)->toBe(2026)
        ->and($ada->batch)->toBe('12')
        ->and($ada->is_active)->toBeTrue();

    $grace = User::where('email', 'grace@example.com')->firstOrFail();
    expect($grace->name)->toBe('Amazing Grace Hopper')
        ->and($grace->batch)->toBe('11')
        ->and($grace->is_active)->toBeFalse();
});

it('forbids non-service users from running scholar import', function () {
    $mapping = ProjectMapping::factory()->create(['redcap_pid' => 1900]);

    asAdmin();

    get(route('admin.settings.project-mappings.import-students', $mapping))->assertForbidden();
});
