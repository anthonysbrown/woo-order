<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));
        $pageName = $request->input('page') ? 'page' : 'activities_page';
        $activities = ActivityLog::query()
            ->with(['actor:id,name,email'])
            ->latest()
            ->paginate($perPage, ['*'], $pageName);

        return response()->json($activities);
    }
}
