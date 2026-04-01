<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

class PaymentService
{
    public function mockPay(Order $order, string $method = 'mock_card'): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'status' => 'paid',
            'amount' => $order->total_amount,
            'transaction_ref' => sprintf('MOCK-%s', Str::upper(Str::random(12))),
            'paid_at' => now(),
            'meta' => [
                'processor' => 'mock',
            ],
        ]);
    }
}
