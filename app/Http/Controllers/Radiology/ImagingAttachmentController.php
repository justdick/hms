<?php

namespace App\Http\Controllers\Radiology;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImagingAttachmentRequest;
use App\Models\ImagingAttachment;
use App\Models\LabOrder;
use App\Services\ImagingStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImagingAttachmentController extends Controller
{
    public function __construct(
        protected ImagingStorageService $storageService
    ) {}

    /**
     * Store a new imaging attachment.
     */
    public function store(StoreImagingAttachmentRequest $request, LabOrder $labOrder): RedirectResponse
    {
        // Ensure this is an imaging order
        if (! $labOrder->isImaging()) {
            return back()->with('error', 'This is not an imaging order.');
        }

        $file = $request->file('file');

        // Validate file using the storage service
        $errors = $this->storageService->validateFile($file);
        if (! empty($errors)) {
            return back()->withErrors(['file' => $errors[0]]);
        }

        // Store the file
        try {
            $filePath = $this->storageService->store($file, $labOrder);
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Failed to store file. Please try again.');
        }

        // Create the attachment record
        ImagingAttachment::create([
            'lab_order_id' => $labOrder->id,
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $request->description,
            'is_external' => false,
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        // If the order is still in 'ordered' status, mark it as in progress
        if ($labOrder->status === 'ordered') {
            $labOrder->markInProgress();
        }

        return back()->with('success', 'Image uploaded successfully.');
    }

    /**
     * Delete an imaging attachment.
     */
    public function destroy(ImagingAttachment $imagingAttachment): RedirectResponse
    {
        $labOrder = $imagingAttachment->labOrder;

        Gate::authorize('uploadImages-radiology', $labOrder);

        // Don't allow deletion if the order is completed
        if ($labOrder->status === 'completed') {
            return back()->with('error', 'Cannot delete attachments from completed orders.');
        }

        // Delete the file from storage
        $this->storageService->delete($imagingAttachment->file_path);

        // Delete the attachment record
        $imagingAttachment->delete();

        return back()->with('success', 'Image deleted successfully.');
    }

    /**
     * Download an imaging attachment.
     */
    public function download(ImagingAttachment $imagingAttachment): StreamedResponse|RedirectResponse
    {
        $labOrder = $imagingAttachment->labOrder;

        Gate::authorize('viewWorklist-radiology');

        // Check if file exists
        if (! $this->storageService->exists($imagingAttachment->file_path)) {
            return back()->with('error', 'File not found.');
        }

        $disk = $this->storageService->getDisk();

        return Storage::disk($disk)->download(
            $imagingAttachment->file_path,
            $imagingAttachment->file_name
        );
    }

    /**
     * View/serve an imaging attachment inline.
     */
    public function view(ImagingAttachment $imagingAttachment): StreamedResponse|RedirectResponse
    {
        Gate::authorize('viewWorklist-radiology');

        // Check if file exists
        if (! $this->storageService->exists($imagingAttachment->file_path)) {
            return back()->with('error', 'File not found.');
        }

        $disk = $this->storageService->getDisk();

        return Storage::disk($disk)->response(
            $imagingAttachment->file_path,
            $imagingAttachment->file_name,
            [
                'Content-Type' => $imagingAttachment->file_type,
            ]
        );
    }
}
