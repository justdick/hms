<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\LabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabServiceSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $labServices = LabService::active()
            ->search($query)
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'code', 'category', 'sample_type', 'turnaround_time']);

        return response()->json($labServices);
    }
}
