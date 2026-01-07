<?php

namespace App\Http\Controllers\Consultation;

use App\Http\Controllers\Controller;
use App\Models\MinorProcedureType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcedureSearchController extends Controller
{
    /**
     * Search procedures by name or code for theatre procedure selection.
     *
     * Query parameters:
     * - q: Search query (required, min 2 characters)
     *
     * Returns procedures without pricing information, with template indicator.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['procedures' => []]);
        }

        $procedures = MinorProcedureType::active()
            ->search($query)
            ->with('template')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function (MinorProcedureType $procedure) {
                return [
                    'id' => $procedure->id,
                    'name' => $procedure->name,
                    'code' => $procedure->code,
                    'type' => $procedure->type,
                    'category' => $procedure->category,
                    'has_template' => $procedure->template !== null,
                ];
            });

        return response()->json(['procedures' => $procedures]);
    }

    /**
     * Get the template for a specific procedure type.
     *
     * Returns template with variables if exists, null otherwise.
     */
    public function template(MinorProcedureType $procedure): JsonResponse
    {
        $template = $procedure->template;

        if (! $template) {
            return response()->json(['template' => null]);
        }

        // Determine extra fields based on procedure type
        $extraFields = [];
        if ($this->isCaesareanSection($procedure)) {
            $extraFields = ['estimated_gestational_age', 'parity', 'procedure_subtype'];
        }

        return response()->json([
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'template_text' => $template->template_text,
                'variables' => $template->variables,
                'extra_fields' => $extraFields,
            ],
        ]);
    }

    /**
     * Check if the procedure is a Caesarean Section.
     */
    private function isCaesareanSection(MinorProcedureType $procedure): bool
    {
        $csKeywords = ['caesarean', 'cesarean', 'c-section', 'c/s'];
        $name = strtolower($procedure->name);
        $code = strtolower($procedure->code ?? '');

        foreach ($csKeywords as $keyword) {
            if (str_contains($name, $keyword) || str_contains($code, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
