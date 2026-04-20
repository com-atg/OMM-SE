<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Omm\RedcapAdvancedLink\RedcapAdvancedLinkService;

function fakeAdvancedLinkService(
    ?array $identity,
    array $assignments,
    string $projectToken = 'project-token',
): RedcapAdvancedLinkService {
    return new class($identity, $assignments, $projectToken) extends RedcapAdvancedLinkService
    {
        /**
         * @param  array<string,mixed>|null  $identity
         * @param  array<int,array<string,mixed>>  $assignments
         */
        public function __construct(
            private ?array $identity,
            private array $assignments,
            private string $projectToken,
        ) {}

        protected function fetchIdentity(string $authKey): ?array
        {
            return $this->identity;
        }

        protected function projectToken(string $projectId): string
        {
            return $this->projectToken;
        }

        protected function fetchRoleAssignments(string $token): array
        {
            return $this->assignments;
        }
    };
}

test('authorizes a user whose redcap role is configured', function () {
    $service = fakeAdvancedLinkService(
        [
            'username' => 'mmatalia',
            'project_id' => '1846',
            'data_access_group_name' => 'OMM',
            'data_access_group_id' => '1',
            'callback_url' => 'https://redcap.example.test/project',
        ],
        [
            ['username' => 'mmatalia', 'unique_role_name' => 'authorized_role'],
        ],
    );

    expect($service->authorize('authkey', ['authorized_role']))
        ->toMatchArray([
            'username' => 'mmatalia',
            'project_id' => '1846',
            'unique_role_name' => 'authorized_role',
        ]);
});

test('posts authkey to the redcap api as form data', function () {
    config(['redcap-advanced-link.url' => 'https://redcap.example.test/api/']);

    Http::fake([
        'https://redcap.example.test/api/' => Http::response([
            'username' => 'mmatalia',
            'project_id' => '1846',
        ]),
    ]);

    $service = new class extends RedcapAdvancedLinkService
    {
        protected function projectToken(string $projectId): string
        {
            return 'project-token';
        }

        protected function fetchRoleAssignments(string $token): array
        {
            return [
                ['username' => 'mmatalia', 'unique_role_name' => 'authorized_role'],
            ];
        }
    };

    expect($service->authorize('valid-authkey', ['authorized_role']))
        ->toHaveKey('username', 'mmatalia');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://redcap.example.test/api/'
            && $request['authkey'] === 'valid-authkey'
            && $request['format'] === 'json';
    });
});

test('rejects a user whose redcap role is not configured', function () {
    $service = fakeAdvancedLinkService(
        ['username' => 'mmatalia', 'project_id' => '1846'],
        [
            ['username' => 'mmatalia', 'unique_role_name' => 'other_role'],
        ],
    );

    expect($service->authorize('authkey', ['authorized_role']))->toBeNull();
});

test('rejects a user when no project token is configured for the project id', function () {
    $service = fakeAdvancedLinkService(
        ['username' => 'mmatalia', 'project_id' => '1846'],
        [
            ['username' => 'mmatalia', 'unique_role_name' => 'authorized_role'],
        ],
        projectToken: '',
    );

    expect($service->authorize('authkey', ['authorized_role']))->toBeNull();
});
