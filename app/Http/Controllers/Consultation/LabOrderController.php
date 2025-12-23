<?php

namespace App\Http\Controllers\Consultation;

use App\Events\LabTestOrdered;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalImageUploadRequest;
use App\Models\Consultation;
use App\Models\ImagingAttachment;
use App\Models\LabOrder;
use App\Models\LabService;
use App\Services\ImagingStorageService;
use Illuminate\Http\Request;

class LabOrderController extends Controller
{
    public function __construct(
        protected ImagingStorageService $storageService
    ) {}

    public function store(Request $request, Consultation $consultation)
    {
        $this->authorize('create', [LabOrder::class, $consultation]);

        $request->validate([
            'lab_service_id' => 'required|exists:lab_services,id',
            'priority' => 'in:routine,urgent,stat',
            'special_instructions' => 'nullable|string|max:500',
        ]);

        $labService = LabService::findOrFail($request->lab_service_id);

        if (! $labService->is_active) {
            return back()->withErrors([
                'lab_service_id' => 'This lab service is not currently available.',
            ]);
        }

        // Check if this lab is already ordered for this consultation
        $existingOrder = $consultation->labOrders()
            ->where('lab_service_id', $request->lab_service_id)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existingOrder) {
            return back()->withErrors([
                'lab_service_id' => 'This lab test has already been ordered for this consultation.',
            ]);
        }

        $labOrder = $consultation->labOrders()->create([
            'lab_service_id' => $request->lab_service_id,
            'ordered_by' => $request->user()->id,
            'ordered_at' => now(),
            'status' => 'ordered',
            'priority' => $request->priority ?? 'routine',
            'special_instructions' => $request->special_instructions,
        ]);

        $labOrder->load('labService');

        // Fire lab test ordered event for billing
        event(new LabTestOrdered($labOrder));

        return back()->with('success', 'Lab test ordered successfully.');
    }

    public function update(Request $request, Consultation $consultation, LabOrder $labOrder)
    {
        $this->authorize('update', $labOrder);

        // Only allow updates to orders that are still in ordered status
        if (! in_array($labOrder->status, ['ordered'])) {
            return back()->withErrors([
                'status' => 'This lab order can no longer be modified.',
            ]);
        }

        $request->validate([
            'priority' => 'in:routine,urgent,stat',
            'special_instructions' => 'nullable|string|max:500',
        ]);

        $labOrder->update($request->only(['priority', 'special_instructions']));

        return back()->with('success', 'Lab order updated successfully.');
    }

    public function cancel(Request $request, Consultation $consultation, LabOrder $labOrder)
    {
        $this->authorize('delete', $labOrder);

        // Only allow cancellation of orders that haven't been processed
        if (! in_array($labOrder->status, ['ordered'])) {
            return back()->withErrors([
                'status' => 'This lab order can no longer be cancelled.',
            ]);
        }

        $labOrder->update(['status' => 'cancelled']);

        return back()->with('success', 'Lab order cancelled successfully.');
    }

    public function destroy(Consultation $consultation, LabOrder $labOrder)
    {
        $this->authorize('delete', $labOrder);

        // Only allow deletion of orders that haven't been processed
        if (! in_array($labOrder->status, ['ordered', 'cancelled'])) {
            return back()->withErrors([
                'status' => 'This lab order cannot be deleted because it has already been processed.',
            ]);
        }

        // Delete the lab order (cascade will handle charge and claim items)
        $labOrder->delete();

        return back()->with('success', 'Lab order deleted successfully.');
    }

    public function index(Request $request)
    {
        // For lab technicians - show all pending lab orders
        $labOrders = LabOrder::with([
            'consultation.patientCheckin.patient:id,first_name,last_name,date_of_birth',
            'consultation.patientCheckin.department:id,name',
            'consultation.doctor:id,name',
            'labService:id,name,code,sample_type,turnaround_time',
        ])
            ->pending()
            ->orderBy('priority', 'desc') // stat, urgent, routine
            ->orderBy('ordered_at')
            ->get();

        return response()->json([
            'lab_orders' => $labOrders,
        ]);
    }

    public function updateStatus(Request $request, LabOrder $labOrder)
    {
        // This would typically be restricted to lab technicians
        $request->validate([
            'status' => 'required|in:sample_collected,in_progress,completed',
            'result_values' => 'array|nullable',
            'result_notes' => 'string|nullable|max:1000',
        ]);

        $updateData = [
            'status' => $request->status,
        ];

        if ($request->status === 'sample_collected') {
            $updateData['sample_collected_at'] = now();
        }

        if ($request->status === 'completed') {
            $updateData['result_entered_at'] = now();
            $updateData['result_values'] = $request->result_values;
            $updateData['result_notes'] = $request->result_notes;
        }

        $labOrder->update($updateData);

        return response()->json([
            'lab_order' => $labOrder->load('labService'),
            'message' => 'Lab order status updated successfully.',
        ]);
    }

    /**
     * Upload external imaging study to a consultation.
     * External images are non-billable and marked as is_external = true.
     */
    public function uploadExternalImage(ExternalImageUploadRequest $request, Consultation $consultation)
    {
        $labService = LabService::findOrFail($request->lab_service_id);

        // Verify this is an imaging service
        if (! $labService->is_imaging) {
            return back()->withErrors([
                'lab_service_id' => 'External image uploads are only allowed for imaging services.',
            ]);
        }

        // Create a lab order for the external image (no billing event fired)
        $labOrder = $consultation->labOrders()->create([
            'lab_service_id' => $request->lab_service_id,
            'ordered_by' => $request->user()->id,
            'ordered_at' => now(),
            'status' => 'completed', // External images are already completed
            'priority' => 'routine',
            'special_instructions' => 'External imaging study from '.$request->external_facility_name,
            'result_entered_at' => now(),
            'result_notes' => $request->notes,
        ]);

        // Process uploaded files
        $files = $request->file('files');
        $descriptions = $request->input('descriptions', []);

        foreach ($files as $index => $file) {
            // Validate file using the storage service
            $errors = $this->storageService->validateFile($file);
            if (! empty($errors)) {
                // If any file fails validation, delete the lab order and return error
                $labOrder->delete();

                return back()->withErrors(['files.'.$index => $errors[0]]);
            }

            // Store the file
            try {
                $filePath = $this->storageService->store($file, $labOrder);
            } catch (\RuntimeException $e) {
                // If storage fails, delete the lab order and return error
                $labOrder->delete();

                return back()->with('error', 'Failed to store file. Please try again.');
            }

            // Create the attachment record - marked as external (non-billable)
            ImagingAttachment::create([
                'lab_order_id' => $labOrder->id,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'description' => $descriptions[$index] ?? null,
                'is_external' => true, // Mark as external (non-billable)
                'external_facility_name' => $request->external_facility_name,
                'external_study_date' => $request->external_study_date,
                'uploaded_by' => $request->user()->id,
                'uploaded_at' => now(),
            ]);
        }

        // NOTE: No LabTestOrdered event is fired for external images
        // This ensures no billing charge is created (Requirement 4.5)

        return back()->with('success', 'External imaging study uploaded successfully.');
    }
}
