<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestaurantBrowseRequest;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;

class RestaurantController extends Controller
{
    public function index(RestaurantBrowseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Restaurant::query()
            ->withCount('menuItems')
            ->where('is_active', true)
            ->latest();

        if (! empty($validated['search'])) {
            $query->where('name', 'like', '%'.$validated['search'].'%');
        }

        $restaurants = $query->paginate((int) ($validated['per_page'] ?? 12));

        return response()->json($restaurants);
    }

    public function show(Restaurant $restaurant): JsonResponse
    {
        if (! $restaurant->is_active) {
            return response()->json(['message' => 'Restaurant not available.'], 404);
        }

        $restaurant->load(['menuItems' => fn ($q) => $q->where('is_available', true)->orderBy('category')->orderBy('name')]);

        return response()->json(['data' => $restaurant]);
    }
}
