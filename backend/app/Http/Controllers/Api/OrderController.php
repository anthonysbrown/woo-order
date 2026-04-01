<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::query()
            ->with(['items', 'restaurant', 'statusHistory', 'customer'])
            ->latest()
            ->when($user->hasRole('customer'), fn ($q) => $q->where('customer_id', $user->id))
            ->when(
                $user->hasRole('restaurant_owner'),
                fn ($q) => $q->whereIn('restaurant_id', $user->restaurants()->pluck('id'))
            );

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'delivery_address' => ['required', 'string', 'max:500'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $order = $this->orderService->createFromCart(
                $request->user(),
                $payload['delivery_address'],
                $payload['customer_note'] ?? null
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($order, Response::HTTP_CREATED);
    }

    public function show(Request $request, Order $order)
    {
        $user = $request->user();
        $canAccess = $order->customer_id === $user->id
            || ($user->hasRole('restaurant_owner') && $user->restaurants()->whereKey($order->restaurant_id)->exists())
            || $user->hasRole('admin');

        if (! $canAccess) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return response()->json($order->load(['items', 'restaurant', 'statusHistory', 'payment']));
    }

    public function track(Request $request, Order $order)
    {
        return $this->show($request, $order);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $payload = $request->validate([
            'status' => ['required', 'in:accepted,rejected,preparing,delivered'],
        ]);

        $user = $request->user();
        $ownerAllowed = $user->hasRole('restaurant_owner') && $user->restaurants()->whereKey($order->restaurant_id)->exists();
        $adminAllowed = $user->hasRole('admin');
        if (! ($ownerAllowed || $adminAllowed)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        try {
            $updated = $this->orderService->updateStatus($order, $payload['status'], $user);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($updated);
    }
}
