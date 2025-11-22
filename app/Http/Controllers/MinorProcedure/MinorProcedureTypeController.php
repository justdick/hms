<?php

namespace App\Http\Controllers\MinorProcedure;

use App\Http\Controllers\Controller;
use App\Models\MinorProcedureType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MinorProcedureTypeController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', MinorProcedureType::class);

        $procedureTypes = MinorProcedureType::orderBy('category')
            ->orderBy('name')
            ->get();

        $categories = MinorProcedureType::distinct('category')
            ->pluck('category')
            ->sort()
            ->values();

        return Inertia::render('MinorProcedure/Configuration/Index', [
            'procedureTypes' => $procedureTypes,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MinorProcedureType::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:minor_procedure_types,code',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        MinorProcedureType::create([
            ...$validated,
            'is_active' => true,
        ]);

        return redirect()
            ->route('minor-procedures.types.index')
            ->with('success', 'Procedure type created successfully.');
    }

    public function update(Request $request, MinorProcedureType $procedureType): RedirectResponse
    {
        $this->authorize('update', $procedureType);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:minor_procedure_types,code,'.$procedureType->id,
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $procedureType->update($validated);

        return redirect()
            ->route('minor-procedures.types.index')
            ->with('success', 'Procedure type updated successfully.');
    }

    public function destroy(MinorProcedureType $procedureType): RedirectResponse
    {
        $this->authorize('delete', $procedureType);

        // Check if procedure type is being used
        if ($procedureType->procedures()->exists()) {
            return redirect()
                ->route('minor-procedures.types.index')
                ->with('error', 'Cannot delete procedure type that has been used in procedures.');
        }

        $procedureType->delete();

        return redirect()
            ->route('minor-procedures.types.index')
            ->with('success', 'Procedure type deleted successfully.');
    }

    public function suggestCode(Request $request): JsonResponse
    {
        $this->authorize('create', MinorProcedureType::class);

        $category = $request->query('category');

        if (! $category) {
            return response()->json(['code' => '']);
        }

        // Generate category prefix (first 3-4 letters)
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 4));

        // Find next available number
        $existingCodes = MinorProcedureType::where('code', 'LIKE', $prefix.'%')
            ->pluck('code')
            ->map(function ($code) use ($prefix) {
                return (int) str_replace($prefix, '', $code);
            })
            ->filter()
            ->sort();

        $nextNumber = $existingCodes->isEmpty() ? 1 : $existingCodes->last() + 1;
        $suggestedCode = $prefix.str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        return response()->json(['code' => $suggestedCode]);
    }
}
