<?php

use Omm\RedcapAdvancedLink\Redcap_lib;

$authorizedRoles = array_values(array_filter(
    array_map('trim', explode(',', (string) env('AUTHORIZED_ROLES', ''))),
    fn (string $role): bool => $role !== '',
));

$projectTokens = [];
foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
    $key = (string) $key;

    if (str_starts_with($key, 'REDCAP_TOKEN_PID_') && is_string($value) && $value !== '') {
        $projectTokens[substr($key, strlen('REDCAP_TOKEN_PID_'))] = $value;
    }
}

return [
    /*
    | REDCap API endpoint used to exchange the authkey for user identity.
    */
    'url' => env('REDCAP_URL', ''),

    /*
    | Master switch. Defaults to enabled when at least one authorized role is
    | configured, so CI and local environments without AUTHORIZED_ROLES stay open.
    */
    'enabled' => (bool) env('REDCAP_ADVANCED_LINK_ENABLED', $authorizedRoles !== []),

    /*
    | REDCap unique_role_name values permitted to access protected routes.
    */
    'authorized_roles' => $authorizedRoles,

    /*
    | Session key used to persist the authorized user payload between requests.
    */
    'session_key' => 'redcap.advanced_link.user',

    /*
    | Fully-qualified class name providing a static exportUserRoleAssignments()
    | method. The service calls $client::exportUserRoleAssignments(format, returnAs, url, token).
    | Swap this for your project's REDCap API wrapper.
    */
    'api_client' => env('REDCAP_API_CLIENT', Redcap_lib::class),

    /*
    | Project tokens keyed by PID. Populated from REDCAP_TOKEN_PID_{pid} env vars.
    */
    'project_tokens' => $projectTokens,
];
