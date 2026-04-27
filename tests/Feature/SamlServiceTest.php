<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\SamlService;

it('creates a new user as Student and lowercases the email', function () {
    app(SamlService::class)->loginFromAssertion('Boss@Example.COM', 'The Boss', 'nameid-xyz');

    $user = User::where('email', 'boss@example.com')->firstOrFail();

    expect($user->role)->toBe(Role::Student)
        ->and($user->name)->toBe('The Boss')
        ->and($user->okta_nameid)->toBe('nameid-xyz')
        ->and($user->last_login_at)->not->toBeNull()
        ->and(auth()->id())->toBe($user->id);
});

it('preserves the database role for an existing admin on subsequent logins', function () {
    User::factory()->create(['email' => 'boss@example.com', 'role' => Role::Admin]);

    app(SamlService::class)->loginFromAssertion('boss@example.com', 'The Boss', null);

    expect(User::where('email', 'boss@example.com')->first()->role)->toBe(Role::Admin);
});

it('preserves the database role for an existing service user on subsequent logins', function () {
    User::factory()->create(['email' => 'super@example.com', 'role' => Role::Service]);

    $user = app(SamlService::class)->loginFromAssertion('super@example.com', 'Super User', null);

    expect($user->role)->toBe(Role::Service)
        ->and($user->canManageUsers())->toBeTrue();
});

it('preserves an existing faculty role on subsequent logins', function () {
    User::factory()->faculty()->create(['email' => 'faculty@example.com']);

    $user = app(SamlService::class)->loginFromAssertion('faculty@example.com', 'Faculty User', null);

    expect($user->role)->toBe(Role::Faculty)
        ->and($user->isFaculty())->toBeTrue();
});

it('updates name, okta_nameid, and last_login_at without touching role', function () {
    $existing = User::factory()->create([
        'email' => 'someone@example.com',
        'role' => Role::Admin,
        'name' => 'Old Name',
        'okta_nameid' => 'old-nameid',
    ]);

    app(SamlService::class)->loginFromAssertion('someone@example.com', 'New Name', 'new-nameid');

    $existing->refresh();

    expect($existing->role)->toBe(Role::Admin)
        ->and($existing->name)->toBe('New Name')
        ->and($existing->okta_nameid)->toBe('new-nameid')
        ->and($existing->last_login_at)->not->toBeNull();
});

it('rejects empty emails', function () {
    app(SamlService::class)->loginFromAssertion('', null, null);
})->throws(RuntimeException::class, 'SAML assertion did not include an email address.');

it('allows relative SAML redirect targets', function () {
    expect(app(SamlService::class)->safeRedirectTarget('/student?id=10'))->toBe('/student?id=10');
});

it('allows same-origin SAML redirect targets', function () {
    $target = url('/student?id=10');

    expect(app(SamlService::class)->safeRedirectTarget($target))->toBe($target);
});

it('rejects off-site SAML redirect targets', function () {
    config(['app.url' => 'https://example.test']);

    expect(app(SamlService::class)->safeRedirectTarget('https://evil.example/phish'))
        ->toBe(route('dashboard', absolute: false));
});

it('rejects protocol-relative SAML redirect targets', function () {
    expect(app(SamlService::class)->safeRedirectTarget('//evil.example/phish'))
        ->toBe(route('dashboard', absolute: false));
});
