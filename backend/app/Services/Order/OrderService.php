<?php

namespace App\Services\Order;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\ActivityLogger;
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

    public function createFromCart(User $customer, string $deliveryAddress, ?string $customerNote = null): Order
    {
        $cart = Cart::query()
            ->with(['items.menuItem'])
            ->firstOrCreate(['user_id' => $customer->id]);

        if ($cart->items->isEmpty()) {
            throw new RuntimeException('Cart is empty.');
        }

        $restaurantId = $cart->items->first()->menuItem->restaurant_id;
        $differentRestaurantItem = $cart->items->first(fn ($item) => $item->menuItem->restaurant_id !== $restaurantId);

        if ($differentRestaurantItem) {
            throw new RuntimeException('Cart contains items from multiple restaurants.');
        }

        $subtotal = (float) $cart->items->sum(fn ($item) => $item->unit_price * $item->quantity);
        $deliveryFee = 4.99;
        $taxAmount = round($subtotal * 0.08, 2);
        $total = round($subtotal + $deliveryFee + $taxAmount, 2);

        return DB::transaction(function () use ($cart, $customer, $deliveryAddress, $customerNote, $restaurantId, $subtotal, $deliveryFee, $taxAmount, $total) {
            $order = Order::query()->create([
                'customer_id' => $customer->id,
                'restaurant_id' => $restaurantId,
                'status' => Order::STATUS_PENDING,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'payment_status' => 'pending',
                'delivery_address' => $deliveryAddress,
                'customer_note' => $customerNote,
            ]);

            foreach ($cart->items as $cartItem) {
                $order->items()->create([
                    'menu_item_id' => $cartItem->menu_item_id,
                    'name' => $cartItem->menuItem->name,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'line_total' => $cartItem->unit_price * $cartItem->quantity,
                ]);
            }

            $this->recordStatus($order, Order::STATUS_PENDING, $customer->id);

            $this->paymentService->mockPay($order);
            $order->payment_status = 'paid';
            $order->save();

            $cart->items()->delete();

            $this->activityLogger->log($customer, 'order.created', Order::class, $order->id, [
                'status' => $order->status,
                'total' => $order->total_amount,
            ]);

            return $order->load(['items', 'payment', 'restaurant', 'statusHistory']);
        });
    }

    public function updateStatus(Order $order, string $newStatus, User $actor): Order
    {
        $allowed = self::TRANSITIONS[$order->status] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            throw new RuntimeException('Invalid order status transition.');
        }

        $order->status = $newStatus;
        $order->save();

        $this->recordStatus($order, $newStatus, $actor->id);
        $this->activityLogger->log($actor, 'order.status.updated', Order::class, $order->id, [
            'from' => $order->getOriginal('status'),
            'to' => $newStatus,
        ]);

        return $order->load(['items', 'payment', 'statusHistory', 'customer']);
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
}
