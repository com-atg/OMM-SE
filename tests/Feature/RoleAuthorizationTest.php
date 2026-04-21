<?php

use App\Models\User;
use App\Services\RedcapDestinationService;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\mock;

beforeEach(function () {
    Cache::flush();
});

// ─── Dashboard ───────────────────────────────────────────────────────────────

it('redirects students from dashboard to their scholar page', function () {
    asStudent('10');

    get('/')->assertRedirect(route('scholar'));
});

it('lets service users view the dashboard', function () {
    asService();
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([]);

    get('/')->assertOk();
});

it('lets admin users view the dashboard', function () {
    asAdmin();
    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([]);

    get('/')->assertOk();
});

// ─── Scholar page — student scoping ──────────────────────────────────────────

it('shows a student only their own record and locks the picker', function () {
    asStudent('10');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
        ['record_id' => '11', 'first_name' => 'Ava', 'last_name' => 'Adams'],
    ]);

    $response = get('/scholar');

    $response->assertOk();
    expect($response->viewData('lock_selection'))->toBeTrue()
        ->and($response->viewData('roster'))->toBe([])
        ->and($response->viewData('selected')['record_id'])->toBe('10');
});

it('returns 404 when a student has no matching record', function () {
    asStudent('999');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);

    get('/scholar')->assertNotFound();
});

it('ignores ?id query string for students and forces their own record', function () {
    asStudent('10');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
        ['record_id' => '11', 'first_name' => 'Ava', 'last_name' => 'Adams'],
    ]);

    $response = get('/scholar?id=11');

    expect($response->viewData('selected')['record_id'])->toBe('10');
});

// ─── Token URL ───────────────────────────────────────────────────────────────

it('service user can access any scholar via token URL', function () {
    asService();

    $target = User::factory()->student()->create(['redcap_record_id' => '10']);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);

    get(route('scholar.token', $target->public_token))->assertOk();
});

it('admin user can access any scholar via token URL', function () {
    asAdmin();

    $target = User::factory()->student()->create(['redcap_record_id' => '10']);

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);

    get(route('scholar.token', $target->public_token))->assertOk();
});

it('student can access their own token URL', function () {
    $student = asStudent('10');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);

    get(route('scholar.token', $student->public_token))->assertOk();
});

it('student cannot access another student token URL', function () {
    asStudent('10');

    $other = User::factory()->student()->create(['redcap_record_id' => '11']);

    get(route('scholar.token', $other->public_token))->assertForbidden();
});

it('token URL returns 404 for an unknown token', function () {
    asService();

    get(route('scholar.token', '00000000-0000-0000-0000-000000000000'))->assertNotFound();
});

it('scholar page includes a shareable URL for students', function () {
    $student = asStudent('10');

    $destination = mock(RedcapDestinationService::class);
    $destination->shouldReceive('getAllScholarRecords')->once()->andReturn([
        ['record_id' => '10', 'first_name' => 'Cat', 'last_name' => 'Chin'],
    ]);

    get('/scholar')->assertOk()->assertSee($student->public_token);
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

it('blocks service users from deleting themselves', function () {
    $me = asService();

    delete(route('admin.users.destroy', $me))->assertForbidden();
});
