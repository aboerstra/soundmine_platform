<?php

return [
    'activations' => [
        'enabled' => (bool) env('SOUNDMINE_ONLINE_ACTIVATION_ENABLED', true),
        'authorization_minutes' => 10,
        'token_days' => 30,
    ],
    'licensing' => [
        'available' => false,
        'certificate_algorithm' => 'Ed25519',
        'certificate_schema_version' => 1,
    ],
];
