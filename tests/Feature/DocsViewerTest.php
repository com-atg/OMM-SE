<?php

use function Pest\Laravel\get;

it('redirects guests to the login flow', function () {
    get(route('admin.docs.index'))->assertRedirect(route('saml.login'));
});

it('forbids non-Service roles from the docs index', function () {
    asAdmin();
    get(route('admin.docs.index'))->assertForbidden();

    asFaculty();
    get(route('admin.docs.index'))->assertForbidden();

    asStudent('10');
    get(route('admin.docs.index'))->assertForbidden();
});

it('lets Service users see the docs index with all docs listed', function () {
    asService();

    $response = get(route('admin.docs.index'))->assertOk();

    foreach (['readme', 'architecture', 'admin-features', 'security'] as $slug) {
        $response->assertSee(route('admin.docs.show', $slug), false);
    }
});

it('renders a single doc page with rendered markdown', function () {
    asService();

    get(route('admin.docs.show', 'architecture'))
        ->assertOk()
        ->assertSee('docs-prose', false)
        ->assertSee('All Docs');
});

it('renders the README via the readme slug', function () {
    asService();

    get(route('admin.docs.show', 'readme'))->assertOk();
});

it('returns 404 for an unknown slug', function () {
    asService();

    get(route('admin.docs.show', 'nope-not-here'))->assertNotFound();
});
