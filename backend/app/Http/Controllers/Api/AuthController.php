<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\AuthRegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Auth\JwtService;
use App\Support\Sanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly ActivityLogger $activityLogger,
        private readonly Sanitizer $sanitizer
    ) {
    }

    public function register(AuthRegisterRequest $request)
    {
        $validated = $request->validated();
        $sanitizedName = Sanitizer::text($validated['name']);
        $sanitizedAddress = isset($validated['address']) ? Sanitizer::text($validated['address']) : null;
        $sanitizedPhone = isset($validated['phone']) ? preg_replace('/[^0-9+\-\s]/', '', $validated['phone']) : null;

        $role = Role::query()->where('name', $validated['role'])->firstOrFail();

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => $sanitizedName,
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'phone' => $sanitizedPhone,
            'address' => $sanitizedAddress,
        ])->load('role');

        $this->activityLogger->log($user, 'auth.registered', User::class, $user->id);

        $token = $this->jwtService->generateToken($user);

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], Response::HTTP_CREATED);
    }

    public function login(AuthLoginRequest $request)
    {
        $validated = $request->validated();

        $user = User::query()
            ->with('role')
            ->where('email', strtolower($validated['email']))
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtService->generateToken($user);
        $this->activityLogger->log($user, 'auth.logged_in', User::class, $user->id);

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('role');

        return response()->json(['user' => $user]);
    }
}
