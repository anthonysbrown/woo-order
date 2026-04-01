<?php

namespace App\Services\Restaurant;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class MenuService
{
    public function restaurantMenu(Restaurant $restaurant): Collection
    {
        return $restaurant->menuItems()->orderBy('category')->orderBy('name')->get();
    }

    public function create(Restaurant $restaurant, array $payload): MenuItem
    {
        return $restaurant->menuItems()->create($payload);
    }

    public function update(MenuItem $item, array $payload): MenuItem
    {
        $item->update($payload);

        return $item->refresh();
    }

    public function remove(MenuItem $item): void
    {
        $item->delete();
    }

    public function ownerCanManage(User $user, Restaurant $restaurant): bool
    {
        return (int) $restaurant->owner_user_id === (int) $user->id;
    }
}
