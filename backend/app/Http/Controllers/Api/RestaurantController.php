<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Restaurant::query()
            ->withCount('menuItems')
            ->where('is_active', true)
            ->latest();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $restaurants = $query->paginate((int) $request->integer('per_page', 10));

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
