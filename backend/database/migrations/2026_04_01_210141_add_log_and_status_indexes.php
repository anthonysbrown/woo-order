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
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['created_at', 'id'], 'activity_logs_created_at_id_idx');
            $table->index(['actor_id', 'created_at'], 'activity_logs_actor_created_at_idx');
            $table->index(['entity_type', 'entity_id'], 'activity_logs_entity_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_created_at_id_idx');
            $table->dropIndex('activity_logs_actor_created_at_idx');
            $table->dropIndex('activity_logs_entity_lookup_idx');
        });
    }
};
