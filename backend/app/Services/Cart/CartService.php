<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CartService
{
    public function getOrCreate(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function addItem(User $user, int $menuItemId, int $quantity): Cart
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }

        $menuItem = MenuItem::query()->findOrFail($menuItemId);

        return DB::transaction(function () use ($user, $menuItem, $quantity): Cart {
            $cart = $this->getOrCreate($user);
            $existingRestaurantIds = $cart->items()
                ->join('menu_items', 'menu_items.id', '=', 'cart_items.menu_item_id')
                ->distinct()
                ->pluck('menu_items.restaurant_id');

            if ($existingRestaurantIds->isNotEmpty() && ! $existingRestaurantIds->contains($menuItem->restaurant_id)) {
                throw new RuntimeException('Cart can only contain items from one restaurant at a time.');
            }

            $item = $cart->items()->firstOrNew([
                'menu_item_id' => $menuItem->id,
            ]);

            $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
            $item->unit_price = $menuItem->price;
            $item->line_total = round((float) $item->unit_price * (int) $item->quantity, 2);
            $item->save();

            return $cart->load('items.menuItem');
        });
    }

    public function updateItemQuantity(User $user, int $cartItemId, int $quantity): Cart
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }

        return DB::transaction(function () use ($user, $cartItemId, $quantity): Cart {
            $cart = $this->getOrCreate($user);
            $item = $cart->items()->findOrFail($cartItemId);

            $item->quantity = $quantity;
            $item->line_total = round((float) $item->unit_price * (int) $quantity, 2);
            $item->save();

            return $cart->load('items.menuItem');
        });
    }

    public function removeItem(User $user, int $cartItemId): Cart
    {
        $cart = $this->getOrCreate($user);
        $cart->items()->whereKey($cartItemId)->delete();

        return $cart->load('items.menuItem');
    }

    public function clear(User $user): void
    {
        $cart = $this->getOrCreate($user);
        $cart->items()->delete();
    }

    /**
     * @return array{subtotal: float, delivery_fee: float, tax_amount: float, total_amount: float}
     */
    public function calculateTotals(Collection $cartItems): array
    {
        $subtotal = 0.0;
        foreach ($cartItems as $item) {
            $subtotal += (float) $item->line_total;
        }

        $subtotal = round($subtotal, 2);
        $deliveryFee = $subtotal === 0.0 ? 0.0 : 3.99;
        $taxAmount = round($subtotal * 0.08, 2);
        $totalAmount = round($subtotal + $deliveryFee + $taxAmount, 2);

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }
}
