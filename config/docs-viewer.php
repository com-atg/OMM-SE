<?php

use App\Http\Middleware\RequireSamlAuth;

return [

    /*
    |--------------------------------------------------------------------------
    | Docs Directory
    |--------------------------------------------------------------------------
    | Absolute path to the directory containing your *.md documentation files.
    */
    'docs_path' => base_path('Docs'),

    /*
    |--------------------------------------------------------------------------
    | README
    |--------------------------------------------------------------------------
    | Path to a README.md that should appear as the first entry in the list.
    | Set to null to disable.
    */
    'readme_path' => base_path('README.md'),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    | The URL prefix and named-route prefix for the docs pages, plus the
    | middleware stack that guards them. Service-only via the `view-docs` gate.
    */
    'route_prefix' => 'admin/docs',
    'route_name_prefix' => 'admin.docs',
    'middleware' => ['web', RequireSamlAuth::class, 'can:view-docs'],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    | The published views in resources/views/vendor/docs-viewer use this
    | project's <x-app-shell> component directly, so these values are unused
    | by the local override. Kept for parity with the upstream package.
    */
    'layout' => 'layouts.app',
    'layout_section' => 'content',

];
