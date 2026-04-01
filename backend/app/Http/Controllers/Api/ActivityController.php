<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;

class ActivityController extends Controller
{
    public function index(): JsonResponse
    {
        $activities = ActivityLog::query()
            ->with(['actor:id,name,email'])
            ->latest()
            ->paginate(20);

        return response()->json($activities);
    }
}
