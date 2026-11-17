<?php

return [
    // Development tooling must stay off outside the local environment.
    'enabled' => env('APP_ENV', 'production') === 'local' && (bool) env('BOOST_ENABLED', true),
    'browser_logs_watcher' => false,
    'executable_paths' => [
        'php' => './bin/boost-php',
    ],
];
