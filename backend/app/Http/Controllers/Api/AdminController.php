<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function users(Request $request)
    {
        $users = User::query()
            ->with('role')
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json($users);
    }

    public function updateUserRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', 'in:customer,restaurant_owner,admin'],
        ]);

        $roleId = \App\Models\Role::query()->where('name', $validated['role'])->value('id');
        if (! $roleId) {
            return response()->json(['message' => 'Role not found.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->update(['role_id' => $roleId]);

        $this->activityLogger->log(
            $request->user(),
            'admin.user.role_updated',
            User::class,
            $user->id,
            ['role' => $validated['role']]
        );

        return response()->json($user->fresh('role'), Response::HTTP_OK);
    }

    public function restaurants(Request $request)
    {
        $restaurants = Restaurant::query()
            ->with(['owner.role'])
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json($restaurants);
    }

    public function updateRestaurantStatus(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $restaurant->update([
            'is_active' => $validated['is_active'],
        ]);

        $this->activityLogger->log(
            $request->user(),
            'admin.restaurant.status_updated',
            Restaurant::class,
            $restaurant->id,
            ['is_active' => $restaurant->is_active]
        );

        return response()->json($restaurant->fresh('owner'), Response::HTTP_OK);
    }

    public function dashboard()
    {
        return response()->json([
            'users_count' => User::query()->count(),
            'restaurants_count' => Restaurant::query()->count(),
            'active_restaurants_count' => Restaurant::query()->where('is_active', true)->count(),
            'recent_activity_count' => \App\Models\ActivityLog::query()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ]);
    }

    public function toggleRestaurantActive(Request $request, Restaurant $restaurant)
    {
        return $this->updateRestaurantStatus($request, $restaurant);
    }
}
