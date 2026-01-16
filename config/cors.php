<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://smt.tfnsolutions.us',
        'https://identity.smt.tfnsolutions.us',
        'http://localhost:3000',
        'http://localhost:8000',
        'http://localhost:8001',
        'http://localhost:8002',
        'http://localhost:5173',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 0,

    'supports_credentials' => false,
];


