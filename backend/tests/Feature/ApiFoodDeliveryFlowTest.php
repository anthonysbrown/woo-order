<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
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
}
