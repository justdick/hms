<?php

namespace App\Services;

use App\Models\ClaimBatch;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimDiagnosis;
use App\Models\InsuranceClaimItem;
use DOMDocument;
use DOMElement;

/**
 * Service for generating NHIA-compliant XML exports for claim batches.
 *
 * Generates XML in the exact format required by NHIS portal submission.
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

        // Create root element - NHIS uses simple <claims> root
        $root = $this->dom->createElement('claims');
        $this->dom->appendChild($root);

        // Load batch items with related claims
        $batchItems = $batch->batchItems()
            ->with([
                'insuranceClaim.patient',
                'insuranceClaim.gdrgTariff',
                'insuranceClaim.claimDiagnoses.diagnosis',
                'insuranceClaim.items.nhisTariff',
                'insuranceClaim.items.charge.prescription.drug',
            ])
            ->get();

        foreach ($batchItems as $batchItem) {
            if ($batchItem->insuranceClaim) {
                $claimElement = $this->generateClaimElement($batchItem->insuranceClaim);
                $root->appendChild($claimElement);
            }
        }

        return $this->dom->saveXML();
    }

    /**
     * Generate a claim element for the XML in NHIS format.
     *
     * @param  InsuranceClaim  $claim  The claim to generate XML for
     * @return DOMElement The claim element
     *
     * _Requirements: 15.3_
     */
    public function generateClaimElement(InsuranceClaim $claim): DOMElement
    {
        $claimElement = $this->dom->createElement('claim');

        // Basic claim identifiers
        $this->appendElement($claimElement, 'claimID', $claim->id);
        $this->appendElement($claimElement, 'claimCheckCode', $claim->claim_check_code ?? '');
        $this->appendElement($claimElement, 'preAuthorizationCodes', '');
        $this->appendElement($claimElement, 'physicianID', '');

        // Patient information - flat structure
        $nhisMemberId = $claim->membership_id ?? $claim->patientInsurance?->membership_id ?? '';
        $this->appendElement($claimElement, 'memberNo', $nhisMemberId);
        $this->appendElement($claimElement, 'cardSerialNo', '');
        $this->appendElement($claimElement, 'surname', $claim->patient_surname ?? '');
        $this->appendElement($claimElement, 'otherNames', $claim->patient_other_names ?? '');
        $this->appendElement($claimElement, 'dateOfBirth', $claim->patient_dob?->format('Y-m-d') ?? '');
        $this->appendElement($claimElement, 'gender', $this->formatGender($claim->patient_gender));
        $this->appendElement($claimElement, 'hospitalRecNo', $claim->folder_id ?? '');
        $this->appendElement($claimElement, 'isDependant', '0');

        // Service information
        $this->appendElement($claimElement, 'typeOfService', $claim->type_of_service ?? 'OPD');
        $this->appendElement($claimElement, 'isUnbundled', $claim->is_unbundled ? '1' : '0');
        $this->appendElement($claimElement, 'includesPharmacy', $this->hasPharmacyItems($claim) ? '1' : '0');
        $this->appendElement($claimElement, 'typeOfAttendance', $claim->type_of_attendance ?? 'EAE');
        $this->appendElement($claimElement, 'serviceOutcome', 'DISC');

        // Date of service (appears twice in NHIS format)
        $serviceDate = $claim->date_of_attendance?->format('Y-m-d') ?? '';
        $this->appendElement($claimElement, 'dateOfService', $serviceDate);
        $this->appendElement($claimElement, 'dateOfService', $claim->date_of_discharge?->format('Y-m-d') ?? $serviceDate);

        $this->appendElement($claimElement, 'specialtyAttended', $claim->specialty_attended ?? 'OPDC');

        // Add procedures (if any)
        $this->addProcedures($claimElement, $claim);

        // Add diagnoses
        $this->addDiagnoses($claimElement, $claim);

        // Add medicines
        $this->addMedicines($claimElement, $claim);

        // Add referral info (always present, can be empty)
        $this->addReferralInfo($claimElement);

        return $claimElement;
    }

    /**
     * Add procedure elements to the claim.
     */
    protected function addProcedures(DOMElement $claimElement, InsuranceClaim $claim): void
    {
        // Get procedure items from claim items
        $procedureItems = $claim->items?->filter(fn ($item) => $item->item_type === 'procedure') ?? collect();

        foreach ($procedureItems as $item) {
            $procedureElement = $this->dom->createElement('procedure');

            $this->appendElement($procedureElement, 'serviceDate', $item->item_date?->format('Y-m-d') ?? $claim->date_of_attendance?->format('Y-m-d') ?? '');
            $this->appendElement($procedureElement, 'gdrgCode', $claim->gdrgTariff?->code ?? $claim->c_drg_code ?? '');
            $this->appendElement($procedureElement, 'ICD10', $claim->primary_diagnosis_code ?? '-');

            $claimElement->appendChild($procedureElement);
        }
    }

    /**
     * Add diagnosis elements to the claim.
     */
    protected function addDiagnoses(DOMElement $claimElement, InsuranceClaim $claim): void
    {
        $claimDiagnoses = $claim->claimDiagnoses ?? collect();
        $gdrgCode = $claim->gdrgTariff?->code ?? $claim->c_drg_code ?? '';
        $serviceDate = $claim->date_of_attendance?->format('Y-m-d') ?? '';

        if ($claimDiagnoses->isNotEmpty()) {
            foreach ($claimDiagnoses as $claimDiagnosis) {
                $diagnosisElement = $this->createDiagnosisElement($claimDiagnosis, $gdrgCode, $serviceDate);
                $claimElement->appendChild($diagnosisElement);
            }
        } elseif ($claim->primary_diagnosis_code || $claim->primary_diagnosis_description) {
            // Fallback to primary diagnosis from claim
            $diagnosisElement = $this->dom->createElement('diagnosis');
            $this->appendElement($diagnosisElement, 'serviceDate', $serviceDate);
            $this->appendElement($diagnosisElement, 'gdrgCode', $gdrgCode);
            $this->appendElement($diagnosisElement, 'ICD10', $claim->primary_diagnosis_code ?? '-');
            $this->appendElement($diagnosisElement, 'diagnosis', $claim->primary_diagnosis_description ?? '');
            $claimElement->appendChild($diagnosisElement);
        }
    }

    /**
     * Create a diagnosis element in NHIS format.
     */
    protected function createDiagnosisElement(InsuranceClaimDiagnosis $claimDiagnosis, string $gdrgCode, string $serviceDate): DOMElement
    {
        $diagnosisElement = $this->dom->createElement('diagnosis');
        $diagnosis = $claimDiagnosis->diagnosis;

        $this->appendElement($diagnosisElement, 'serviceDate', $serviceDate);
        $this->appendElement($diagnosisElement, 'gdrgCode', $gdrgCode);
        $this->appendElement($diagnosisElement, 'ICD10', $diagnosis?->icd_10 ?? $diagnosis?->code ?? '-');
        $this->appendElement($diagnosisElement, 'diagnosis', $diagnosis?->diagnosis ?? '');

        return $diagnosisElement;
    }

    /**
     * Add medicine elements to the claim.
     */
    protected function addMedicines(DOMElement $claimElement, InsuranceClaim $claim): void
    {
        // Get drug items from claim items
        $drugItems = $claim->items?->filter(fn ($item) => $item->item_type === 'drug') ?? collect();

        foreach ($drugItems as $item) {
            $medicineElement = $this->createMedicineElement($item, $claim);
            $claimElement->appendChild($medicineElement);
        }
    }

    /**
     * Create a medicine element in NHIS format.
     */
    protected function createMedicineElement(InsuranceClaimItem $item, InsuranceClaim $claim): DOMElement
    {
        $medicineElement = $this->dom->createElement('medicine');

        // Get NHIS code from tariff or item
        $nhisCode = $item->nhis_code ?? $item->nhisTariff?->nhis_code ?? $item->code ?? '';
        $this->appendElement($medicineElement, 'medicineCode', $nhisCode);
        $this->appendElement($medicineElement, 'dispensedQty', (string) ($item->quantity ?? 1));
        $this->appendElement($medicineElement, 'serviceDate', $item->item_date?->format('Y-m-d') ?? $claim->date_of_attendance?->format('Y-m-d') ?? '');

        // Add prescription details
        $prescriptionElement = $this->dom->createElement('prescription');
        $prescription = $item->charge?->prescription;

        $this->appendElement($prescriptionElement, 'dose', '');
        $this->appendElement($prescriptionElement, 'frequency', '');
        $this->appendElement($prescriptionElement, 'duration', '');

        // Build unparsed prescription string
        $unparsed = $this->buildPrescriptionUnparsed($prescription);
        $this->appendElement($prescriptionElement, 'unparsed', $unparsed);

        $medicineElement->appendChild($prescriptionElement);

        return $medicineElement;
    }

    /**
     * Build the unparsed prescription string in NHIS format.
     * Format: "DOSE FREQUENCY X DURATION" e.g., "2 BD X 5DAYS"
     */
    protected function buildPrescriptionUnparsed($prescription): string
    {
        if (! $prescription) {
            return '';
        }

        $parts = [];

        // Add dose quantity if available
        if ($prescription->dose_quantity) {
            $parts[] = $prescription->dose_quantity;
        }

        // Add frequency
        if ($prescription->frequency) {
            $parts[] = strtoupper($prescription->frequency);
        }

        // Add duration
        if ($prescription->duration) {
            $parts[] = 'X '.strtoupper($prescription->duration);
        }

        return implode(' ', $parts);
    }

    /**
     * Add referral info element (always present, can be empty).
     */
    protected function addReferralInfo(DOMElement $claimElement): void
    {
        $referralElement = $this->dom->createElement('referralInfo');

        $this->appendElement($referralElement, 'claimCheckCode', '');
        $this->appendElement($referralElement, 'facilityID', '');
        $this->appendElement($referralElement, 'facilityName', '');

        $claimElement->appendChild($referralElement);
    }

    /**
     * Check if claim has pharmacy/drug items.
     */
    protected function hasPharmacyItems(InsuranceClaim $claim): bool
    {
        return $claim->is_pharmacy_included
            || ($claim->items?->contains(fn ($item) => $item->item_type === 'drug') ?? false);
    }

    /**
     * Format gender to single character (M/F).
     */
    protected function formatGender(?string $gender): string
    {
        if (! $gender) {
            return '';
        }

        $gender = strtoupper(trim($gender));

        if (in_array($gender, ['M', 'MALE'])) {
            return 'M';
        }

        if (in_array($gender, ['F', 'FEMALE'])) {
            return 'F';
        }

        return $gender;
    }

    /**
     * Append a child element with text content.
     */
    protected function appendElement(DOMElement $parent, string $tagName, string $value): void
    {
        $element = $this->dom->createElement($tagName);
        $element->appendChild($this->dom->createTextNode($this->escapeXml($value)));
        $parent->appendChild($element);
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
     * Generate an item element for the XML (kept for backward compatibility).
     *
     * @param  InsuranceClaimItem  $item  The item to generate XML for
     * @return DOMElement The item element
     */
    public function generateItemElement(InsuranceClaimItem $item): DOMElement
    {
        // For medicines, use the medicine format
        if ($item->item_type === 'drug') {
            return $this->createMedicineElement($item, $item->claim);
        }

        // For procedures, create a procedure element
        $procedureElement = $this->dom->createElement('procedure');
        $this->appendElement($procedureElement, 'serviceDate', $item->item_date?->format('Y-m-d') ?? '');
        $this->appendElement($procedureElement, 'gdrgCode', $item->nhis_code ?? $item->code ?? '');
        $this->appendElement($procedureElement, 'ICD10', '-');

        return $procedureElement;
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

        return [
            'claims' => $this->parseClaimsElement($dom),
        ];
    }

    /**
     * Parse claims element from XML.
     */
    protected function parseClaimsElement(DOMDocument $dom): array
    {
        $claims = [];
        $claimElements = $dom->getElementsByTagName('claim');

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
            'claimID' => $this->getElementText($claimElement, 'claimID'),
            'claimCheckCode' => $this->getElementText($claimElement, 'claimCheckCode'),
            'memberNo' => $this->getElementText($claimElement, 'memberNo'),
            'surname' => $this->getElementText($claimElement, 'surname'),
            'otherNames' => $this->getElementText($claimElement, 'otherNames'),
            'dateOfBirth' => $this->getElementText($claimElement, 'dateOfBirth'),
            'gender' => $this->getElementText($claimElement, 'gender'),
            'hospitalRecNo' => $this->getElementText($claimElement, 'hospitalRecNo'),
            'typeOfService' => $this->getElementText($claimElement, 'typeOfService'),
            'includesPharmacy' => $this->getElementText($claimElement, 'includesPharmacy'),
            'typeOfAttendance' => $this->getElementText($claimElement, 'typeOfAttendance'),
            'specialtyAttended' => $this->getElementText($claimElement, 'specialtyAttended'),
            'diagnoses' => [],
            'medicines' => [],
            'procedures' => [],
        ];

        // Parse diagnoses
        $diagnosisElements = $claimElement->getElementsByTagName('diagnosis');
        foreach ($diagnosisElements as $diagElement) {
            // Skip if this is nested inside another element (like referralInfo)
            if ($diagElement->parentNode->nodeName === 'claim') {
                $claim['diagnoses'][] = [
                    'serviceDate' => $this->getElementText($diagElement, 'serviceDate'),
                    'gdrgCode' => $this->getElementText($diagElement, 'gdrgCode'),
                    'ICD10' => $this->getElementText($diagElement, 'ICD10'),
                    'diagnosis' => $this->getElementText($diagElement, 'diagnosis'),
                ];
            }
        }

        // Parse medicines
        $medicineElements = $claimElement->getElementsByTagName('medicine');
        foreach ($medicineElements as $medElement) {
            $prescriptionElement = $medElement->getElementsByTagName('prescription')->item(0);
            $claim['medicines'][] = [
                'medicineCode' => $this->getElementText($medElement, 'medicineCode'),
                'dispensedQty' => $this->getElementText($medElement, 'dispensedQty'),
                'serviceDate' => $this->getElementText($medElement, 'serviceDate'),
                'prescription' => $prescriptionElement ? [
                    'dose' => $this->getElementText($prescriptionElement, 'dose'),
                    'frequency' => $this->getElementText($prescriptionElement, 'frequency'),
                    'duration' => $this->getElementText($prescriptionElement, 'duration'),
                    'unparsed' => $this->getElementText($prescriptionElement, 'unparsed'),
                ] : null,
            ];
        }

        // Parse procedures
        $procedureElements = $claimElement->getElementsByTagName('procedure');
        foreach ($procedureElements as $procElement) {
            $claim['procedures'][] = [
                'serviceDate' => $this->getElementText($procElement, 'serviceDate'),
                'gdrgCode' => $this->getElementText($procElement, 'gdrgCode'),
                'ICD10' => $this->getElementText($procElement, 'ICD10'),
            ];
        }

        return $claim;
    }

    /**
     * Get text content of a child element.
     */
    protected function getElementText(DOMElement $parent, string $tagName): string
    {
        $elements = $parent->getElementsByTagName($tagName);

        // Get the first direct child with this tag name
        foreach ($elements as $element) {
            if ($element->parentNode === $parent) {
                return $element->textContent;
            }
        }

        return '';
    }
}
