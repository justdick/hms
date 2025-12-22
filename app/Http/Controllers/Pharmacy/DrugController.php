<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DrugController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Drug::class);

        $drugs = Drug::with(['batches' => function ($query) {
            $query->available()->orderBy('expiry_date');
        }])
            ->withCount(['batches as total_batches'])
            ->active()
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Pharmacy/Drugs/Index', [
            'drugs' => $drugs,
        ]);
    }

    public function inventory(Request $request): Response
    {
        $this->authorize('viewAny', Drug::class);

        $perPage = $request->query('per_page', 5);
        $search = $request->query('search');
        $category = $request->query('category');
        $stockStatus = $request->query('stock_status');

        $query = Drug::with(['batches' => function ($query) {
            $query->available()->orderBy('expiry_date');
        }])->active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('category', 'LIKE', "%{$search}%")
                    ->orWhere('form', 'LIKE', "%{$search}%")
                    ->orWhere('drug_code', 'LIKE', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        $drugs = $query->orderBy('name')->paginate($perPage);

        // Transform the paginated data
        $drugs->getCollection()->transform(function ($drug) {
            return [
                'id' => $drug->id,
                'name' => $drug->name,
                'category' => $drug->category,
                'form' => $drug->form,
                'unit_type' => $drug->unit_type,
                'unit_price' => $drug->unit_price,
                'total_stock' => $drug->total_stock,
                'minimum_stock_level' => $drug->minimum_stock_level,
                'is_low_stock' => $drug->isLowStock(),
                'batches_count' => $drug->batches->count(),
                'next_expiry' => $drug->batches->first()?->expiry_date,
            ];
        });

        // Filter by stock status after transformation (needs computed values)
        if ($stockStatus) {
            $filtered = $drugs->getCollection()->filter(function ($drug) use ($stockStatus) {
                if ($stockStatus === 'out_of_stock') {
                    return $drug['total_stock'] === 0;
                }
                if ($stockStatus === 'low_stock') {
                    return $drug['is_low_stock'] && $drug['total_stock'] > 0;
                }
                if ($stockStatus === 'in_stock') {
                    return ! $drug['is_low_stock'] && $drug['total_stock'] > 0;
                }

                return true;
            });
            $drugs->setCollection($filtered->values());
        }

        // Get categories for filter dropdown
        $categories = Drug::active()->distinct()->pluck('category')->filter()->sort()->values();

        // Stats for the page
        $allDrugs = Drug::with(['batches' => fn ($q) => $q->available()])->active()->get();
        $stats = [
            'total' => $allDrugs->count(),
            'low_stock' => $allDrugs->filter(fn ($d) => $d->isLowStock())->count(),
            'out_of_stock' => $allDrugs->filter(fn ($d) => $d->total_stock === 0)->count(),
            'total_value' => $allDrugs->sum(fn ($d) => $d->total_stock * ($d->unit_price ?? 0)),
        ];

        return Inertia::render('Pharmacy/Inventory/Index', [
            'drugs' => $drugs,
            'categories' => $categories,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'stock_status' => $stockStatus,
            ],
        ]);
    }

    public function lowStock(): Response
    {
        $this->authorize('viewAny', Drug::class);

        $lowStockDrugs = Drug::with(['batches' => function ($query) {
            $query->available()->orderBy('expiry_date');
        }])
            ->active()
            ->get()
            ->filter(fn ($drug) => $drug->isLowStock())
            ->values();

        return Inertia::render('Pharmacy/Inventory/LowStock', [
            'drugs' => $lowStockDrugs,
        ]);
    }

    public function expiring(): Response
    {
        $this->authorize('viewAny', Drug::class);

        $expiringBatches = DrugBatch::with(['drug:id,name,form,unit_type', 'supplier:id,name'])
            ->expiringSoon()
            ->available()
            ->orderBy('expiry_date')
            ->paginate(20);

        return Inertia::render('Pharmacy/Inventory/Expiring', [
            'batches' => $expiringBatches,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Drug::class);

        $categories = Drug::distinct()->pluck('category')->filter()->sort()->values();
        $suppliers = Supplier::active()->orderBy('name')->get();

        return Inertia::render('Pharmacy/Drugs/Create', [
            'categories' => $categories,
            'suppliers' => $suppliers,
            'canManageNhisSettings' => auth()->user()->can('drugs.manage-nhis-settings'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Drug::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'drug_code' => 'required|string|unique:drugs,drug_code|max:50',
            'category' => 'required|string',
            'form' => 'required|string',
            'strength' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'unit_type' => 'required|string',
            'bottle_size' => 'nullable|integer|min:1',
            'minimum_stock_level' => 'required|integer|min:0',
            'maximum_stock_level' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'nhis_claim_qty_as_one' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        // Only allow nhis_claim_qty_as_one if user has permission
        if (isset($validated['nhis_claim_qty_as_one']) && $validated['nhis_claim_qty_as_one']) {
            if (! auth()->user()->can('drugs.manage-nhis-settings')) {
                unset($validated['nhis_claim_qty_as_one']);
            }
        }
        $validated['nhis_claim_qty_as_one'] = $validated['nhis_claim_qty_as_one'] ?? false;
        // New drugs default to unpriced (null) - price is set via Pricing Dashboard
        $validated['unit_price'] = null;

        $drug = Drug::create($validated);

        return redirect()->route('pharmacy.drugs.show', $drug)
            ->with('success', 'Drug created successfully.');
    }

    public function show(Drug $drug): Response
    {
        $this->authorize('view', $drug);

        $drug->load(['batches.supplier']);

        return Inertia::render('Pharmacy/Drugs/Show', [
            'drug' => $drug,
        ]);
    }

    public function edit(Drug $drug): Response
    {
        $this->authorize('update', $drug);

        $categories = Drug::distinct()->pluck('category')->filter()->sort()->values();

        return Inertia::render('Pharmacy/Drugs/Edit', [
            'drug' => $drug,
            'categories' => $categories,
            'canManageNhisSettings' => auth()->user()->can('drugs.manage-nhis-settings'),
        ]);
    }

    public function update(Request $request, Drug $drug)
    {
        $this->authorize('update', $drug);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'drug_code' => 'required|string|max:50|unique:drugs,drug_code,'.$drug->id,
            'category' => 'required|string',
            'form' => 'required|string',
            'strength' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'unit_type' => 'required|string',
            'bottle_size' => 'nullable|integer|min:1',
            'minimum_stock_level' => 'required|integer|min:0',
            'maximum_stock_level' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'nhis_claim_qty_as_one' => 'boolean',
        ]);

        // Only allow nhis_claim_qty_as_one changes if user has permission
        if (isset($validated['nhis_claim_qty_as_one'])) {
            if (! auth()->user()->can('drugs.manage-nhis-settings')) {
                // Keep the existing value if user doesn't have permission
                $validated['nhis_claim_qty_as_one'] = $drug->nhis_claim_qty_as_one;
            }
        }

        // Note: unit_price is managed via Pricing Dashboard, not this form

        $drug->update($validated);

        return redirect()->route('pharmacy.drugs.show', $drug)
            ->with('success', 'Drug updated successfully.');
    }

    public function destroy(Drug $drug)
    {
        $this->authorize('delete', $drug);

        if ($drug->batches()->exists()) {
            return back()->with('error', 'Cannot delete drug with existing batches.');
        }

        $drug->delete();

        return redirect()->route('pharmacy.drugs.index')
            ->with('success', 'Drug deleted successfully.');
    }

    public function batches(Drug $drug): Response
    {
        $this->authorize('manageBatches', Drug::class);

        $batches = $drug->batches()
            ->with('supplier:id,name')
            ->orderBy('expiry_date')
            ->paginate(20);

        $suppliers = \App\Models\Supplier::all();

        return Inertia::render('Pharmacy/Drugs/Batches', [
            'drug' => $drug,
            'batches' => $batches,
            'suppliers' => $suppliers,
        ]);
    }

    public function storeBatch(Request $request, Drug $drug)
    {
        $this->authorize('manageBatches', Drug::class);

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'batch_number' => 'required|string|max:255',
            'expiry_date' => 'required|date|after:today',
            'manufacture_date' => 'nullable|date|before_or_equal:today',
            'quantity_received' => 'required|integer|min:1',
            'cost_per_unit' => 'required|numeric|min:0',
            'selling_price_per_unit' => 'required|numeric|min:0',
            'received_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string',
        ]);

        $validated['drug_id'] = $drug->id;
        $validated['quantity_remaining'] = $validated['quantity_received'];

        // Check for duplicate batch
        $existingBatch = DrugBatch::where('drug_id', $drug->id)
            ->where('batch_number', $validated['batch_number'])
            ->where('supplier_id', $validated['supplier_id'])
            ->first();

        if ($existingBatch) {
            return back()->with('error', 'Batch number already exists for this drug and supplier.');
        }

        DrugBatch::create($validated);

        return back()->with('success', 'Drug batch added successfully.');
    }
}
