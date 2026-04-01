<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $algo = (string) config('jwt.algo', 'HS256');
        if (! in_array($algo, ['HS256', 'HS384', 'HS512'], true)) {
            return response()->json(['message' => 'Server token configuration error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $payload = JWT::decode($token, new Key((string) config('jwt.secret'), $algo));
        } catch (ExpiredException) {
            return response()->json(['message' => 'Token expired'], Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable) {
            return response()->json(['message' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        if (! isset($payload->sub) || ! is_numeric($payload->sub)) {
            return response()->json(['message' => 'Invalid token payload'], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::with('role')->find($payload->sub ?? null);
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('jwt_payload', (array) $payload);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return trim(substr($header, 7)) ?: null;
    }
}
