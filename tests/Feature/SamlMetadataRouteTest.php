<?php

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

it('serves SAML metadata without starting a session', function () {
    config([
        'saml.strict' => false,
        'saml.sp.entityId' => 'https://comapp.nyit.edu/omm_ace/saml/metadata',
        'saml.sp.assertionConsumerService.url' => 'https://comapp.nyit.edu/omm_ace/saml/acs',
        'saml.sp.singleLogoutService.url' => 'https://comapp.nyit.edu/omm_ace/saml/logout',
        'saml.idp.entityId' => null,
        'saml.idp.singleSignOnService.url' => null,
        'saml.idp.singleLogoutService.url' => '',
        'saml.idp.x509cert' => '',
        'saml.security.wantAssertionsSigned' => false,
    ]);

    $response = $this->get('/saml/metadata');

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
        ->assertHeaderMissing('Set-Cookie');

    expect($response->getContent())
        ->toContain('<md:EntityDescriptor')
        ->toContain('entityID="https://comapp.nyit.edu/omm_ace/saml/metadata"')
        ->and(Route::getRoutes()->getByName('saml.metadata')->excludedMiddleware())
        ->toContain(StartSession::class);
});
