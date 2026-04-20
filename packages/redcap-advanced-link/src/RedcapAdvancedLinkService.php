<?php

namespace Omm\RedcapAdvancedLink;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class RedcapAdvancedLinkService
{
    private string $url;

    public function __construct()
    {
        $this->url = (string) config('redcap-advanced-link.url');
    }

    /**
     * @param  array<int,string>  $authorizedRoles
     * @return array<string,string>|null
     */
    public function authorize(string $authKey, array $authorizedRoles): ?array
    {
        $identity = $this->fetchIdentity($authKey);

        if ($identity === null) {
            return null;
        }

        $username = trim((string) ($identity['username'] ?? ''));
        $projectId = trim((string) ($identity['project_id'] ?? ''));

        if ($username === '' || $projectId === '') {
            return null;
        }

        $token = $this->projectToken($projectId);

        if ($token === '') {
            return null;
        }

        $role = $this->roleForUsername($username, $this->fetchRoleAssignments($token));

        if ($role === null || ! $this->isRoleAuthorized($role, $authorizedRoles)) {
            return null;
        }

        return [
            'username' => $username,
            'project_id' => $projectId,
            'unique_role_name' => $role,
            'data_access_group_name' => (string) ($identity['data_access_group_name'] ?? ''),
            'data_access_group_id' => (string) ($identity['data_access_group_id'] ?? ''),
            'callback_url' => (string) ($identity['callback_url'] ?? ''),
        ];
    }

    /**
     * @param  array<int,string>  $authorizedRoles
     */
    public function isRoleAuthorized(string $role, array $authorizedRoles): bool
    {
        $authorizedRoles = array_map('trim', $authorizedRoles);

        return in_array(trim($role), $authorizedRoles, true);
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function fetchIdentity(string $authKey): ?array
    {
        if ($this->url === '') {
            throw new RuntimeException('REDCap API URL is not configured.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->post($this->url, [
                'authkey' => $authKey,
                'format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('REDCap Advanced Link validation failed with HTTP '.$response->status().'.');
        }

        if (trim($response->body()) === '0') {
            return null;
        }

        $payload = $response->json();

        if (is_array($payload) && array_is_list($payload) && isset($payload[0]) && is_array($payload[0])) {
            $payload = $payload[0];
        }

        return is_array($payload) ? $payload : null;
    }

    protected function projectToken(string $projectId): string
    {
        return (string) config("redcap-advanced-link.project_tokens.{$projectId}", '');
    }

    /**
     * Fetch role assignments for the REDCap project using the provided API token.
     *
     * The default implementation expects a `Redcap_lib` class exposing a static
     * `exportUserRoleAssignments` method (as provided by the REDCap PHP API
     * wrapper used in the OMM projects). Override this method in a subclass if
     * your project uses a different REDCap client.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function fetchRoleAssignments(string $token): array
    {
        $client = config('redcap-advanced-link.api_client');

        if (! is_string($client) || ! class_exists($client) || ! method_exists($client, 'exportUserRoleAssignments')) {
            throw new RuntimeException('REDCap API client is not configured. Set redcap-advanced-link.api_client to a class providing exportUserRoleAssignments().');
        }

        $assignments = $client::exportUserRoleAssignments(
            format: 'json',
            returnAs: 'array',
            url: $this->url,
            token: $token,
        );

        return is_array($assignments) ? $assignments : [];
    }

    /**
     * @param  array<int,array<string,mixed>>  $assignments
     */
    private function roleForUsername(string $username, array $assignments): ?string
    {
        foreach ($assignments as $assignment) {
            if ((string) ($assignment['username'] ?? '') === $username) {
                $role = trim((string) ($assignment['unique_role_name'] ?? ''));

                return $role !== '' ? $role : null;
            }
        }

        return null;
    }
}
