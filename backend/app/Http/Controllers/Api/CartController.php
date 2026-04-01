<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use App\Support\Sanitizer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function show(Request $request)
    {
        $cart = $this->cartService->getOrCreate($request->user())->load('items.menuItem.restaurant');
        $totals = $this->cartService->calculateTotals($cart->items);

        return response()->json([
            'cart' => $cart,
            'totals' => $totals,
        ]);
    }

    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);
        $validated = Sanitizer::trimStrings($validated);

        try {
            $cart = $this->cartService->addItem(
                $request->user(),
                (int) $validated['menu_item_id'],
                (int) $validated['quantity']
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to update cart right now.'], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'cart' => $cart,
            'totals' => $this->cartService->calculateTotals($cart->items),
        ], Response::HTTP_CREATED);
    }

    public function updateItem(Request $request, int $cartItemId)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);
        $validated = Sanitizer::trimStrings($validated);

        try {
            $cart = $this->cartService->updateItemQuantity(
                $request->user(),
                $cartItemId,
                (int) $validated['quantity']
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to update cart item.'], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'cart' => $cart,
            'totals' => $this->cartService->calculateTotals($cart->items),
        ]);
    }

    public function removeItem(Request $request, int $cartItemId)
    {
        try {
            $cart = $this->cartService->removeItem($request->user(), $cartItemId);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to remove cart item.'], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'cart' => $cart,
            'totals' => $this->cartService->calculateTotals($cart->items),
        ]);
    }

    public function clear(Request $request)
    {
        $this->cartService->clear($request->user());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
