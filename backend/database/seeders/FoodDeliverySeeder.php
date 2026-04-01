<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FoodDeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customerRole = Role::query()->where('name', 'customer')->firstOrFail();
        $ownerRole = Role::query()->where('name', 'restaurant_owner')->firstOrFail();
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => 'admin@foodhub.test'],
            [
                'name' => 'Platform Admin',
                'role_id' => $adminRole->id,
                'password' => Hash::make('password123'),
                'phone' => '555-1000',
                'address' => '1 Admin Plaza',
            ]
        );

        $owner = User::query()->updateOrCreate(
            ['email' => 'owner@foodhub.test'],
            [
                'name' => 'Restaurant Owner',
                'role_id' => $ownerRole->id,
                'password' => Hash::make('password123'),
                'phone' => '555-2000',
                'address' => '22 Kitchen Street',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'customer@foodhub.test'],
            [
                'name' => 'Sample Customer',
                'role_id' => $customerRole->id,
                'password' => Hash::make('password123'),
                'phone' => '555-3000',
                'address' => '101 Customer Ave',
            ]
        );

        $restaurant = Restaurant::query()->updateOrCreate(
            ['name' => 'Sunset Grill'],
            [
                'owner_user_id' => $owner->id,
                'description' => 'Fresh burgers, bowls, and sides.',
                'address' => '800 Market St',
                'phone' => '555-4422',
                'is_active' => true,
            ]
        );

        $items = [
            ['name' => 'Classic Cheeseburger', 'description' => 'Beef patty, cheddar, lettuce.', 'price' => 11.99],
            ['name' => 'Truffle Fries', 'description' => 'Crispy fries with truffle oil.', 'price' => 5.49],
            ['name' => 'Citrus Salad', 'description' => 'Mixed greens, citrus vinaigrette.', 'price' => 8.25],
        ];

        foreach ($items as $item) {
            MenuItem::query()->updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'name' => $item['name'],
                ],
                [
                    'description' => $item['description'],
                    'category' => 'Popular',
                    'price' => $item['price'],
                    'is_available' => true,
                ]
            );
        }
    }
}
