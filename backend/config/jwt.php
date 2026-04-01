<?php

return [
    'secret' => env('JWT_SECRET', '01234567890123456789012345678901'),
    'ttl_minutes' => env('JWT_TTL_MINUTES', 120),
    'refresh_ttl_minutes' => env('JWT_REFRESH_TTL_MINUTES', 10080),
    'algo' => 'HS256',
    'allowed_clock_skew' => (int) env('JWT_ALLOWED_CLOCK_SKEW', 30),
];
