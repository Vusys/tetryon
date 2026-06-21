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
    ],

    'artifacts' => [
        'path' => env('TETRYON_ARTIFACTS_PATH', 'tests/Browser/Artifacts'),
    ],

    'selectors' => [
        'test_attributes' => ['data-testid', 'data-test', 'data-cy'],
    ],
];
