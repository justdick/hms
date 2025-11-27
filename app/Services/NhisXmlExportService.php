<?php

namespace App\Services;

use App\Models\ClaimBatch;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimDiagnosis;
use App\Models\InsuranceClaimItem;
use App\Models\SystemConfiguration;
use DOMDocument;
use DOMElement;

/**
 * Service for generating NHIA-compliant XML exports for claim batches.
 *
 * _Requirements: 15.1, 15.2, 15.3_
 */
class NhisXmlExportService
{
    protected DOMDocument $dom;

    /**
     * Generate NHIA-compliant XML for a claim batch.
     *
     * @param  ClaimBatch  $batch  The batch to export
     * @return string The generated XML string
     *
     * _Requirements: 15.1, 15.2_
     */
    public function generateXml(ClaimBatch $batch): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        // Create root element
        $root = $this->dom->createElement('NHIAClaimBatch');
        $root->setAttribute('xmlns', 'http://nhia.gov.gh/claims');
        $root->setAttribute('version', '1.0');
        $this->dom->appendChild($root);

        // Add facility information
        $facilityElement = $this->createFacilityElement();
        $root->appendChild($facilityElement);

        // Add batch details
        $batchElement = $this->createBatchElement($batch);
        $root->appendChild($batchElement);

        // Add claims
        $claimsElement = $this->dom->createElement('Claims');
        $claimsElement->setAttribute('count', (string) $batch->total_claims);

        // Load batch items with related claims
        $batchItems = $batch->batchItems()
            ->with([
                'insuranceClaim.patient',
                'insuranceClaim.gdrgTariff',
                'insuranceClaim.claimDiagnoses.diagnosis',
                'insuranceClaim.items.nhisTariff',
            ])
            ->get();

        foreach ($batchItems as $batchItem) {
            if ($batchItem->insuranceClaim) {
                $claimElement = $this->generateClaimElement($batchItem->insuranceClaim);
                $claimsElement->appendChild($claimElement);
            }
        }

        $root->appendChild($claimsElement);

