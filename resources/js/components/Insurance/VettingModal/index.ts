/**
 * VettingModal Components
 *
 * Modal-based interface for reviewing and vetting NHIS insurance claims.
 * Includes patient info, attendance details, G-DRG selection, diagnoses management,
 * claim items display, and total calculation.
 */

export { AttendanceDetailsSection } from './AttendanceDetailsSection';
export { ClaimItemsTabs } from './ClaimItemsTabs';
export { ClaimTotalDisplay } from './ClaimTotalDisplay';
export { DiagnosesManager } from './DiagnosesManager';
export { GdrgSelector } from './GdrgSelector';
export { PatientInfoSection } from './PatientInfoSection';
export type {
    AttendanceDetails,
    ClaimItem,
    ClaimTotals,
    Diagnosis,
    GdrgTariff,
    PatientInfo,
    VettingData,
    VettingModalProps,
} from './types';
export { VettingModal } from './VettingModal';
