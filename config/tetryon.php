<?php

declare(strict_types=1);

return [
    // Where the app under test is served. Falls back to APP_URL, then a sane
    // local default. You start the server; Tetryon drives the browser.
    'base_url' => env('TETRYON_BASE_URL', env('APP_URL', 'http://127.0.0.1:8000')),

    'headless' => env('TETRYON_HEADLESS', true),

    // Explicit path to the Firefox binary; null = auto-locate.
    'firefox_binary' => env('TETRYON_FIREFOX_BINARY'),

    'timeout' => [
        'default' => 5000,
        'navigation' => 15000,
        'assertion' => 5000,
    ],

    'viewport' => [
        'width' => 1280,
        'height' => 720,
        // 2 for sharp, high-density screenshots and recordings (4× the pixels).
        'device_pixel_ratio' => 1,
    ],

    'artifacts' => [
        'path' => env('TETRYON_ARTIFACTS_PATH', 'tests/Browser/Artifacts'),
    ],

    'selectors' => [
        'test_attributes' => ['data-testid', 'data-test', 'data-cy'],
    ],

    // Let Tetryon start the app server itself, one per process (#74). Under
    // paratest each worker gets its own on base_port + TEST_TOKEN, running
    // with the worker's environment; base_url is then ignored. {port},
    // {token} and {host} are replaced in the command and in env values, so a
    // worker's server can use its own database, e.g.
    // 'env' => ['DB_DATABASE' => 'database/tetryon_{token}.sqlite'].
    'server' => [
        'managed' => env('TETRYON_MANAGED_SERVER', false),
        'command' => 'php artisan serve --host={host} --port={port} --no-reload',
        'host' => '127.0.0.1',
        'base_port' => 8100,
        'ready_path' => '/',
        'ready_timeout' => 15000,
        'env' => [],
        // Where the command runs; null = the app's base path.
        'working_directory' => null,
    ],
];
