<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Docs Directory
    |--------------------------------------------------------------------------
    | Absolute path to the directory containing your *.md documentation files.
    */
    'docs_path' => base_path('docs'),

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
    | middleware stack that guards them.
    */
    'route_prefix'      => 'admin/docs',
    'route_name_prefix' => 'admin.docs',
    'middleware'        => ['web', 'auth', 'admin'],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    | The Blade layout the package views extend, and the @section name they
    | yield into. Publish the views to customise the markup entirely.
    */
    'layout'         => 'layouts.app',
    'layout_section' => 'content',

];
