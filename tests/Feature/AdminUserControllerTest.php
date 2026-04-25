<?php

use App\Enums\Role;
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

// ── Index ─────────────────────────────────────────────────────────────────────

it('lists all users with their role badges', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);
    User::factory()->student()->create(['email' => 'student@example.com']);

    get('/admin/users')
        ->assertOk()
        ->assertSee('admin@example.com')
        ->assertSee('student@example.com');
});

it('renders user actions as a dropdown with edit, impersonate, and delete options', function () {
    User::factory()->student()->create(['email' => 'actions@example.com']);

    get('/admin/users')
        ->assertOk()
        ->assertSee('User actions', false)
        ->assertSeeInOrder(['Edit', 'Impersonate', 'Delete'], false);
});

it('shows deleted users in the trashed section on the index page', function () {
    $user = User::factory()->student()->create(['email' => 'deleted@example.com']);
    $user->delete();

    get('/admin/users')
        ->assertOk()
        ->assertSee('Deleted users')
        ->assertSee('deleted@example.com');
});

// ── Create / Store ────────────────────────────────────────────────────────────

it('renders the create user form', function () {
    get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('Add User');
});

it('creates a new user and redirects to the index', function () {
    post(route('admin.users.store'), [
        'email' => 'NEW@Example.COM',
        'name' => 'New Person',
        'role' => 'admin',
    ])->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(Role::Admin)
        ->and($user->name)->toBe('New Person');
});

it('creates a faculty user', function () {
    post(route('admin.users.store'), [
        'email' => 'faculty@example.com',
        'name' => 'Faculty Person',
        'role' => 'faculty',
    ])->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'faculty@example.com')->first()->role)->toBe(Role::Faculty);
});

it('rejects a duplicate email on store', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    post(route('admin.users.store'), [
        'email' => 'existing@example.com',
        'name' => 'Dup',
        'role' => 'student',
    ])->assertSessionHasErrors('email');
});

it('rejects an invalid role on store', function () {
    post(route('admin.users.store'), [
        'email' => 'x@example.com',
        'name' => 'X',
        'role' => 'superuser',
    ])->assertSessionHasErrors('role');
});

// ── Edit / Update ─────────────────────────────────────────────────────────────

it('renders the edit form for a user', function () {
    $user = User::factory()->student()->create(['email' => 'student@example.com']);

    get(route('admin.users.edit', $user))
        ->assertOk()
        ->assertSee('student@example.com')
        ->assertSee('Student');
});

it('updates a user role and redcap_record_id', function () {
    $user = User::factory()->student()->create();

    from(route('admin.users.edit', $user))
        ->patch(route('admin.users.update', $user), [
            'name' => 'Updated Name',
            'role' => 'admin',
            'redcap_record_id' => '42',
        ])
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();
    expect($user->role)->toBe(Role::Admin)
        ->and($user->name)->toBe('Updated Name')
        ->and($user->redcap_record_id)->toBe('42');
});

it('rejects an invalid role', function () {
    $user = User::factory()->student()->create();

    patch(route('admin.users.update', $user), [
        'name' => 'X',
        'role' => 'supreme-ruler',
    ])->assertSessionHasErrors('role');
});

it('clears redcap_record_id when left blank', function () {
    $user = User::factory()->student()->create(['redcap_record_id' => '99']);

    patch(route('admin.users.update', $user), [
        'name' => $user->name,
        'role' => 'student',
        'redcap_record_id' => '',
    ]);

    expect($user->fresh()->redcap_record_id)->toBeNull();
});

// ── Delete / Restore ──────────────────────────────────────────────────────────

it('soft-deletes a user and they no longer appear in the active list', function () {
    $user = User::factory()->student()->create();

    delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));

    expect(User::find($user->id))->toBeNull()
        ->and(User::onlyTrashed()->find($user->id))->not->toBeNull();
});

it('restores a soft-deleted user', function () {
    $user = User::factory()->student()->create();
    $user->delete();

    post(route('admin.users.restore', $user->id))->assertRedirect(route('admin.users.index'));

    expect(User::find($user->id))->not->toBeNull();
});

// ── Impersonation ─────────────────────────────────────────────────────────────

it('impersonates a student and redirects to dashboard', function () {
    $student = User::factory()->student()->create();

    post(route('admin.users.impersonate', $student))
        ->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($student->id)
        ->and(session('impersonating_original_id'))->not->toBeNull();
});

it('stops impersonation and restores original user', function () {
    $service = auth()->user();
    $student = User::factory()->student()->create();

    post(route('admin.users.impersonate', $student));
    expect(auth()->id())->toBe($student->id);

    post(route('users.impersonate.stop'))
        ->assertRedirect(route('admin.users.index'));

    expect(auth()->id())->toBe($service->id)
        ->and(session()->has('impersonating_original_id'))->toBeFalse();
});

it('cannot impersonate yourself', function () {
    $self = auth()->user();

    post(route('admin.users.impersonate', $self))->assertForbidden();
});

it('cannot impersonate a service account', function () {
    $other = User::factory()->service()->create();

    post(route('admin.users.impersonate', $other))->assertForbidden();
});

it('cannot impersonate while already impersonating', function () {
    $student1 = User::factory()->student()->create();
    $student2 = User::factory()->student()->create();

    post(route('admin.users.impersonate', $student1));

    post(route('admin.users.impersonate', $student2))->assertForbidden();
});

// ── REDCap Import ─────────────────────────────────────────────────────────────

it('imports students from REDCap as student users', function () {
    $mock = Mockery::mock(RedcapDestinationService::class);
    $mock->shouldReceive('getAllStudentRecords')->once()->andReturn([
        ['record_id' => '1', 'first_name' => 'Alice', 'last_name' => 'Smith', 'goes_by' => '', 'email' => 'alice@example.com'],
        ['record_id' => '2', 'first_name' => 'Bob', 'last_name' => 'Jones', 'goes_by' => 'Bobby', 'email' => 'bob@example.com'],
    ]);
    app()->instance(RedcapDestinationService::class, $mock);

    post(route('admin.users.import'))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status', fn ($v) => str_contains($v, '2 created'));

    expect(User::where('email', 'alice@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'bob@example.com')->value('name'))->toBe('Bobby Jones');
});

it('skips students that already have a user account during import', function () {
    User::factory()->student()->create(['email' => 'existing@example.com']);

    $mock = Mockery::mock(RedcapDestinationService::class);
    $mock->shouldReceive('getAllStudentRecords')->once()->andReturn([
        ['record_id' => '5', 'first_name' => 'Existing', 'last_name' => 'User', 'goes_by' => '', 'email' => 'existing@example.com'],
        ['record_id' => '6', 'first_name' => 'New', 'last_name' => 'Person', 'goes_by' => '', 'email' => 'new@example.com'],
    ]);
    app()->instance(RedcapDestinationService::class, $mock);

    post(route('admin.users.import'))
        ->assertSessionHas('status', fn ($v) => str_contains($v, '1 created') && str_contains($v, '1 already existed'));
});

it('skips students without an email during import', function () {
    $mock = Mockery::mock(RedcapDestinationService::class);
    $mock->shouldReceive('getAllStudentRecords')->once()->andReturn([
        ['record_id' => '7', 'first_name' => 'No', 'last_name' => 'Email', 'goes_by' => '', 'email' => ''],
    ]);
    app()->instance(RedcapDestinationService::class, $mock);

    post(route('admin.users.import'))
        ->assertSessionHas('status', fn ($v) => str_contains($v, '0 created'));

    expect(User::where('name', 'No Email')->exists())->toBeFalse();
});
