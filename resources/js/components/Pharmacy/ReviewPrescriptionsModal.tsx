import {
    MobileReviewCards,
    PrescriptionReviewTable,
} from '@/components/Pharmacy/PrescriptionReviewTable';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { router } from '@inertiajs/react';
import { AlertCircle, Save } from 'lucide-react';
import { useState } from 'react';

interface Drug {
    id: number;
    name: string;
    form: string;
    unit_type: string;
    strength?: string;
}

interface Prescription {
    id: number;
    drug_id: number;
    drug: Drug;
    quantity: number;
    quantity_to_dispense?: number;
    dose_quantity?: string;
    frequency: string;
    duration: string;
    status: string;
    instructions?: string;
    dispensing_notes?: string;
}

interface StockStatus {
    available: boolean;
    in_stock: number;
    shortage: number;
}

interface PrescriptionData {
    prescription: Prescription;
    stock_status: StockStatus;
    can_dispense_full: boolean;
    max_dispensable: number;
    is_unpriced?: boolean;
}

interface MinorProcedureSupply {
    id: number;
    drug_id: number;
    drug: Drug;
    quantity: number;
    quantity_to_dispense?: number;
    status: string;
    dispensing_notes?: string;
}

interface SupplyData {
    supply: MinorProcedureSupply;
    stock_status: StockStatus;
    can_dispense_full: boolean;
    max_dispensable: number;
    procedure_type: string;
}

interface ReviewForm {
    prescription_id: number;
    action: 'keep' | 'partial' | 'external' | 'cancel';
    quantity_to_dispense: number | null;
    notes: string;
    reason: string;
}

interface SupplyReviewForm {
    supply_id: number;
    action: 'keep' | 'partial' | 'external' | 'cancel';
    quantity_to_dispense: number | null;
    notes: string;
    reason: string;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    patientId: number;
    prescriptionsData: PrescriptionData[];
    suppliesData: SupplyData[];
}

