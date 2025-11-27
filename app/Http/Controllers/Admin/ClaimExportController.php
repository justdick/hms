<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimBatch;
use App\Services\NhisXmlExportService;
use Illuminate\Http\Response;

/**
 * Controller for exporting claim batches to XML format for NHIA submission.
 *
 * _Requirements: 15.1, 15.4, 15.5_
 */
class ClaimExportController extends Controller
{
    public function __construct(
        protected NhisXmlExportService $nhisXmlExportService
    ) {}

    /**
     * Export a claim batch to XML format for NHIA submission.
     *
     * Generates an NHIA-compliant XML file containing all claims in the batch,
     * records the export timestamp, and returns the file for download.
     *
     * @param  ClaimBatch  $batch  The batch to export
     * @return Response The XML file download response
     *
     * _Requirements: 15.1, 15.4, 15.5_
     */
    public function exportXml(ClaimBatch $batch): Response
    {
        $this->authorize('export', $batch);

        // Load all necessary relationships for XML generation
        $batch->load([
            'batchItems.insuranceClaim.patient',
            'batchItems.insuranceClaim.claimDiagnoses.diagnosis',
            'batchItems.insuranceClaim.items.nhisTariff',
            'batchItems.insuranceClaim.gdrgTariff',
        ]);

        // Generate the XML using the service
        $xml = $this->nhisXmlExportService->generateXml($batch);

        // Record export timestamp (_Requirements: 15.5_)
        $batch->exported_at = now();
        $batch->save();

        // Generate filename with batch number
        $filename = "nhis-batch-{$batch->batch_number}.xml";

        // Return the XML file for download (_Requirements: 15.4_)
        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