        return $this->dom->saveXML();
    }

    /**
     * Create the facility information element.
     */
    protected function createFacilityElement(): DOMElement
    {
        $facility = $this->dom->createElement('Facility');

        // Get facility code from system configuration
        $facilityCode = SystemConfiguration::get('nhis_facility_code', 'FACILITY-001');
        $facilityName = SystemConfiguration::get('facility_name', 'Hospital');

        $facility->appendChild($this->dom->createElement('FacilityCode', $this->escapeXml($facilityCode)));
        $facility->appendChild($this->dom->createElement('FacilityName', $this->escapeXml($facilityName)));

        return $facility;
    }

    /**
     * Create the batch details element.
     */
    protected function createBatchElement(ClaimBatch $batch): DOMElement
    {
        $batchElement = $this->dom->createElement('BatchDetails');

        $batchElement->appendChild($this->dom->createElement('BatchNumber', $this->escapeXml($batch->batch_number)));
        $batchElement->appendChild($this->dom->createElement('BatchName', $this->escapeXml($batch->name)));
        $batchElement->appendChild($this->dom->createElement('SubmissionPeriod', $batch->submission_period->format('Y-m')));
        $batchElement->appendChild($this->dom->createElement('TotalClaims', (string) $batch->total_claims));
        $batchElement->appendChild($this->dom->createElement('TotalAmount', number_format((float) $batch->total_amount, 2, '.', '')));
        $batchElement->appendChild($this->dom->createElement('GeneratedAt', now()->toIso8601String()));

        return $batchElement;
    }

    /**
     * Generate a claim element for the XML.
     *
     * @param  InsuranceClaim  $claim  The claim to generate XML for
     * @return DOMElement The claim element
     *
     * _Requirements: 15.3_
     */
    public function generateClaimElement(InsuranceClaim $claim): DOMElement
    {
        $claimElement = $this->dom->createElement('Claim');
        $claimElement->setAttribute('id', $claim->claim_check_code ?? '');

        // Patient information - Get NHIS member ID from claim's membership_id or PatientInsurance
        $patientElement = $this->dom->createElement('Patient');
        $nhisMemberId = $claim->membership_id ?? $claim->patientInsurance?->membership_id ?? '';
        $patientElement->appendChild($this->dom->createElement('NhisMemberId', $this->escapeXml($nhisMemberId)));
        $patientElement->appendChild($this->dom->createElement('Surname', $this->escapeXml($claim->patient_surname ?? '')));
        $patientElement->appendChild($this->dom->createElement('OtherNames', $this->escapeXml($claim->patient_other_names ?? '')));
        $patientElement->appendChild($this->dom->createElement('DateOfBirth', $claim->patient_dob?->format('Y-m-d') ?? ''));
        $patientElement->appendChild($this->dom->createElement('Gender', $this->escapeXml($claim->patient_gender ?? '')));
        $patientElement->appendChild($this->dom->createElement('FolderId', $this->escapeXml($claim->folder_id ?? '')));
        $claimElement->appendChild($patientElement);

        // Attendance details
        $attendanceElement = $this->dom->createElement('Attendance');
        $attendanceElement->appendChild($this->dom->createElement('DateOfAttendance', $claim->date_of_attendance?->format('Y-m-d') ?? ''));
        $attendanceElement->appendChild($this->dom->createElement('DateOfDischarge', $claim->date_of_discharge?->format('Y-m-d') ?? ''));
        $attendanceElement->appendChild($this->dom->createElement('TypeOfAttendance', $this->escapeXml($claim->type_of_attendance ?? '')));
        $attendanceElement->appendChild($this->dom->createElement('TypeOfService', $this->escapeXml($claim->type_of_service ?? '')));
        $attendanceElement->appendChild($this->dom->createElement('SpecialtyAttended', $this->escapeXml($claim->specialty_attended ?? '')));
        $attendanceElement->appendChild($this->dom->createElement('AttendingPrescriber', $this->escapeXml($claim->attending_prescriber ?? '')));
        $claimElement->appendChild($attendanceElement);

        // G-DRG information
        $gdrgElement = $this->dom->createElement('GDRG');
        $gdrgElement->appendChild($this->dom->createElement('Code', $this->escapeXml($claim->gdrgTariff?->code ?? $claim->c_drg_code ?? '')));
        $gdrgElement->appendChild($this->dom->createElement('Name', $this->escapeXml($claim->gdrgTariff?->name ?? '')));
        $gdrgElement->appendChild($this->dom->createElement('Amount', number_format((float) ($claim->gdrg_amount ?? 0), 2, '.', '')));
        $claimElement->appendChild($gdrgElement);

        // Diagnoses
        $diagnosesElement = $this->dom->createElement('Diagnoses');
        $claimDiagnoses = $claim->claimDiagnoses ?? collect();

        foreach ($claimDiagnoses as $claimDiagnosis) {
            $diagnosisElement = $this->createDiagnosisElement($claimDiagnosis);
            $diagnosesElement->appendChild($diagnosisElement);
        }

        // If no claim diagnoses, use primary diagnosis from claim
        if ($claimDiagnoses->isEmpty() && $claim->primary_diagnosis_code) {
            $primaryDiag = $this->dom->createElement('Diagnosis');
            $primaryDiag->setAttribute('isPrimary', 'true');
            $primaryDiag->appendChild($this->dom->createElement('ICD10Code', $this->escapeXml($claim->primary_diagnosis_code)));
            $primaryDiag->appendChild($this->dom->createElement('Description', $this->escapeXml($claim->primary_diagnosis_description ?? '')));
            $diagnosesElement->appendChild($primaryDiag);
        }

        $claimElement->appendChild($diagnosesElement);

        // Claim items
        $itemsElement = $this->dom->createElement('Items');
        $claimItems = $claim->items ?? collect();

        foreach ($claimItems as $item) {
            $itemElement = $this->generateItemElement($item);
            $itemsElement->appendChild($itemElement);
        }

        $claimElement->appendChild($itemsElement);

        // Totals
        $totalsElement = $this->dom->createElement('Totals');
        $totalsElement->appendChild($this->dom->createElement('TotalClaimAmount', number_format((float) ($claim->total_claim_amount ?? 0), 2, '.', '')));
        $totalsElement->appendChild($this->dom->createElement('InsuranceCoveredAmount', number_format((float) ($claim->insurance_covered_amount ?? 0), 2, '.', '')));
        $totalsElement->appendChild($this->dom->createElement('PatientCopayAmount', number_format((float) ($claim->patient_copay_amount ?? 0), 2, '.', '')));
        $claimElement->appendChild($totalsElement);

        return $claimElement;
    }

    /**
     * Create a diagnosis element.
     */
    protected function createDiagnosisElement(InsuranceClaimDiagnosis $claimDiagnosis): DOMElement
    {
        $diagnosisElement = $this->dom->createElement('Diagnosis');
        $diagnosisElement->setAttribute('isPrimary', $claimDiagnosis->is_primary ? 'true' : 'false');

        $diagnosis = $claimDiagnosis->diagnosis;
        $diagnosisElement->appendChild($this->dom->createElement('ICD10Code', $this->escapeXml($diagnosis?->icd_10 ?? $diagnosis?->code ?? '')));
        $diagnosisElement->appendChild($this->dom->createElement('Description', $this->escapeXml($diagnosis?->diagnosis ?? '')));

        return $diagnosisElement;
    }

    /**
     * Generate an item element for the XML.
     *
     * @param  InsuranceClaimItem  $item  The item to generate XML for
     * @return DOMElement The item element
     *
     * _Requirements: 15.3_
     */
    public function generateItemElement(InsuranceClaimItem $item): DOMElement
    {
        $itemElement = $this->dom->createElement('Item');
        $itemElement->setAttribute('type', $item->item_type ?? '');

        $itemElement->appendChild($this->dom->createElement('ItemDate', $item->item_date?->format('Y-m-d') ?? ''));
        $itemElement->appendChild($this->dom->createElement('NhisCode', $this->escapeXml($item->nhis_code ?? '')));
        $itemElement->appendChild($this->dom->createElement('HospitalCode', $this->escapeXml($item->code ?? '')));
        $itemElement->appendChild($this->dom->createElement('Description', $this->escapeXml($item->description ?? '')));
        $itemElement->appendChild($this->dom->createElement('Quantity', (string) ($item->quantity ?? 1)));
        $itemElement->appendChild($this->dom->createElement('UnitPrice', number_format((float) ($item->nhis_price ?? $item->unit_tariff ?? 0), 2, '.', '')));
        $itemElement->appendChild($this->dom->createElement('Subtotal', number_format((float) ($item->subtotal ?? 0), 2, '.', '')));
        $itemElement->appendChild($this->dom->createElement('IsCovered', $item->is_covered ? 'true' : 'false'));
        $itemElement->appendChild($this->dom->createElement('InsurancePays', number_format((float) ($item->insurance_pays ?? 0), 2, '.', '')));
        $itemElement->appendChild($this->dom->createElement('PatientPays', number_format((float) ($item->patient_pays ?? 0), 2, '.', '')));

        return $itemElement;
    }

    /**
     * Escape special XML characters.
     */
    protected function escapeXml(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Parse XML string back to structured data for validation.
     * This is useful for round-trip testing.
     *
     * @param  string  $xml  The XML string to parse
     * @return array The parsed data
     */
    public function parseXml(string $xml): array
    {
        $dom = new DOMDocument;
        $dom->loadXML($xml);

        $result = [
            'facility' => $this->parseFacilityElement($dom),
            'batch' => $this->parseBatchElement($dom),
            'claims' => $this->parseClaimsElement($dom),
        ];

        return $result;
    }

    /**
     * Parse facility element from XML.
     */
    protected function parseFacilityElement(DOMDocument $dom): array
    {
        $facility = $dom->getElementsByTagName('Facility')->item(0);
        if (! $facility) {
            return [];
        }

        return [
            'facility_code' => $this->getElementText($facility, 'FacilityCode'),
            'facility_name' => $this->getElementText($facility, 'FacilityName'),
        ];
    }

    /**
     * Parse batch element from XML.
     */
    protected function parseBatchElement(DOMDocument $dom): array
    {
        $batch = $dom->getElementsByTagName('BatchDetails')->item(0);
        if (! $batch) {
            return [];
        }

        return [
            'batch_number' => $this->getElementText($batch, 'BatchNumber'),
            'batch_name' => $this->getElementText($batch, 'BatchName'),
            'submission_period' => $this->getElementText($batch, 'SubmissionPeriod'),
            'total_claims' => (int) $this->getElementText($batch, 'TotalClaims'),
            'total_amount' => (float) $this->getElementText($batch, 'TotalAmount'),
        ];
    }

    /**
     * Parse claims element from XML.
     */
    protected function parseClaimsElement(DOMDocument $dom): array
    {
        $claims = [];
        $claimElements = $dom->getElementsByTagName('Claim');

        foreach ($claimElements as $claimElement) {
            $claims[] = $this->parseClaimElement($claimElement);
        }

        return $claims;
    }

    /**
     * Parse a single claim element.
     */
    protected function parseClaimElement(DOMElement $claimElement): array
    {
        $claim = [
            'claim_check_code' => $claimElement->getAttribute('id'),
            'patient' => [],
            'attendance' => [],
            'gdrg' => [],
            'diagnoses' => [],
            'items' => [],
            'totals' => [],
        ];

        // Parse patient
        $patientElement = $claimElement->getElementsByTagName('Patient')->item(0);
        if ($patientElement) {
            $claim['patient'] = [
                'nhis_member_id' => $this->getElementText($patientElement, 'NhisMemberId'),
                'surname' => $this->getElementText($patientElement, 'Surname'),
                'other_names' => $this->getElementText($patientElement, 'OtherNames'),
                'date_of_birth' => $this->getElementText($patientElement, 'DateOfBirth'),
                'gender' => $this->getElementText($patientElement, 'Gender'),
                'folder_id' => $this->getElementText($patientElement, 'FolderId'),
            ];
        }

        // Parse attendance
        $attendanceElement = $claimElement->getElementsByTagName('Attendance')->item(0);
        if ($attendanceElement) {
            $claim['attendance'] = [
                'date_of_attendance' => $this->getElementText($attendanceElement, 'DateOfAttendance'),
                'date_of_discharge' => $this->getElementText($attendanceElement, 'DateOfDischarge'),
                'type_of_attendance' => $this->getElementText($attendanceElement, 'TypeOfAttendance'),
                'type_of_service' => $this->getElementText($attendanceElement, 'TypeOfService'),
                'specialty_attended' => $this->getElementText($attendanceElement, 'SpecialtyAttended'),
                'attending_prescriber' => $this->getElementText($attendanceElement, 'AttendingPrescriber'),
            ];
        }

        // Parse G-DRG
        $gdrgElement = $claimElement->getElementsByTagName('GDRG')->item(0);
        if ($gdrgElement) {
            $claim['gdrg'] = [
                'code' => $this->getElementText($gdrgElement, 'Code'),
                'name' => $this->getElementText($gdrgElement, 'Name'),
                'amount' => (float) $this->getElementText($gdrgElement, 'Amount'),
            ];
        }

        // Parse diagnoses
        $diagnosisElements = $claimElement->getElementsByTagName('Diagnosis');
        foreach ($diagnosisElements as $diagElement) {
            $claim['diagnoses'][] = [
                'is_primary' => $diagElement->getAttribute('isPrimary') === 'true',
                'icd10_code' => $this->getElementText($diagElement, 'ICD10Code'),
                'description' => $this->getElementText($diagElement, 'Description'),
            ];
        }

        // Parse items
        $itemElements = $claimElement->getElementsByTagName('Item');
        foreach ($itemElements as $itemElement) {
            $claim['items'][] = [
                'type' => $itemElement->getAttribute('type'),
                'item_date' => $this->getElementText($itemElement, 'ItemDate'),
                'nhis_code' => $this->getElementText($itemElement, 'NhisCode'),
                'hospital_code' => $this->getElementText($itemElement, 'HospitalCode'),
                'description' => $this->getElementText($itemElement, 'Description'),
                'quantity' => (int) $this->getElementText($itemElement, 'Quantity'),
                'unit_price' => (float) $this->getElementText($itemElement, 'UnitPrice'),
                'subtotal' => (float) $this->getElementText($itemElement, 'Subtotal'),
                'is_covered' => $this->getElementText($itemElement, 'IsCovered') === 'true',
                'insurance_pays' => (float) $this->getElementText($itemElement, 'InsurancePays'),
                'patient_pays' => (float) $this->getElementText($itemElement, 'PatientPays'),
            ];
        }

        // Parse totals
        $totalsElement = $claimElement->getElementsByTagName('Totals')->item(0);
        if ($totalsElement) {
            $claim['totals'] = [
                'total_claim_amount' => (float) $this->getElementText($totalsElement, 'TotalClaimAmount'),
                'insurance_covered_amount' => (float) $this->getElementText($totalsElement, 'InsuranceCoveredAmount'),
                'patient_copay_amount' => (float) $this->getElementText($totalsElement, 'PatientCopayAmount'),
            ];
        }

        return $claim;
    }

    /**
     * Get text content of a child element.
     */
    protected function getElementText(DOMElement $parent, string $tagName): string
    {
        $element = $parent->getElementsByTagName($tagName)->item(0);

        return $element ? $element->textContent : '';
    }
}
