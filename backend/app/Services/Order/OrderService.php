<?php

namespace App\Services\Order;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    private const TRANSITIONS = [
        Order::STATUS_PENDING => [Order::STATUS_ACCEPTED, Order::STATUS_REJECTED],
        Order::STATUS_ACCEPTED => [Order::STATUS_PREPARING],
        Order::STATUS_PREPARING => [Order::STATUS_DELIVERED],
        Order::STATUS_DELIVERED => [],
        Order::STATUS_REJECTED => [],
    ];

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly PaymentService $paymentService
    )
    {
    }

    public function createFromCart(
        User $customer,
        string $deliveryAddress,
        ?string $customerNote = null,
        ?string $idempotencyKey = null
    ): Order
    {
        return DB::transaction(function () use ($customer, $deliveryAddress, $customerNote, $idempotencyKey) {
            if ($idempotencyKey !== null) {
                $existingOrder = Order::query()
                    ->where('customer_id', $customer->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingOrder !== null) {
                    return $existingOrder->load(['items', 'payment', 'restaurant', 'statusHistory']);
                }
            }

            $cart = Cart::query()
                ->lockForUpdate()
                ->firstOrCreate(['user_id' => $customer->id]);

            $cartItems = CartItem::query()
                ->with('menuItem')
                ->where('cart_id', $cart->id)
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw new RuntimeException('Cart is empty.');
            }

            $menuItems = MenuItem::query()
                ->whereIn('id', $cartItems->pluck('menu_item_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $pricing = $this->calculatePricing($cartItems, $menuItems);

            try {
                $order = Order::query()->create([
                    'customer_id' => $customer->id,
                    'restaurant_id' => $pricing['restaurant_id'],
                    'status' => Order::STATUS_PENDING,
                    'subtotal' => $pricing['subtotal'],
                    'delivery_fee' => $pricing['delivery_fee'],
                    'tax_amount' => $pricing['tax_amount'],
                    'total_amount' => $pricing['total_amount'],
                    'payment_status' => 'pending',
                    'delivery_address' => $deliveryAddress,
                    'customer_note' => $customerNote,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (QueryException $exception) {
                if ($idempotencyKey === null || ! $this->isIdempotencyConstraintViolation($exception)) {
                    throw $exception;
                }

                $existingOrder = Order::query()
                    ->where('customer_id', $customer->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingOrder !== null) {
                    return $existingOrder->load(['items', 'payment', 'restaurant', 'statusHistory']);
                }

                throw $exception;
            }

            foreach ($cartItems as $cartItem) {
                $currentMenuItem = $menuItems->get((int) $cartItem->menu_item_id);
                if ($currentMenuItem === null || ! $currentMenuItem->is_available) {
                    throw new RuntimeException('One or more items are no longer available.');
                }

                $unitPrice = round((float) $currentMenuItem->price, 2);
                $quantity = (int) $cartItem->quantity;
                $order->items()->create([
                    'menu_item_id' => $cartItem->menu_item_id,
                    'name' => $currentMenuItem->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($unitPrice * $quantity, 2),
                ]);
            }

            $this->recordStatus($order, Order::STATUS_PENDING, $customer->id);

            $this->paymentService->mockPay($order);
            $order->payment_status = 'paid';
            $order->save();

            CartItem::query()->where('cart_id', $cart->id)->delete();

            $this->activityLogger->log($customer, 'order.created', Order::class, $order->id, [
                'status' => $order->status,
                'total' => $order->total_amount,
            ]);

            return $order->load(['items', 'payment', 'restaurant', 'statusHistory']);
        });
    }

    public function updateStatus(Order $order, string $newStatus, User $actor): Order
    {
        return DB::transaction(function () use ($order, $newStatus, $actor) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $allowed = self::TRANSITIONS[$lockedOrder->status] ?? [];
            if (! in_array($newStatus, $allowed, true)) {
                throw new RuntimeException('Invalid order status transition.');
            }

            $previousStatus = $lockedOrder->status;
            $lockedOrder->status = $newStatus;
            $lockedOrder->save();

            $this->recordStatus($lockedOrder, $newStatus, $actor->id);
            $this->activityLogger->log($actor, 'order.status.updated', Order::class, $lockedOrder->id, [
                'from' => $previousStatus,
                'to' => $newStatus,
            ]);

            return $lockedOrder->load(['items', 'payment', 'statusHistory', 'customer']);
        });
    }

    private function recordStatus(Order $order, string $status, ?int $actorId): void
    {
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'status' => $status,
            'changed_by' => $actorId,
            'changed_at' => now(),
        ]);
    }

    /**
     * @param Collection<int, CartItem> $cartItems
     * @param Collection<int, MenuItem> $menuItems
     * @return array{restaurant_id:int, subtotal:float, delivery_fee:float, tax_amount:float, total_amount:float}
     */
    private function calculatePricing(Collection $cartItems, Collection $menuItems): array
    {
        $firstItem = $cartItems->first();
        $firstMenuItem = $firstItem ? $menuItems->get((int) $firstItem->menu_item_id) : null;
        if (! $firstMenuItem) {
            throw new RuntimeException('Unable to resolve cart menu items.');
        }
        $restaurantId = (int) $firstMenuItem->restaurant_id;

        $mixedRestaurantItem = $cartItems->first(function (CartItem $item) use ($menuItems, $restaurantId) {
            $menuItem = $menuItems->get((int) $item->menu_item_id);

            return $menuItem === null || (int) $menuItem->restaurant_id !== $restaurantId;
        });

        if ($mixedRestaurantItem !== null) {
            throw new RuntimeException('Cart contains items from multiple restaurants.');
        }

        $subtotal = 0.0;
        foreach ($cartItems as $item) {
            $menuItem = $menuItems->get((int) $item->menu_item_id);
            if ($menuItem === null || ! $menuItem->is_available) {
                throw new RuntimeException('One or more items are unavailable.');
            }

            $livePrice = (float) $menuItem->price;
            $subtotal += round($livePrice * (int) $item->quantity, 2);
        }

        $subtotal = round($subtotal, 2);
        $deliveryFee = $subtotal > 0 ? (float) config('order.default_delivery_fee', 4.99) : 0.0;
        $taxRate = (float) config('order.tax_rate', 0.08);
        $taxAmount = round($subtotal * $taxRate, 2);
        $totalAmount = round($subtotal + $deliveryFee + $taxAmount, 2);

        return [
            'restaurant_id' => $restaurantId,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }

    private function isIdempotencyConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $vendorCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        $isUniqueViolation = in_array($sqlState, ['23000', '23505'], true)
            || in_array($vendorCode, ['1062', '2067'], true)
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');

        return $isUniqueViolation
            && (
                str_contains($message, 'idempotency_key')
                || str_contains($message, 'orders_customer_id_idempotency_key_unique')
            );
    }
}
