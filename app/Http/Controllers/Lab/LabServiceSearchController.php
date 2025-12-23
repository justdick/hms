<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\LabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabServiceSearchController extends Controller
{
    /**
     * Search lab services with optional type and modality filtering.
     *
     * Query parameters:
     * - q: Search query (required, min 2 characters)
     * - type: Filter by type ('laboratory', 'imaging', or null for all)
     * - modality: Filter by modality (only applies when type is 'imaging')
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $type = $request->get('type'); // 'laboratory', 'imaging', or null for all
        $modality = $request->get('modality'); // Filter by modality (e.g., 'X-Ray', 'CT', 'MRI')

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $labServicesQuery = LabService::active()
            ->search($query);

        // Apply type filter if specified
        if ($type === 'laboratory') {
            $labServicesQuery->laboratory();
        } elseif ($type === 'imaging') {
            $labServicesQuery->imaging();

            // Apply modality filter if specified (only for imaging)
            if ($modality) {
                $labServicesQuery->where('modality', $modality);
            }
        }

        $labServices = $labServicesQuery
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'code', 'category', 'sample_type', 'turnaround_time', 'price', 'is_imaging', 'modality']);

        return response()->json($labServices);
    }
}
