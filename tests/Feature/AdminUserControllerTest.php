<?php

use App\Enums\Role;
use App\Models\User;

use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

beforeEach(function () {
    asService();
});

it('lists all users with their role badges', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);
    User::factory()->student()->create(['email' => 'student@example.com']);

    get('/admin/users')
        ->assertOk()
        ->assertSee('admin@example.com')
        ->assertSee('student@example.com');
});

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
