<?php

return [
    'tax_rate' => (float) env('ORDER_TAX_RATE', 0.08),
    'delivery_fee' => (float) env('ORDER_DELIVERY_FEE', 4.99),
];
