<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function asService(?string $email = null): User
{
    $user = User::factory()->service()->create($email ? ['email' => strtolower($email)] : []);
    test()->actingAs($user);

    return $user;
}

function asAdmin(?string $email = null): User
{
    $user = User::factory()->admin()->create($email ? ['email' => strtolower($email)] : []);
    test()->actingAs($user);

    return $user;
}

function asStudent(?string $redcapRecordId = null): User
{
    $user = User::factory()->student()->create(
        $redcapRecordId ? ['redcap_record_id' => $redcapRecordId] : []
    );
    test()->actingAs($user);

    return $user;
}

function asFaculty(?string $email = null, ?string $name = null): User
{
    $user = User::factory()->faculty()->create(array_filter([
        'email' => $email ? strtolower($email) : null,
        'name' => $name,
    ]));
    test()->actingAs($user);

    return $user;
}
