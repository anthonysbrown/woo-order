<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuQueryRequest;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\ActivityLogger;
use App\Services\Restaurant\MenuService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MenuItemController extends Controller
{
    public function __construct(
        private readonly MenuService $menuService,
        private readonly ActivityLogger $activityLogger
    ) {
    }

    public function index(MenuQueryRequest $request, Restaurant $restaurant): JsonResponse
    {
        if (! $restaurant->is_active) {
            return response()->json(['message' => 'Restaurant not available.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $cacheKey = sprintf(
            'restaurant:%d:menu:%s',
            $restaurant->id,
            md5(json_encode($validated))
        );
        $ttl = now()->addSeconds((int) config('app.public_api_cache_ttl', 60));

        $menuItems = Cache::remember($cacheKey, $ttl, function () use ($restaurant, $validated) {
            $query = $restaurant->menuItems()
                ->where('is_available', true)
                ->orderBy('category')
                ->orderBy('name');

            if (! empty($validated['category'])) {
                $query->where('category', $validated['category']);
            }

            return $query->get();
        });

        return response()->json($menuItems);
    }

    public function store(Request $request, Restaurant $restaurant): JsonResponse
    {
        $user = $request->user();
        if (! $this->menuService->ownerCanManage($user, $restaurant)) {
            return response()->json(['message' => 'Forbidden for this restaurant.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'is_available' => ['sometimes', 'boolean'],
            'category' => ['nullable', 'string', 'max:80'],
            'image_url' => ['nullable', 'string', 'max:1024'],
        ]);

        $menuItem = $this->menuService->create($restaurant, $payload);
        $this->activityLogger->log($user, 'menu_item.created', MenuItem::class, $menuItem->id);

        return response()->json($menuItem, Response::HTTP_CREATED);
    }

    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $user = $request->user();
        if (! $this->menuService->ownerCanManage($user, $menuItem->restaurant)) {
            return response()->json(['message' => 'Forbidden for this restaurant.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0.01'],
            'is_available' => ['sometimes', 'boolean'],
            'category' => ['nullable', 'string', 'max:80'],
            'image_url' => ['nullable', 'string', 'max:1024'],
        ]);

        $menuItem = $this->menuService->update($menuItem, $payload);
        $this->activityLogger->log($user, 'menu_item.updated', MenuItem::class, $menuItem->id);

        return response()->json($menuItem);
    }

    public function destroy(Request $request, MenuItem $menuItem): JsonResponse
    {
        $user = $request->user();
        if (! $this->menuService->ownerCanManage($user, $menuItem->restaurant)) {
            return response()->json(['message' => 'Forbidden for this restaurant.'], Response::HTTP_FORBIDDEN);
        }

        $this->menuService->remove($menuItem);
        $this->activityLogger->log($user, 'menu_item.deleted', MenuItem::class, $menuItem->id);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
