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
        Schema::table('restaurants', function (Blueprint $table) {
            $table->index(['is_active', 'created_at'], 'restaurants_active_created_idx');
            $table->index(['owner_user_id', 'is_active'], 'restaurants_owner_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex('restaurants_active_created_idx');
            $table->dropIndex('restaurants_owner_active_idx');
        });
    }
};
