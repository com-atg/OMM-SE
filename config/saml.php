<?php

$splitCsv = function (string $raw): array {
    return array_values(array_filter(array_map(
        fn (string $v): string => strtolower(trim($v)),
        explode(',', $raw),
    )));
};

$appUrl = rtrim((string) env('APP_URL'), '/');
$spUrl = fn (string $path): ?string => $appUrl !== '' ? $appUrl.$path : null;

return [
    /*
    | ─────────────────────────────────────────────────────────────────────────
    | Service Provider (this app)
    | ─────────────────────────────────────────────────────────────────────────
    */
    'sp' => [
        'entityId' => env('SAML_SP_ENTITY_ID', $spUrl('/saml/metadata')),
        'assertionConsumerService' => [
            'url' => env('SAML_SP_ACS_URL', $spUrl('/saml/acs')),
            'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ],
        'singleLogoutService' => [
            'url' => env('SAML_SP_SLO_URL', $spUrl('/saml/logout')),
            'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],
        'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
        'x509cert' => env('SAML_SP_X509_CERT', ''),
        'privateKey' => env('SAML_SP_PRIVATE_KEY', ''),
    ],

    /*
    | ─────────────────────────────────────────────────────────────────────────
    | Identity Provider (Okta)
    | ─────────────────────────────────────────────────────────────────────────
    */
    'idp' => [
        'entityId' => env('SAML_IDP_ENTITY_ID'),
        'singleSignOnService' => [
            'url' => env('SAML_IDP_SSO_URL'),
            'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],
        'singleLogoutService' => [
            'url' => env('SAML_IDP_SLO_URL', ''),
            'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],
        'x509cert' => env('SAML_IDP_X509_CERT', ''),
    ],

    /*
    | ─────────────────────────────────────────────────────────────────────────
    | Security
    | ─────────────────────────────────────────────────────────────────────────
    */
    'security' => [
        'nameIdEncrypted' => false,
        'authnRequestsSigned' => false,
        'logoutRequestSigned' => false,
        'logoutResponseSigned' => false,
        'wantAssertionsSigned' => true,
        'wantMessagesSigned' => false,
        'wantAssertionsEncrypted' => false,
        'wantNameId' => true,
        'wantNameIdEncrypted' => false,
        'requestedAuthnContext' => false,
        'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
        'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
    ],

    'debug' => (bool) env('SAML_DEBUG', false),
    'strict' => (bool) env('SAML_STRICT', true),

    /*
    | ─────────────────────────────────────────────────────────────────────────
    | SAML assertion → User attribute mapping
    | ─────────────────────────────────────────────────────────────────────────
    |
    | Okta typically pushes email as the NameID (emailAddress format) and
    | optionally as attributes. The service prefers explicit attributes when
    | configured, then falls back to NameID for email.
    */
    'attributes' => [
        'email' => env('SAML_ATTR_EMAIL', 'email'),
        'name' => env('SAML_ATTR_NAME', 'displayName'),
    ],

    /*
    | ─────────────────────────────────────────────────────────────────────────
    | App-level role assignments (email allowlists, comma-separated, lowercased)
    | ─────────────────────────────────────────────────────────────────────────
    |
    | These are consulted on every login. They prime the users table on first
    | sign-in and keep it authoritative if env values change. Users not in
    | either list default to the Student role and must resolve to a REDCap
    | destination record by email.
    */
    'service_users' => $splitCsv((string) env('SERVICE_USERS', '')),
    'admin_users' => $splitCsv((string) env('ADMIN_USERS', '')),

    /*
    | Where to send users after ACS completes without an explicit intended URL.
    */
    'default_redirect' => env('SAML_DEFAULT_REDIRECT', '/'),
];
