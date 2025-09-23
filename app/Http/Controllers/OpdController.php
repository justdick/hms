<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientCheckin;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpdController extends Controller
{
    public function index()
    {
        // Check permission
        abort_unless(auth()->user()->can('opd.access'), 403);

        $todayCheckins = PatientCheckin::with(['patient', 'department'])
            ->today()
            ->orderBy('checked_in_at', 'desc')
            ->get();

        $departments = Department::active()->opd()->get();

        return Inertia::render('Checkin/Index', [
            'todayCheckins' => $todayCheckins,
            'departments' => $departments,
        ]);
    }

    public function dashboard()
    {
        abort_unless(auth()->user()->can('opd.access'), 403);

        return $this->index();
    }
}
