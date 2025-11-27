import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { Calendar, Loader2, Package } from 'lucide-react';
import { FormEvent } from 'react';

interface Props {
    isOpen: boolean;
    onClose: () => void;
}

export default function CreateBatchModal({ isOpen, onClose }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        submission_period: new Date().toISOString().slice(0, 7), // Default to current month (YYYY-MM)
        notes: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/insurance/batches', {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    // Generate a suggested batch name based on the period
    const generateSuggestedName = () => {
        if (data.submission_period) {
            const date = new Date(data.submission_period + '-01');
            const monthName = date.toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric',
            });
            return `${monthName} Claims`;
        }
        return '';
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Package className="h-5 w-5" />
                        Create New Batch
                    </DialogTitle>
                    <DialogDescription>
                        Create a new claim batch for NHIA submission. You can
                        add vetted claims after creating the batch.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="submission_period">
                            Submission Period *
                        </Label>
                        <div className="relative">
                            <Calendar className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                            <Input
                                id="submission_period"
                                type="month"
                                value={data.submission_period}
                                onChange={(e) =>
                                    setData('submission_period', e.target.value)
                                }
                                className="pl-9"
                                required
                            />
                        </div>
                        {errors.submission_period && (
                            <p className="text-sm text-red-500">
                                {errors.submission_period}
                            </p>
                        )}
                        <p className="text-sm text-gray-500">
                            Select the month/year this batch covers
                        </p>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="name">Batch Name *</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder={
                                generateSuggestedName() ||
                                'e.g., November 2025 Claims'
                            }
                            required
                        />
                        {errors.name && (
                            <p className="text-sm text-red-500">
                                {errors.name}
                            </p>
                        )}
                        {!data.name && generateSuggestedName() && (
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                className="h-auto p-0 text-xs"
                                onClick={() =>
                                    setData('name', generateSuggestedName())
                                }
                            >
                                Use suggested: {generateSuggestedName()}
                            </Button>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="notes">Notes (Optional)</Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            placeholder="Any additional notes about this batch..."
                            rows={3}
                        />
                        {errors.notes && (
                            <p className="text-sm text-red-500">
                                {errors.notes}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Creating...
                                </>
                            ) : (
                                'Create Batch'
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
