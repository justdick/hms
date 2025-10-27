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
}

interface ReviewForm {
    prescription_id: number;
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
}

export function ReviewPrescriptionsModal({
    open,
    onOpenChange,
    patientId,
    prescriptionsData,
}: Props) {
    const [reviews, setReviews] = useState<ReviewForm[]>(
        prescriptionsData.map((pd) => {
            // If prescription is already reviewed, determine the action based on quantity
            const isReviewed = pd.prescription.status === 'reviewed';
            const quantityToDispense = isReviewed
                ? pd.prescription.quantity_to_dispense ||
                  pd.prescription.quantity
                : pd.prescription.quantity;

            // Determine action based on current state
            let action: 'keep' | 'partial' | 'external' | 'cancel' = 'keep';
            if (isReviewed && quantityToDispense < pd.prescription.quantity) {
                action = 'partial';
            }

            return {
                prescription_id: pd.prescription.id,
                action,
                quantity_to_dispense: quantityToDispense,
                notes: isReviewed ? pd.prescription.dispensing_notes || '' : '',
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
                // Set to null so user must enter manually to avoid mistakes
                newReviews[index].quantity_to_dispense = null;
            }
        }

        setReviews(newReviews);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Clean up the data before submission - only include reason when needed
        const cleanedReviews = reviews.map((review) => {
            const cleaned: any = {
                prescription_id: review.prescription_id,
                action: review.action,
                quantity_to_dispense: review.quantity_to_dispense,
            };

            // Only include notes if not empty
            if (review.notes) {
                cleaned.notes = review.notes;
            }

            // Only include reason for external/cancel actions
            if (review.action === 'external' || review.action === 'cancel') {
                cleaned.reason = review.reason;
            }

            return cleaned;
        });

        setProcessing(true);

        // Submit with cleaned data using router
        router.post(
            `/pharmacy/dispensing/patients/${patientId}/review`,
            { reviews: cleanedReviews },
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
                    <DialogTitle>Review Prescriptions</DialogTitle>
                    <DialogDescription>
                        Review stock availability and adjust quantities before
                        dispensing
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
                        {/* Desktop: Table View */}
                        <div className="hidden pr-4 md:block">
                            <PrescriptionReviewTable
                                prescriptionsData={prescriptionsData}
                                reviews={reviews}
                                onUpdateReview={updateReview}
                                errors={errors}
                            />
                        </div>

                        {/* Mobile: Card View */}
                        <div className="pr-4 md:hidden">
                            <MobileReviewCards
                                prescriptionsData={prescriptionsData}
                                reviews={reviews}
                                onUpdateReview={updateReview}
                                errors={errors}
                            />
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
