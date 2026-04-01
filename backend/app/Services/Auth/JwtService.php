<?php

namespace App\Services\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    public function generateToken(User $user): string
    {
        $ttlMinutes = (int) config('jwt.ttl_minutes', 120);
        $now = time();

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + ($ttlMinutes * 60),
            'role' => optional($user->role)->name,
        ];

        return JWT::encode($payload, config('jwt.secret'), config('jwt.algo', 'HS256'));
    }

    public function decodeToken(string $token): object
    {
        return JWT::decode($token, new Key(config('jwt.secret'), config('jwt.algo', 'HS256')));
    }
}
