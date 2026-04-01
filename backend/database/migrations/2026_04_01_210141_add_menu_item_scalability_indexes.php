<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->index(['restaurant_id', 'is_available'], 'menu_restaurant_available_idx');
            $table->index(['restaurant_id', 'created_at'], 'menu_restaurant_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex('menu_restaurant_available_idx');
            $table->dropIndex('menu_restaurant_created_idx');
        });
    }
};
