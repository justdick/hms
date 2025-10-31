<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInsuranceProviderRequest;
use App\Http\Requests\UpdateInsuranceProviderRequest;
use App\Http\Resources\InsuranceProviderResource;
use App\Models\InsuranceProvider;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceProviderController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', InsuranceProvider::class);

        $providers = InsuranceProvider::withCount('plans')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Admin/Insurance/Providers/Index', [
            'providers' => InsuranceProviderResource::collection($providers),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', InsuranceProvider::class);

        return Inertia::render('Admin/Insurance/Providers/Create');
    }

    public function store(StoreInsuranceProviderRequest $request): RedirectResponse
    {
        $this->authorize('create', InsuranceProvider::class);

        $provider = InsuranceProvider::create($request->validated());

        return redirect()
            ->route('admin.insurance.providers.show', $provider)
            ->with('success', 'Insurance provider created successfully.');
    }

    public function show(InsuranceProvider $provider): Response
    {
        $this->authorize('view', $provider);

        $provider->load(['plans' => function ($query) {
            $query->withCount('coverageRules', 'tariffs')->orderBy('plan_name');
        }]);

        return Inertia::render('Admin/Insurance/Providers/Show', [
            'provider' => new InsuranceProviderResource($provider),
        ]);
    }

    public function edit(InsuranceProvider $provider): Response
    {
        $this->authorize('update', $provider);

        return Inertia::render('Admin/Insurance/Providers/Edit', [
            'provider' => new InsuranceProviderResource($provider),
        ]);
    }

    public function update(UpdateInsuranceProviderRequest $request, InsuranceProvider $provider): RedirectResponse
    {
        $this->authorize('update', $provider);

        $provider->update($request->validated());

        return redirect()
            ->route('admin.insurance.providers.show', $provider)
            ->with('success', 'Insurance provider updated successfully.');
    }

    public function destroy(InsuranceProvider $provider): RedirectResponse
    {
        $this->authorize('delete', $provider);

        if ($provider->plans()->exists()) {
            return back()->with('error', 'Cannot delete provider with existing plans.');
        }

        $provider->delete();

        return redirect()
            ->route('admin.insurance.providers.index')
            ->with('success', 'Insurance provider deleted successfully.');
    }
}
