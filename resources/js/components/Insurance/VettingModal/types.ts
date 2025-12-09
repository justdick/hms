/**
 * Type definitions for the NHIS Claims Vetting Modal
 */

export interface GdrgTariff {
    id: number;
    code: string;
    name: string;
    tariff_price: number;
    display_name: string;
}

export interface Diagnosis {
    id: number | null;
    diagnosis_id: number;
    name: string;
    icd_code: string;
    is_primary: boolean;
}

export interface ClaimItem {
    id: number;
    name: string;
    code: string | null;
    nhis_code: string | null;
    quantity: number;
    unit_price: number;
    nhis_price: number | null;
    subtotal: number;
    is_covered: boolean;
    item_type?: 'drug' | 'lab' | 'procedure';
}

export interface NhisTariffOption {
    id: number;
    nhis_code: string;
    name: string;
    category: string;
    price: number;
}

export interface PatientInfo {
    id: number;
    name: string;
    surname: string;
    other_names: string;
    date_of_birth: string;
    gender: string;
    folder_number: string | null;
    nhis_member_id: string | null;
    nhis_expiry_date: string | null;
    is_nhis_expired: boolean;
}

export interface AttendanceDetails {
    type_of_attendance: string;
    date_of_attendance: string;
    date_of_discharge: string | null;
    type_of_service: string;
    specialty_attended: string | null;
    attending_prescriber: string | null;
    claim_check_code: string;
    is_unbundled: boolean;
    is_pharmacy_included: boolean;
    // NHIS code options for editing
    attendance_type_options: Record<string, string>;
    service_type_options: Record<string, string>;
    specialty_options: Record<string, string>;
}

export interface ClaimTotals {
    investigations: number;
    prescriptions: number;
    procedures: number;
    gdrg: number;
    grand_total: number;
    unmapped_count: number;
}

export interface VettingData {
    claim: {
        id: number;
        claim_check_code: string;
        status: string;
        gdrg_tariff_id: number | null;
        gdrg_amount: number | null;
    };
    patient: PatientInfo;
    attendance: AttendanceDetails;
    diagnoses: Diagnosis[];
    items: {
        investigations: ClaimItem[];
        prescriptions: ClaimItem[];
        procedures: ClaimItem[];
    };
    totals: ClaimTotals;
    is_nhis: boolean;
    gdrg_tariffs: GdrgTariff[];
    // Diagnoses loaded via async search - too many to load upfront
    can: {
        vet: boolean;
    };
}

export interface VettingModalProps {
    claimId: number | null;
    isOpen: boolean;
    onClose: () => void;
    onVetSuccess: () => void;
    /** Mode: 'vet' for vetting actions, 'view' for read-only viewing */
    mode?: 'vet' | 'view';
}
