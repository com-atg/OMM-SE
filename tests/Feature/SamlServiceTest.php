<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\SamlService;

beforeEach(function () {
    config([
        'saml.service_users' => ['super@example.com'],
        'saml.admin_users' => ['boss@example.com'],
    ]);
});

it('resolves role from the service allowlist', function () {
    expect(app(SamlService::class)->resolveRole('SUPER@example.com'))->toBe(Role::Service);
});

it('resolves role from the admin allowlist', function () {
    expect(app(SamlService::class)->resolveRole('boss@example.com'))->toBe(Role::Admin);
});

it('defaults unknown emails to Student', function () {
    expect(app(SamlService::class)->resolveRole('random@example.com'))->toBe(Role::Student);
});

it('creates a user with the resolved role and lowercases the email', function () {
    app(SamlService::class)->loginFromAssertion('Boss@Example.COM', 'The Boss', 'nameid-xyz');

    $user = User::where('email', 'boss@example.com')->firstOrFail();

    expect($user->role)->toBe(Role::Admin)
        ->and($user->name)->toBe('The Boss')
        ->and($user->okta_nameid)->toBe('nameid-xyz')
        ->and($user->last_login_at)->not->toBeNull()
        ->and(auth()->id())->toBe($user->id);
});

it('updates role on subsequent logins if allowlist changes', function () {
    User::factory()->student()->create(['email' => 'boss@example.com']);

    app(SamlService::class)->loginFromAssertion('boss@example.com', 'The Boss', null);

    expect(User::where('email', 'boss@example.com')->first()->role)->toBe(Role::Admin);
});

it('rejects empty emails', function () {
    app(SamlService::class)->loginFromAssertion('', null, null);
})->throws(RuntimeException::class, 'SAML assertion did not include an email address.');
