<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportNhisTariffRequest;
use App\Http\Requests\StoreNhisTariffRequest;
use App\Http\Requests\UpdateNhisTariffRequest;
use App\Http\Resources\NhisTariffResource;
use App\Models\NhisTariff;
use App\Services\NhisTariffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NhisTariffController extends Controller
{
    public function __construct(
        protected NhisTariffService $nhisTariffService
    ) {}

    /**
     * Display a listing of NHIS tariffs.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', NhisTariff::class);

        $perPage = $request->integer('per_page', 25);

        $tariffs = NhisTariff::query()
            ->search($request->input('search'))
            ->byCategory($request->input('category'))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/NhisTariffs/Index', [
            'tariffs' => [
                'data' => NhisTariffResource::collection($tariffs->items())->resolve(),
                'current_page' => $tariffs->currentPage(),
                'from' => $tariffs->firstItem(),
                'last_page' => $tariffs->lastPage(),
                'per_page' => $tariffs->perPage(),
                'to' => $tariffs->lastItem(),
                'total' => $tariffs->total(),
                'links' => $tariffs->linkCollection()->toArray(),
            ],
            'filters' => [
                'search' => $request->input('search'),
                'category' => $request->input('category'),
                'active_only' => $request->boolean('active_only'),
            ],
            'categories' => ['medicine', 'lab', 'procedure', 'consultation', 'consumable'],
        ]);
    }

    /**
     * Store a newly created NHIS tariff.
     */
    public function store(StoreNhisTariffRequest $request): RedirectResponse
    {
        $this->authorize('create', NhisTariff::class);

        NhisTariff::create($request->validated());

        return redirect()
            ->route('admin.nhis-tariffs.index')
            ->with('success', 'NHIS tariff created successfully.');
    }

    /**
     * Update the specified NHIS tariff.
     */
    public function update(UpdateNhisTariffRequest $request, NhisTariff $nhisTariff): RedirectResponse
    {
        $this->authorize('update', $nhisTariff);

        $nhisTariff->update($request->validated());

        return redirect()
            ->route('admin.nhis-tariffs.index')
            ->with('success', 'NHIS tariff updated successfully.');
    }

    /**
     * Remove the specified NHIS tariff.
     */
    public function destroy(NhisTariff $nhisTariff): RedirectResponse
    {
        $this->authorize('delete', $nhisTariff);

        // Check if tariff has any mappings
        if ($nhisTariff->itemMappings()->exists()) {
            return back()->with('error', 'Cannot delete tariff with existing item mappings.');
        }

        $nhisTariff->delete();

        return redirect()
            ->route('admin.nhis-tariffs.index')
            ->with('success', 'NHIS tariff deleted successfully.');
    }

    /**
     * Import NHIS tariffs from a file.
     */
    public function import(ImportNhisTariffRequest $request): RedirectResponse
    {
        $this->authorize('create', NhisTariff::class);

        $result = $this->nhisTariffService->importTariffs($request->file('file'));

        if (! $result['success']) {
            return back()->with('error', 'Import failed: '.implode(', ', $result['errors']));
        }

        $message = "Import completed: {$result['imported']} created, {$result['updated']} updated.";

        if (! empty($result['errors'])) {
            $message .= ' Some rows had errors: '.implode('; ', array_slice($result['errors'], 0, 3));
        }

        return redirect()
            ->route('admin.nhis-tariffs.index')
            ->with('success', $message);
    }

    /**
     * Search NHIS tariffs for dropdown (JSON response).
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NhisTariff::class);

        $tariffs = $this->nhisTariffService->searchTariffs(
            $request->input('search'),
            $request->input('category'),
            $request->integer('limit', 50)
        );

        return response()->json([
            'tariffs' => NhisTariffResource::collection($tariffs),
        ]);
    }
}
