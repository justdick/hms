<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Ward;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WardController extends Controller
{
    public function index()
    {
        $wards = Ward::with(['beds:id,ward_id,status'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Ward/Index', [
            'wards' => $wards
        ]);
    }

    public function create()
    {
        return Inertia::render('Ward/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:wards,code',
            'description' => 'nullable|string|max:500',
            'bed_count' => 'required|integer|min:1|max:100',
        ]);

        $ward = Ward::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'total_beds' => $request->bed_count,
            'available_beds' => $request->bed_count,
            'is_active' => true,
        ]);

        // Create beds for the ward
        for ($i = 1; $i <= $request->bed_count; $i++) {
            Bed::create([
                'bed_number' => str_pad($i, 2, '0', STR_PAD_LEFT),
                'ward_id' => $ward->id,
                'status' => 'available',
                'type' => 'standard',
                'is_active' => true,
            ]);
        }

        return redirect()->route('wards.index')
            ->with('success', 'Ward created successfully with ' . $request->bed_count . ' beds.');
    }

    public function show(Ward $ward)
    {
        $ward->load([
            'beds:id,ward_id,bed_number,status,type,is_active',
            'admissions' => function ($query) {
                $query->where('status', 'admitted')
                    ->with(['patient:id,first_name,last_name', 'attendingDoctor:id,name'])
                    ->orderBy('admitted_at', 'desc');
            }
        ]);

        return Inertia::render('Ward/Show', [
            'ward' => $ward
        ]);
    }

    public function edit(Ward $ward)
    {
        return Inertia::render('Ward/Edit', [
            'ward' => $ward
        ]);
    }

    public function update(Request $request, Ward $ward)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:wards,code,' . $ward->id,
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $ward->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('wards.index')
            ->with('success', 'Ward updated successfully.');
    }

    public function destroy(Ward $ward)
    {
        // Check if ward has active admissions
        $activeAdmissions = $ward->admissions()->where('status', 'admitted')->count();

        if ($activeAdmissions > 0) {
            return redirect()->back()
                ->withErrors(['ward' => 'Cannot delete ward with active patient admissions.']);
        }

        // Delete associated beds
        $ward->beds()->delete();

        // Delete ward
        $ward->delete();

        return redirect()->route('wards.index')
            ->with('success', 'Ward deleted successfully.');
    }

    public function toggleStatus(Ward $ward)
    {
        $ward->update(['is_active' => !$ward->is_active]);

        $status = $ward->is_active ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "Ward {$status} successfully.");
    }
}
