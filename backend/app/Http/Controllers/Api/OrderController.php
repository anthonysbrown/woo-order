<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderIndexRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\Order\OrderService;
use App\Support\Sanitizer;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function index(OrderIndexRequest $request)
    {
        $validated = Sanitizer::trimStrings($request->validated());
        $user = $request->user();

        $query = Order::query()
            ->with([
                'items:id,order_id,menu_item_id,name,quantity,unit_price,line_total',
                'restaurant:id,name,address',
                'statusHistory:id,order_id,status,changed_at',
                'customer:id,name,email',
            ])
            ->latest()
            ->when($user->hasRole('customer'), fn ($q) => $q->where('customer_id', $user->id))
            ->when(
                $user->hasRole('restaurant_owner'),
                fn ($q) => $q->whereIn('restaurant_id', $user->restaurants()->pluck('id'))
            );

        $orders = $query->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json($orders);
    }

    public function store(StoreOrderRequest $request)
    {
        $payload = Sanitizer::trimStrings($request->validated());

        try {
            $order = $this->orderService->createFromCart(
                $request->user(),
                $payload['delivery_address'],
                $payload['customer_note'] ?? null,
                $payload['idempotency_key'] ?? null
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($order, Response::HTTP_CREATED);
    }

    public function show(OrderIndexRequest $request, Order $order)
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

    public function track(OrderIndexRequest $request, Order $order)
    {
        return $this->show($request, $order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $payload = Sanitizer::trimStrings($request->validated());

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
