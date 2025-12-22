import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { AlertCircle } from 'lucide-react';
import { StockIndicator } from './StockIndicator';

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
    dose_quantity?: string;
    frequency: string;
    duration: string;
    status: string;
    instructions?: string;
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

interface ReviewForm {
    prescription_id: number;
    action: 'keep' | 'partial' | 'external' | 'cancel';
    quantity_to_dispense: number | null;
    notes: string;
    reason: string;
}

interface Props {
    prescriptionsData: PrescriptionData[];
    reviews: ReviewForm[];
    onUpdateReview: (
        index: number,
        field: keyof ReviewForm,
        value: any,
    ) => void;
    errors?: Record<string, string>;
}

export function PrescriptionReviewTable({
    prescriptionsData,
    reviews,
    onUpdateReview,
    errors,
}: Props) {
    const getErrorForRow = (index: number) => {
        if (!errors) return null;
        const reviewErrors = Object.keys(errors)
            .filter((key) => key.startsWith(`reviews.${index}.`))
            .map((key) => errors[key]);
        return reviewErrors.length > 0 ? reviewErrors[0] : null;
    };

    return (
        <div className="rounded-md border dark:border-gray-800">
            <Table>
                <TableHeader>
                    <TableRow className="bg-muted/50 dark:bg-gray-900/50">
                        <TableHead className="w-[250px]">Drug</TableHead>
                        <TableHead className="w-[200px]">
                            Instructions
                        </TableHead>
                        <TableHead className="w-[100px]">Prescribed</TableHead>
                        <TableHead className="w-[150px]">Stock</TableHead>
                        <TableHead className="w-[200px]">Action</TableHead>
                        <TableHead className="w-[120px]">
                            Qty to Dispense
                        </TableHead>
                        <TableHead>Reason/Notes</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {prescriptionsData.map((pd, index) => {
                        const review = reviews[index];
                        const rowError = getErrorForRow(index);
                        const needsReason = review.action === 'cancel';
                        const isPartial = review.action === 'partial';

                        return (
                            <TableRow
                                key={pd.prescription.id}
                                className={
                                    rowError
                                        ? 'bg-red-50 dark:bg-red-950/20'
                                        : ''
                                }
                            >
                                {/* Drug Name */}
                                <TableCell className="font-medium">
                                    <div>
                                        <div className="text-sm font-semibold">
                                            {pd.prescription.drug.name}
                                        </div>
                                        {pd.prescription.drug.strength && (
                                            <div className="text-xs text-muted-foreground">
                                                {pd.prescription.drug.strength}
                                            </div>
                                        )}
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {pd.prescription.drug.form}
                                        </div>
                                        {pd.is_unpriced && (
                                            <div className="mt-1 inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                                <AlertCircle className="h-3 w-3" />
                                                Unpriced
                                            </div>
                                        )}
                                    </div>
                                </TableCell>

                                {/* Instructions */}
                                <TableCell>
                                    <div className="space-y-0.5 text-sm">
                                        {pd.prescription.dose_quantity && (
                                            <div className="font-medium">
                                                {pd.prescription.dose_quantity}{' '}
                                                {pd.prescription.drug
                                                    .unit_type === 'piece'
                                                    ? pd.prescription.drug.form
                                                    : pd.prescription.drug
                                                            .unit_type ===
                                                            'bottle' ||
                                                        pd.prescription.drug
                                                            .unit_type ===
                                                            'vial'
                                                      ? 'ml'
                                                      : pd.prescription.drug
                                                            .unit_type}
                                                {' per dose'}
                                            </div>
                                        )}
                                        <div className="text-xs text-muted-foreground">
                                            {pd.prescription.frequency}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {pd.prescription.duration}
                                        </div>
                                    </div>
                                </TableCell>

                                {/* Prescribed Quantity */}
                                <TableCell>
                                    <div className="text-sm font-semibold">
                                        {pd.prescription.quantity}
                                        <span className="ml-1 text-xs text-muted-foreground">
                                            {pd.prescription.drug.unit_type}
                                        </span>
                                    </div>
                                </TableCell>

                                {/* Stock Status */}
                                <TableCell>
                                    <StockIndicator
                                        available={pd.stock_status.available}
                                        inStock={pd.stock_status.in_stock}
                                        requested={pd.prescription.quantity}
                                    />
                                </TableCell>

                                {/* Action Dropdown */}
                                <TableCell>
                                    <Select
                                        value={review.action}
                                        onValueChange={(value) =>
                                            onUpdateReview(
                                                index,
                                                'action',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="keep">
                                                Keep - Full Qty
                                            </SelectItem>
                                            <SelectItem value="partial">
                                                Partial
                                            </SelectItem>
                                            <SelectItem value="external">
                                                External
                                            </SelectItem>
                                            <SelectItem value="cancel">
                                                Cancel
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </TableCell>

                                {/* Quantity to Dispense */}
                                <TableCell>
                                    <Input
                                        type="number"
                                        min="1"
                                        max={
                                            isPartial
                                                ? pd.max_dispensable
                                                : pd.prescription.quantity
                                        }
                                        value={
                                            review.quantity_to_dispense || ''
                                        }
                                        onChange={(e) =>
                                            onUpdateReview(
                                                index,
                                                'quantity_to_dispense',
                                                parseInt(e.target.value),
                                            )
                                        }
                                        disabled={
                                            !isPartial &&
                                            review.action === 'keep'
                                        }
                                        className="w-full"
                                    />
                                    {isPartial && (
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            Max: {pd.max_dispensable}
                                        </div>
                                    )}
                                </TableCell>

                                {/* Reason/Notes */}
                                <TableCell>
                                    <Textarea
                                        value={
                                            needsReason
                                                ? review.reason
                                                : review.notes
                                        }
                                        onChange={(e) =>
                                            onUpdateReview(
                                                index,
                                                needsReason
                                                    ? 'reason'
                                                    : 'notes',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            needsReason
                                                ? 'Reason required...'
                                                : 'Optional notes...'
                                        }
                                        rows={2}
                                        className={
                                            needsReason && !review.reason
                                                ? 'border-red-300 dark:border-red-700'
                                                : ''
                                        }
                                    />
                                    {rowError && (
                                        <div className="mt-1 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                                            <AlertCircle className="h-3 w-3" />
                                            {rowError}
                                        </div>
                                    )}
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}

// Mobile card view for responsive design
interface MobileReviewCardProps {
    prescriptionsData: PrescriptionData[];
    reviews: ReviewForm[];
    onUpdateReview: (
        index: number,
        field: keyof ReviewForm,
        value: any,
    ) => void;
    errors?: Record<string, string>;
}

export function MobileReviewCards({
    prescriptionsData,
    reviews,
    onUpdateReview,
    errors,
}: MobileReviewCardProps) {
    const getErrorForRow = (index: number) => {
        if (!errors) return null;
        const reviewErrors = Object.keys(errors)
            .filter((key) => key.startsWith(`reviews.${index}.`))
            .map((key) => errors[key]);
        return reviewErrors.length > 0 ? reviewErrors[0] : null;
    };

    return (
        <div className="space-y-4">
            {prescriptionsData.map((pd, index) => {
                const review = reviews[index];
                const rowError = getErrorForRow(index);
                const needsReason = review.action === 'cancel';
                const isPartial = review.action === 'partial';

                return (
                    <div
                        key={pd.prescription.id}
                        className={`space-y-3 rounded-lg border p-4 dark:border-gray-800 ${
                            rowError
                                ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-950/20'
                                : ''
                        }`}
                    >
                        {/* Drug Header */}
                        <div>
                            <div className="flex items-start justify-between gap-2">
                                <h3 className="font-semibold">
                                    {pd.prescription.drug.name}
                                    {pd.prescription.drug.strength && (
                                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                                            {pd.prescription.drug.strength}
                                        </span>
                                    )}
                                </h3>
                                {pd.is_unpriced && (
                                    <span className="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                        <AlertCircle className="h-3 w-3" />
                                        Unpriced
                                    </span>
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {pd.prescription.dose_quantity && (
                                    <>
                                        {pd.prescription.dose_quantity}{' '}
                                        {pd.prescription.drug.unit_type ===
                                        'piece'
                                            ? pd.prescription.drug.form
                                            : pd.prescription.drug.unit_type ===
                                                    'bottle' ||
                                                pd.prescription.drug
                                                    .unit_type === 'vial'
                                              ? 'ml'
                                              : pd.prescription.drug.unit_type}
                                        {' per dose'} ·{' '}
                                    </>
                                )}
                                {pd.prescription.frequency} ·{' '}
                                {pd.prescription.duration}
                            </p>
                        </div>

                        {/* Prescribed & Stock */}
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="text-xs text-muted-foreground">
                                    Prescribed
                                </div>
                                <div className="font-semibold">
                                    {pd.prescription.quantity}{' '}
                                    {pd.prescription.drug.unit_type}
                                </div>
                            </div>
                            <StockIndicator
                                available={pd.stock_status.available}
                                inStock={pd.stock_status.in_stock}
                                requested={pd.prescription.quantity}
                            />
                        </div>

                        {/* Action */}
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Action
                            </label>
                            <Select
                                value={review.action}
                                onValueChange={(value) =>
                                    onUpdateReview(index, 'action', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="keep">
                                        Keep - Full Quantity
                                    </SelectItem>
                                    <SelectItem value="partial">
                                        Partial
                                    </SelectItem>
                                    <SelectItem value="external">
                                        External
                                    </SelectItem>
                                    <SelectItem value="cancel">
                                        Cancel
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Quantity */}
                        {(review.action === 'keep' || isPartial) && (
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Quantity to Dispense
                                </label>
                                <Input
                                    type="number"
                                    min="1"
                                    max={
                                        isPartial
                                            ? pd.max_dispensable
                                            : pd.prescription.quantity
                                    }
                                    value={review.quantity_to_dispense || ''}
                                    onChange={(e) =>
                                        onUpdateReview(
                                            index,
                                            'quantity_to_dispense',
                                            parseInt(e.target.value),
                                        )
                                    }
                                    disabled={!isPartial}
                                />
                                {isPartial && (
                                    <p className="text-xs text-muted-foreground">
                                        Maximum available: {pd.max_dispensable}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Reason/Notes */}
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                {needsReason ? 'Reason *' : 'Notes (Optional)'}
                            </label>
                            <Textarea
                                value={
                                    needsReason ? review.reason : review.notes
                                }
                                onChange={(e) =>
                                    onUpdateReview(
                                        index,
                                        needsReason ? 'reason' : 'notes',
                                        e.target.value,
                                    )
                                }
                                placeholder={
                                    needsReason
                                        ? 'Reason required...'
                                        : 'Optional notes...'
                                }
                                rows={2}
                                className={
                                    needsReason && !review.reason
                                        ? 'border-red-300 dark:border-red-700'
                                        : ''
                                }
                            />
                        </div>

                        {rowError && (
                            <div className="flex items-center gap-2 text-sm text-red-600 dark:text-red-400">
                                <AlertCircle className="h-4 w-4" />
                                {rowError}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
