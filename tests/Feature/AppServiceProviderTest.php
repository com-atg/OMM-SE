<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;

it('keeps the request base path when forcing production urls', function () {
    config(['app.url' => 'https://comapp.nyit.edu']);

    $request = Request::create(
        'https://comapp.nyit.edu/omm_ace/admin/users',
        'GET',
        server: [
            'SCRIPT_NAME' => '/omm_ace/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ],
    );

    app()->instance('request', $request);

    $provider = new AppServiceProvider(app());
    $method = new ReflectionMethod($provider, 'productionRootUrl');

    expect($method->invoke($provider))->toBe('https://comapp.nyit.edu/omm_ace');
});

it('uses the configured production url when it already has the request base path', function () {
    config(['app.url' => 'https://comapp.nyit.edu/omm_ace']);

    $request = Request::create(
        'https://comapp.nyit.edu/omm_ace/admin/users',
        'GET',
        server: [
            'SCRIPT_NAME' => '/omm_ace/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ],
    );

    app()->instance('request', $request);

    $provider = new AppServiceProvider(app());
    $method = new ReflectionMethod($provider, 'productionRootUrl');

    expect($method->invoke($provider))->toBe('https://comapp.nyit.edu/omm_ace');
});
