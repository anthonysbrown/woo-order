<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogger
{
    public function log(
        ?User $actor,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = []
    ): ActivityLog {
        return ActivityLog::create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role?->name,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ]);
    }
}