export function ReviewPrescriptionsModal({
    open,
    onOpenChange,
    patientId,
    prescriptionsData,
    suppliesData,
}: Props) {
    const [reviews, setReviews] = useState<ReviewForm[]>(
        prescriptionsData.map((pd) => {
            // If prescription is already reviewed, determine the action based on quantity
            const isReviewed = pd.prescription.status === 'reviewed';

            // Determine action based on current state and stock availability
            let action: 'keep' | 'partial' | 'external' | 'cancel' = 'keep';
            let quantityToDispense: number | null = pd.prescription.quantity;
            let reason = '';

            if (isReviewed) {
                // Already reviewed - use existing values
                quantityToDispense =
                    pd.prescription.quantity_to_dispense ||
                    pd.prescription.quantity;
                if (quantityToDispense < pd.prescription.quantity) {
                    action = 'partial';
                }
            } else {
                // Not yet reviewed - apply smart defaults
                // Priority: unpriced > out of stock > partial stock > keep
                if (pd.is_unpriced) {
                    // Unpriced drug - default to external
                    action = 'external';
                    quantityToDispense = null;
                    reason =
                        'Drug is unpriced - patient to purchase externally';
                } else if (pd.stock_status.in_stock === 0) {
                    // Out of stock - default to external
                    action = 'external';
                    quantityToDispense = null;
                } else if (
                    pd.stock_status.in_stock < pd.prescription.quantity
                ) {
                    // Partial stock available - default to partial with available qty
                    action = 'partial';
                    quantityToDispense = pd.max_dispensable;
                }
                // Otherwise keep defaults: action='keep', qty=prescribed
            }

            return {
                prescription_id: pd.prescription.id,
                action,
                quantity_to_dispense: quantityToDispense,
                notes: isReviewed ? pd.prescription.dispensing_notes || '' : '',
                reason,
            };
        }),
    );

    const [supplyReviews, setSupplyReviews] = useState<SupplyReviewForm[]>(
        suppliesData.map((sd) => {
            const isReviewed = sd.supply.status === 'reviewed';

            // Determine action based on current state and stock availability
            let action: 'keep' | 'partial' | 'external' | 'cancel' = 'keep';
            let quantityToDispense: number | null = sd.supply.quantity;

            if (isReviewed) {
                // Already reviewed - use existing values
                quantityToDispense =
                    sd.supply.quantity_to_dispense || sd.supply.quantity;
                if (quantityToDispense < sd.supply.quantity) {
                    action = 'partial';
                }
            } else {
                // Not yet reviewed - apply smart defaults based on stock
                if (sd.stock_status.in_stock === 0) {
                    // Out of stock - default to external
                    action = 'external';
                    quantityToDispense = null;
                } else if (sd.stock_status.in_stock < sd.supply.quantity) {
                    // Partial stock available - default to partial with available qty
                    action = 'partial';
                    quantityToDispense = sd.max_dispensable;
                }
                // Otherwise keep defaults: action='keep', qty=requested
            }

            return {
                supply_id: sd.supply.id,
                action,
                quantity_to_dispense: quantityToDispense,
                notes: isReviewed ? sd.supply.dispensing_notes || '' : '',
                reason: '',
            };
        }),
    );

    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const updateReview = (
        index: number,
        field: keyof ReviewForm,
        value: any,
    ) => {
        const newReviews = [...reviews];
        newReviews[index] = { ...newReviews[index], [field]: value };

        if (field === 'action') {
            if (value === 'keep') {
                newReviews[index].quantity_to_dispense =
                    prescriptionsData[index].prescription.quantity;
            } else if (value === 'partial') {
                // Prefill with max available stock (pharmacist can reduce if needed)
                newReviews[index].quantity_to_dispense =
                    prescriptionsData[index].max_dispensable;
            } else if (value === 'external' || value === 'cancel') {
                newReviews[index].quantity_to_dispense = null;
            }
        }

        setReviews(newReviews);
    };

    const updateSupplyReview = (
        index: number,
        field: keyof ReviewForm,
        value: any,
    ) => {
        const newReviews = [...supplyReviews];
        newReviews[index] = { ...newReviews[index], [field]: value };

        if (field === 'action') {
            if (value === 'keep') {
                newReviews[index].quantity_to_dispense =
                    suppliesData[index].supply.quantity;
            } else if (value === 'partial') {
                // Prefill with max available stock (pharmacist can reduce if needed)
                newReviews[index].quantity_to_dispense =
                    suppliesData[index].max_dispensable;
            } else if (value === 'external' || value === 'cancel') {
                newReviews[index].quantity_to_dispense = null;
            }
        }

        setSupplyReviews(newReviews);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Clean up prescription reviews
        const cleanedReviews = reviews.map((review) => {
            const cleaned: any = {
                prescription_id: review.prescription_id,
                action: review.action,
                quantity_to_dispense: review.quantity_to_dispense,
            };

            if (review.notes) {
                cleaned.notes = review.notes;
            }

            if (review.action === 'external' || review.action === 'cancel') {
                cleaned.reason = review.reason;
            }

            return cleaned;
        });

        // Clean up supply reviews
        const cleanedSupplyReviews = supplyReviews.map((review) => {
            const cleaned: any = {
                supply_id: review.supply_id,
                action: review.action,
                quantity_to_dispense: review.quantity_to_dispense,
            };

            if (review.notes) {
                cleaned.notes = review.notes;
            }

            if (review.action === 'external' || review.action === 'cancel') {
                cleaned.reason = review.reason;
            }

            return cleaned;
        });

        setProcessing(true);

        // Build payload - only include non-empty arrays
        const payload: any = {};
        if (cleanedReviews.length > 0) {
            payload.reviews = cleanedReviews;
        }
        if (cleanedSupplyReviews.length > 0) {
            payload.supply_reviews = cleanedSupplyReviews;
        }

        // Submit both prescriptions and supplies
        router.post(
            `/pharmacy/dispensing/patients/${patientId}/review`,
            payload,
            {
                onSuccess: () => {
                    onOpenChange(false);
                },
                onError: (errors) => {
                    setErrors(errors);
                },
                onFinish: () => {
                    setProcessing(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] w-[95vw] max-w-[95vw] sm:max-w-[95vw]">
                <DialogHeader>
                    <DialogTitle>Review Items for Dispensing</DialogTitle>
                    <DialogDescription>
                        Review stock availability and adjust quantities for
                        prescriptions and supplies before dispensing
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {errors && Object.keys(errors).length > 0 && (
                        <Card className="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/20">
                            <CardContent className="pt-6">
                                <div className="flex items-start gap-2">
                                    <AlertCircle className="mt-0.5 h-5 w-5 text-red-600 dark:text-red-400" />
                                    <div>
                                        <p className="mb-2 font-medium text-red-600 dark:text-red-400">
                                            Please fix the following errors:
                                        </p>
                                        <ul className="list-disc space-y-1 pl-5 text-sm text-red-600 dark:text-red-400">
                                            {Object.entries(errors)
                                                .filter(
                                                    ([key]) =>
                                                        !key.startsWith(
                                                            'reviews.',
                                                        ),
                                                )
                                                .map(([key, error]) => (
                                                    <li key={key}>{error}</li>
                                                ))}
                                        </ul>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <ScrollArea className="h-[calc(90vh-16rem)]">
                        <div className="space-y-6 pr-4">
                            {/* Prescriptions Section */}
                            {prescriptionsData.length > 0 && (
                                <div className="space-y-3">
                                    <h3 className="text-lg font-semibold">
                                        Prescriptions (
                                        {prescriptionsData.length})
                                    </h3>
                                    {/* Desktop: Table View */}
                                    <div className="hidden md:block">
                                        <PrescriptionReviewTable
                                            prescriptionsData={
                                                prescriptionsData
                                            }
                                            reviews={reviews}
                                            onUpdateReview={updateReview}
                                            errors={errors}
                                        />
                                    </div>

                                    {/* Mobile: Card View */}
                                    <div className="md:hidden">
                                        <MobileReviewCards
                                            prescriptionsData={
                                                prescriptionsData
                                            }
                                            reviews={reviews}
                                            onUpdateReview={updateReview}
                                            errors={errors}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Minor Procedure Supplies Section */}
                            {suppliesData.length > 0 && (
                                <div className="space-y-3">
                                    <h3 className="text-lg font-semibold">
                                        Minor Procedure Supplies (
                                        {suppliesData.length})
                                    </h3>
                                    {/* Desktop: Table View */}
                                    <div className="hidden md:block">
                                        <PrescriptionReviewTable
                                            prescriptionsData={suppliesData.map(
                                                (sd) => ({
                                                    prescription: {
                                                        id: sd.supply.id,
                                                        drug_id:
                                                            sd.supply.drug_id,
                                                        drug: sd.supply.drug,
                                                        quantity:
                                                            sd.supply.quantity,
                                                        quantity_to_dispense:
                                                            sd.supply
                                                                .quantity_to_dispense,
                                                        dose_quantity:
                                                            undefined,
                                                        frequency:
                                                            sd.procedure_type,
                                                        duration: '',
                                                        status: sd.supply
                                                            .status,
                                                        dispensing_notes:
                                                            sd.supply
                                                                .dispensing_notes,
                                                    },
                                                    stock_status:
                                                        sd.stock_status,
                                                    can_dispense_full:
                                                        sd.can_dispense_full,
                                                    max_dispensable:
                                                        sd.max_dispensable,
                                                }),
                                            )}
                                            reviews={supplyReviews.map(
                                                (sr) => ({
                                                    prescription_id:
                                                        sr.supply_id,
                                                    action: sr.action,
                                                    quantity_to_dispense:
                                                        sr.quantity_to_dispense,
                                                    notes: sr.notes,
                                                    reason: sr.reason,
                                                }),
                                            )}
                                            onUpdateReview={updateSupplyReview}
                                            errors={errors}
                                        />
                                    </div>

                                    {/* Mobile: Card View */}
                                    <div className="md:hidden">
                                        <MobileReviewCards
                                            prescriptionsData={suppliesData.map(
                                                (sd) => ({
                                                    prescription: {
                                                        id: sd.supply.id,
                                                        drug_id:
                                                            sd.supply.drug_id,
                                                        drug: sd.supply.drug,
                                                        quantity:
                                                            sd.supply.quantity,
                                                        quantity_to_dispense:
                                                            sd.supply
                                                                .quantity_to_dispense,
                                                        dose_quantity:
                                                            undefined,
                                                        frequency:
                                                            sd.procedure_type,
                                                        duration: '',
                                                        status: sd.supply
                                                            .status,
                                                        dispensing_notes:
                                                            sd.supply
                                                                .dispensing_notes,
                                                    },
                                                    stock_status:
                                                        sd.stock_status,
                                                    can_dispense_full:
                                                        sd.can_dispense_full,
                                                    max_dispensable:
                                                        sd.max_dispensable,
                                                }),
                                            )}
                                            reviews={supplyReviews.map(
                                                (sr) => ({
                                                    prescription_id:
                                                        sr.supply_id,
                                                    action: sr.action,
                                                    quantity_to_dispense:
                                                        sr.quantity_to_dispense,
                                                    notes: sr.notes,
                                                    reason: sr.reason,
                                                }),
                                            )}
                                            onUpdateReview={updateSupplyReview}
                                            errors={errors}
                                        />
                                    </div>
                                </div>
                            )}

                            {prescriptionsData.length === 0 &&
                                suppliesData.length === 0 && (
                                    <div className="py-8 text-center text-muted-foreground">
                                        No items to review
                                    </div>
                                )}
                        </div>
                    </ScrollArea>

                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing
                                ? 'Saving...'
                                : 'Save Review & Continue to Billing'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
