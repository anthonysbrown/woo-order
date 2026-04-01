<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\ActivityLog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiFoodDeliveryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('jwt.secret', str_repeat('a', 32));
    }

    public function test_customer_can_place_order_and_owner_can_update_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customerLogin = $this->postJson('/api/auth/login', [
            'email' => 'customer@foodhub.test',
            'password' => 'password123',
        ])->assertOk()->json();

        $ownerLogin = $this->postJson('/api/auth/login', [
            'email' => 'owner@foodhub.test',
            'password' => 'password123',
        ])->assertOk()->json();

        $customerToken = $customerLogin['token'];
        $ownerToken = $ownerLogin['token'];

        $restaurant = Restaurant::query()->firstOrFail();
        $menuItem = MenuItem::query()->where('restaurant_id', $restaurant->id)->firstOrFail();

        $this->getJson('/api/restaurants')
            ->assertOk()
            ->assertJsonPath('data.0.id', $restaurant->id);

        $cartAddResponse = $this->postJson('/api/cart/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
        ], [
            'Authorization' => "Bearer {$customerToken}",
        ]);

        $this->assertTrue(
            in_array($cartAddResponse->status(), [201, 200], true),
            $cartAddResponse->getContent()
        );

        $orderCreateResponse = $this->postJson('/api/orders', [
            'delivery_address' => '123 Test Street',
            'customer_note' => 'Leave at door',
            'idempotency_key' => 'test-key-1',
        ], [
            'Authorization' => "Bearer {$customerToken}",
        ]);
        $this->assertSame(201, $orderCreateResponse->status(), $orderCreateResponse->getContent());
        $orderResponse = $orderCreateResponse->json();

        $orderId = $orderResponse['id'];
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'paid',
        ]);

        $this->patchJson("/api/orders/{$orderId}/status", [
            'status' => Order::STATUS_ACCEPTED,
        ], [
            'Authorization' => "Bearer {$ownerToken}",
        ])->assertOk()
            ->assertJsonPath('status', Order::STATUS_ACCEPTED);

        $this->getJson("/api/orders/{$orderId}/track", [
            'Authorization' => "Bearer {$customerToken}",
        ])->assertOk()
            ->assertJsonPath('status', Order::STATUS_ACCEPTED);
    }

    public function test_order_creation_is_idempotent_for_same_key(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customerLogin = $this->postJson('/api/auth/login', [
            'email' => 'customer@foodhub.test',
            'password' => 'password123',
        ])->assertOk()->json();

        $customerToken = $customerLogin['token'];

        $restaurant = Restaurant::query()->firstOrFail();
        $menuItem = MenuItem::query()->where('restaurant_id', $restaurant->id)->firstOrFail();

        $this->postJson('/api/cart/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
        ], [
            'Authorization' => "Bearer {$customerToken}",
        ])->assertCreated();

        $payload = [
            'delivery_address' => '123 Test Street',
            'customer_note' => 'Idempotent checkout',
            'idempotency_key' => 'same-key-1',
        ];

        $first = $this->postJson('/api/orders', $payload, [
            'Authorization' => "Bearer {$customerToken}",
        ])->assertCreated()->json();

        $second = $this->postJson('/api/orders', $payload, [
            'Authorization' => "Bearer {$customerToken}",
        ])->assertCreated()->json();

        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_orders_endpoint_is_paginated(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customerLogin = $this->postJson('/api/auth/login', [
            'email' => 'customer@foodhub.test',
            'password' => 'password123',
        ])->assertOk()->json();

        $customerToken = $customerLogin['token'];

        $restaurant = Restaurant::query()->firstOrFail();
        $menuItem = MenuItem::query()->where('restaurant_id', $restaurant->id)->firstOrFail();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/cart/items', [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
            ], [
                'Authorization' => "Bearer {$customerToken}",
            ])->assertCreated();

            $this->postJson('/api/orders', [
                'delivery_address' => '123 Test Street',
                'idempotency_key' => 'pagination-'.$i,
            ], [
                'Authorization' => "Bearer {$customerToken}",
            ])->assertCreated();
        }

        $this->getJson('/api/orders?per_page=2', [
            'Authorization' => "Bearer {$customerToken}",
        ])->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ]);
    }

    public function test_auth_login_is_rate_limited_after_threshold(): void
    {
        $this->seed(DatabaseSeeder::class);

        for ($i = 0; $i < 12; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'customer@foodhub.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'customer@foodhub.test',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_activity_endpoint_is_paginated(): void
    {
        $this->seed(DatabaseSeeder::class);

        $adminLogin = $this->postJson('/api/auth/login', [
            'email' => 'admin@foodhub.test',
            'password' => 'password123',
        ])->assertOk()->json();

        ActivityLog::query()->create([
            'action' => 'test.activity',
        ]);

        $this->getJson('/api/admin/activity?per_page=10', [
            'Authorization' => 'Bearer '.$adminLogin['token'],
        ])->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ]);
    }
}
