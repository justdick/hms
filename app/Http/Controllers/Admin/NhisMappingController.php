<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportNhisMappingRequest;
use App\Http\Requests\StoreNhisMappingRequest;
use App\Http\Resources\NhisMappingResource;
use App\Models\Drug;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NhisMappingController extends Controller
{
    /**
     * Display a listing of NHIS item mappings.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', NhisItemMapping::class);

        $perPage = min($request->input('per_page', 20), 100);

        $mappings = NhisItemMapping::query()
            ->with(['nhisTariff', 'gdrgTariff'])
            ->when($request->input('item_type'), fn ($q, $type) => $q->byItemType($type))
            ->when($request->input('search'), function ($q, $search) {
                $searchTerm = "%{$search}%";
                $q->where(function ($query) use ($searchTerm) {
                    $query->where('item_code', 'like', $searchTerm)
                        ->orWhereHas('nhisTariff', fn ($tariffQuery) => $tariffQuery->where('name', 'like', $searchTerm))
                        ->orWhereHas('nhisTariff', fn ($tariffQuery) => $tariffQuery->where('nhis_code', 'like', $searchTerm))
                        ->orWhereHas('gdrgTariff', fn ($tariffQuery) => $tariffQuery->where('name', 'like', $searchTerm))
                        ->orWhereHas('gdrgTariff', fn ($tariffQuery) => $tariffQuery->where('code', 'like', $searchTerm));
                });
            })
            ->orderBy('item_type')
            ->orderBy('item_code')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/NhisMappings/Index', [
            'mappings' => NhisMappingResource::collection($mappings),
            'filters' => [
                'search' => $request->input('search'),
                'item_type' => $request->input('item_type'),
            ],
            'itemTypes' => ['drug', 'lab_service', 'procedure', 'consumable'],
        ]);
    }

    /**
     * Store a newly created NHIS item mapping.
     */
    public function store(StoreNhisMappingRequest $request): RedirectResponse
    {
        $this->authorize('create', NhisItemMapping::class);

        $validated = $request->validated();

        // Get the item code from the actual item
        $itemCode = $this->getItemCode($validated['item_type'], $validated['item_id']);

        NhisItemMapping::create([
            'item_type' => $validated['item_type'],
            'item_id' => $validated['item_id'],
            'item_code' => $itemCode,
            'nhis_tariff_id' => $validated['nhis_tariff_id'],
        ]);

        return redirect()
            ->route('admin.nhis-mappings.index')
            ->with('success', 'NHIS mapping created successfully.');
    }

    /**
     * Remove the specified NHIS item mapping.
     */
    public function destroy(NhisItemMapping $nhisMapping): RedirectResponse
    {
        $this->authorize('delete', $nhisMapping);

        $nhisMapping->delete();

        return redirect()
            ->route('admin.nhis-mappings.index')
            ->with('success', 'NHIS mapping deleted successfully.');
    }

    /**
     * Import NHIS mappings from a file.
     */
    public function import(ImportNhisMappingRequest $request): RedirectResponse
    {
        $this->authorize('create', NhisItemMapping::class);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');

        // Skip header row
        $header = fgetcsv($handle);

        $imported = 0;
        $updated = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) < 3) {
                $errors[] = "Row {$rowNumber}: Invalid format";

                continue;
            }

            [$itemType, $itemCode, $nhisCode] = $row;

            // Validate item type
            if (! in_array($itemType, ['drug', 'lab_service', 'procedure', 'consumable'])) {
                $errors[] = "Row {$rowNumber}: Invalid item type '{$itemType}'";

                continue;
            }

            // Find the item
            $item = $this->findItemByCode($itemType, $itemCode);
            if (! $item) {
                $errors[] = "Row {$rowNumber}: Item not found with code '{$itemCode}'";

                continue;
            }

            // Find the NHIS tariff
            $nhisTariff = NhisTariff::where('nhis_code', $nhisCode)->first();
            if (! $nhisTariff) {
                $errors[] = "Row {$rowNumber}: NHIS tariff not found with code '{$nhisCode}'";

                continue;
            }

            // Create or update mapping
            $mapping = NhisItemMapping::updateOrCreate(
                [
                    'item_type' => $itemType,
                    'item_id' => $item->id,
                ],
                [
                    'item_code' => $itemCode,
                    'nhis_tariff_id' => $nhisTariff->id,
                ]
            );

            if ($mapping->wasRecentlyCreated) {
                $imported++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        $message = "Import completed: {$imported} created, {$updated} updated.";

        if (! empty($errors)) {
            $message .= ' Some rows had errors: '.implode('; ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= ' and '.(count($errors) - 3).' more.';
            }
        }

        return redirect()
            ->route('admin.nhis-mappings.index')
            ->with('success', $message);
    }

    /**
     * List unmapped items.
     */
    public function unmapped(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', NhisItemMapping::class);

        $itemType = $request->input('item_type', 'drug');
        $search = $request->input('search');

        $unmappedItems = $this->getUnmappedItems($itemType, $search);

        if ($request->wantsJson()) {
            return response()->json([
                'items' => $unmappedItems,
            ]);
        }

        return Inertia::render('Admin/NhisMappings/Unmapped', [
            'items' => $unmappedItems,
            'filters' => [
                'item_type' => $itemType,
                'search' => $search,
            ],
            'itemTypes' => ['drug', 'lab_service', 'procedure', 'consumable'],
        ]);
    }

    /**
     * Export unmapped items to CSV for mapping.
     */
    public function exportUnmapped(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', NhisItemMapping::class);

        $itemType = $request->input('item_type');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\UnmappedItemsExport($itemType),
            'unmapped_items_for_nhis_mapping.xlsx'
        );
    }

    /**
     * Export mapped items to CSV for backup/audit.
     */
    public function exportMapped(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', NhisItemMapping::class);

        $itemType = $request->input('item_type');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\MappedItemsExport($itemType),
            'nhis_mappings_export.xlsx'
        );
    }

    /**
     * Get the item code for a given item type and ID.
     */
    protected function getItemCode(string $itemType, int $itemId): string
    {
        $codeField = NhisItemMapping::getCodeFieldForType($itemType);

        return match ($itemType) {
            'drug', 'consumable' => Drug::findOrFail($itemId)->{$codeField},
            'lab_service' => LabService::findOrFail($itemId)->{$codeField},
            'procedure' => MinorProcedureType::findOrFail($itemId)->{$codeField},
            default => throw new \InvalidArgumentException("Invalid item type: {$itemType}"),
        };
    }

    /**
     * Find an item by its code.
     */
    protected function findItemByCode(string $itemType, string $itemCode): ?object
    {
        $codeField = NhisItemMapping::getCodeFieldForType($itemType);

        return match ($itemType) {
            'drug', 'consumable' => Drug::where($codeField, $itemCode)->first(),
            'lab_service' => LabService::where($codeField, $itemCode)->first(),
            'procedure' => MinorProcedureType::where($codeField, $itemCode)->first(),
            default => null,
        };
    }

    /**
     * Get unmapped items for a given type.
     */
    protected function getUnmappedItems(string $itemType, ?string $search): array
    {
        $mappedItemIds = NhisItemMapping::where('item_type', $itemType)
            ->pluck('item_id')
            ->toArray();

        $query = match ($itemType) {
            'drug', 'consumable' => Drug::query()
                ->whereNotIn('id', $mappedItemIds)
                ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('drug_code', 'like', "%{$search}%");
                })),
            'lab_service' => LabService::query()
                ->whereNotIn('id', $mappedItemIds)
                ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })),
            'procedure' => MinorProcedureType::query()
                ->whereNotIn('id', $mappedItemIds)
                ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })),
            default => collect(),
        };

        if ($query instanceof \Illuminate\Support\Collection) {
            return [];
        }

        $codeField = NhisItemMapping::getCodeFieldForType($itemType);

        return $query->select(['id', $codeField.' as code', 'name'])
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->toArray();
    }
}
