<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    asService();
});

it('imports users from an uploaded csv file', function () {
    $file = UploadedFile::fake()->createWithContent(
        'users.csv',
        "name,email,role\nJane Faculty,JANE.FACULTY@example.com,faculty\nAlex Admin,alex.admin@example.com,admin\n",
    );

    Livewire::test('admin.csv-user-import')
        ->set('csvFile', $file)
        ->assertSet('rows.0.name', 'Jane Faculty')
        ->assertSet('rows.0.email', 'jane.faculty@example.com')
        ->assertSet('rows.0.role', 'faculty')
        ->assertSet('cellErrors', [])
        ->call('import')
        ->assertSet('done', true)
        ->assertSet('imported', 2)
        ->assertSet('skipped', 0);

    expect(User::where('email', 'jane.faculty@example.com')->first()?->role)->toBe(Role::Faculty)
        ->and(User::where('email', 'alex.admin@example.com')->first()?->role)->toBe(Role::Admin);
});

it('shows missing csv columns as editable cell errors that resolve live', function () {
    $file = UploadedFile::fake()->createWithContent(
        'users.csv',
        "email,role\nstudent@example.com,student\n",
    );

    Livewire::test('admin.csv-user-import')
        ->set('csvFile', $file)
        ->assertSet('missingColumns', ['name'])
        ->assertSet('rows.0.name', '')
        ->assertSet('rows.0.email', 'student@example.com')
        ->assertSet('cellErrors.0.name', 'Name is required.')
        ->set('rows.0.name', 'Student Person')
        ->assertSet('cellErrors', [])
        ->call('import')
        ->assertSet('done', true)
        ->assertSet('imported', 1);

    expect(User::where('email', 'student@example.com')->first()?->name)->toBe('Student Person');
});

it('keeps invalid cells highlighted until edited to valid values', function () {
    $file = UploadedFile::fake()->createWithContent(
        'users.csv',
        "name,email,role\nBad Row,not-an-email,mentor\n",
    );

    Livewire::test('admin.csv-user-import')
        ->set('csvFile', $file)
        ->assertSet('cellErrors.0.email', 'Enter a valid email address.')
        ->assertSet('cellErrors.0.role', 'Use service, admin, student, faculty.')
        ->set('rows.0.email', 'bad.row@example.com')
        ->assertSet('cellErrors.0.role', 'Use service, admin, student, faculty.')
        ->set('rows.0.role', 'faculty')
        ->assertSet('cellErrors', [])
        ->call('import')
        ->assertSet('done', true)
        ->assertSet('imported', 1);

    expect(User::where('email', 'bad.row@example.com')->first()?->role)->toBe(Role::Faculty);
});

it('parses csv files with a UTF-8 BOM in the header', function () {
    $file = UploadedFile::fake()->createWithContent(
        'users.csv',
        "\xEF\xBB\xBFname,email,role\nJane Faculty,jane.faculty@example.com,faculty\n",
    );

    Livewire::test('admin.csv-user-import')
        ->set('csvFile', $file)
        ->assertSet('missingColumns', [])
        ->assertSet('rows.0.name', 'Jane Faculty')
        ->assertSet('cellErrors', []);
});

it('serves a downloadable sample csv with the expected columns', function () {
    $response = $this->get(route('admin.users.import-csv.sample'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename="user-import-sample.csv"');

    expect($response->getContent())->toStartWith("name,email,role\n");
});

it('accepts service as a valid role in the csv', function () {
    $file = UploadedFile::fake()->createWithContent(
        'users.csv',
        "name,email,role\nSvc Account,svc@example.com,service\n",
    );

    Livewire::test('admin.csv-user-import')
        ->set('csvFile', $file)
        ->assertSet('cellErrors', [])
        ->call('import')
        ->assertSet('done', true)
        ->assertSet('imported', 1);

    expect(User::where('email', 'svc@example.com')->first()?->role)->toBe(Role::Service);
});
