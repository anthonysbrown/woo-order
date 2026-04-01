<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestaurantBrowseRequest;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RestaurantController extends Controller
{
    public function index(RestaurantBrowseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? '';
        $perPage = (int) ($validated['per_page'] ?? 12);

        $cacheKey = sprintf(
            'restaurants:index:%s:%d:%d',
            md5((string) $search),
            $perPage,
            (int) $request->integer('page', 1)
        );

        $restaurants = Cache::remember(
            $cacheKey,
            now()->addSeconds(60),
            function () use ($search, $perPage) {
                $query = Restaurant::query()
                    ->withCount('menuItems')
                    ->where('is_active', true)
                    ->latest();

                if ($search !== '') {
                    $query->where('name', 'like', '%'.$search.'%');
                }

                return $query->paginate($perPage);
            }
        );

        return response()->json($restaurants);
    }

    public function show(Restaurant $restaurant): JsonResponse
    {
        if (! $restaurant->is_active) {
            return response()->json(['message' => 'Restaurant not available.'], Response::HTTP_NOT_FOUND);
        }

        $restaurant->load(['menuItems' => fn ($q) => $q->where('is_available', true)->orderBy('category')->orderBy('name')]);

        return response()->json(['data' => $restaurant]);
    }
}
