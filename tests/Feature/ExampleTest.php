<?php

test('unauthenticated requests redirect to SAML login', function () {
    $this->get('/')->assertRedirect(route('saml.login'));
});
