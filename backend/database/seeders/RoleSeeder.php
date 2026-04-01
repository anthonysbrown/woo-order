<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'customer', 'description' => 'Can browse restaurants and place orders'],
            ['name' => 'restaurant_owner', 'description' => 'Can manage menu and restaurant orders'],
            ['name' => 'admin', 'description' => 'Can manage users, restaurants, and activity'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}
