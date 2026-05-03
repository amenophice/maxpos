<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTenantController extends Controller
{
    public function approve(Tenant $tenant): JsonResponse
    {
        $tenant->status = 'trial';
        $tenant->trial_ends_at = now()->addDays(30);
        $tenant->save();

        return response()->json([
            'data' => ['message' => 'Tenant aprobat.'],
            'meta' => [],
        ]);
    }

    public function reject(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $tenant->status = 'rejected';
        $tenant->rejection_reason = $request->reason;
        $tenant->save();

        return response()->json([
            'data' => ['message' => 'Tenant respins.'],
            'meta' => [],
        ]);
    }
}
