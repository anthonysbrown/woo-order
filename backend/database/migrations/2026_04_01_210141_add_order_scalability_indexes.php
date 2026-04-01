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
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'orders_customer_created_idx');
            $table->index(['restaurant_id', 'created_at'], 'orders_restaurant_created_idx');
            $table->index(['status', 'created_at'], 'orders_status_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_customer_created_idx');
            $table->dropIndex('orders_restaurant_created_idx');
            $table->dropIndex('orders_status_created_idx');
        });
    }
};
