<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Dispensing;
use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\Prescription;
use Inertia\Inertia;
use Inertia\Response;

class PharmacyController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Drug::class);
        $stats = [
            'pending_prescriptions' => Prescription::where('status', 'prescribed')->count(),
            'dispensed_today' => Dispensing::today()->count(),
            'low_stock_drugs' => Drug::whereHas('batches', function ($query) {
                $query->where('quantity_remaining', '>', 0)
                    ->where('expiry_date', '>', now());
            })->get()->filter(fn ($drug) => $drug->total_stock <= $drug->minimum_stock_level)->count(),
            'expiring_soon' => DrugBatch::expiringSoon()->available()->count(),
        ];

        $pendingPrescriptions = Prescription::with([
            'consultation.patientCheckin.patient',
            'drug:id,name,form,unit_type',
        ])
            ->where('status', 'prescribed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $lowStockDrugs = Drug::with(['batches' => function ($query) {
            $query->available();
        }])
            ->active()
            ->get()
            ->filter(fn ($drug) => $drug->total_stock <= $drug->minimum_stock_level)
            ->take(5);

        $expiringBatches = DrugBatch::with('drug:id,name')
            ->expiringSoon()
            ->available()
            ->orderBy('expiry_date')
            ->limit(5)
            ->get();

        return Inertia::render('Pharmacy/Index', [
            'stats' => $stats,
            'pendingPrescriptions' => $pendingPrescriptions,
            'lowStockDrugs' => $lowStockDrugs,
            'expiringBatches' => $expiringBatches,
        ]);
    }
}
