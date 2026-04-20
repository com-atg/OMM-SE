<?php

use Illuminate\Support\Facades\Route;
use Omm\RedcapAdvancedLink\Middleware\VerifyRedcapAdvancedLink;
use Omm\RedcapAdvancedLink\RedcapAdvancedLinkService;

use function Pest\Laravel\mock;

beforeEach(function () {
    config([
        'redcap-advanced-link.enabled' => true,
        'redcap-advanced-link.authorized_roles' => ['authorized_role'],
        'redcap-advanced-link.session_key' => 'redcap.advanced_link.user',
    ]);

    Route::middleware(['web', VerifyRedcapAdvancedLink::class])
        ->get('/advanced-link-protected', fn () => response('ok'));

    Route::middleware([VerifyRedcapAdvancedLink::class])
        ->post('/redcap/launch', fn () => redirect('/advanced-link-protected'));
});

afterEach(function () {
    config(['redcap-advanced-link.enabled' => false]);
});

test('stores advanced link user in session when authkey is authorized', function () {
    $service = mock(RedcapAdvancedLinkService::class);
    $service->shouldReceive('authorize')
        ->once()
        ->with('valid-authkey', ['authorized_role'])
        ->andReturn([
            'username' => 'mmatalia',
            'project_id' => '1846',
            'unique_role_name' => 'authorized_role',
        ]);

    $this->post('/redcap/launch', ['authkey' => 'valid-authkey'])
        ->assertRedirect('/advanced-link-protected');

    expect(session('redcap.advanced_link.user.username'))->toBe('mmatalia');
});

test('allows protected requests with an authorized advanced link session', function () {
    $service = mock(RedcapAdvancedLinkService::class);
    $service->shouldReceive('isRoleAuthorized')
        ->once()
        ->with('authorized_role', ['authorized_role'])
        ->andReturnTrue();

    $this->withSession([
        'redcap.advanced_link.user' => [
            'username' => 'mmatalia',
            'project_id' => '1846',
            'unique_role_name' => 'authorized_role',
        ],
    ])->get('/advanced-link-protected')->assertSuccessful();
});

test('rejects protected requests without authkey or session', function () {
    $this->get('/advanced-link-protected')
        ->assertForbidden()
        ->assertSee('You are not authorized to access this page.');
});

test('rejects advanced link requests when authkey does not authorize a role', function () {
    $service = mock(RedcapAdvancedLinkService::class);
    $service->shouldReceive('authorize')
        ->once()
        ->with('invalid-authkey', ['authorized_role'])
        ->andReturnNull();

    $this->post('/redcap/launch', ['authkey' => 'invalid-authkey'])
        ->assertForbidden()
        ->assertSee('You are not authorized to access this page.');
});

test('bypasses advanced link checks when middleware is disabled', function () {
    config(['redcap-advanced-link.enabled' => false]);

    $this->get('/advanced-link-protected')->assertSuccessful();
});
