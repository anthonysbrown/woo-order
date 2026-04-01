<?php

return [
    'secret' => env('JWT_SECRET', '01234567890123456789012345678901'),
    'ttl_minutes' => env('JWT_TTL_MINUTES', 120),
    'algo' => 'HS256',
];
