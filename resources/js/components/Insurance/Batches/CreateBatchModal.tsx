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
import { useForm } from '@inertiajs/react';
import { Calendar, FileText, Loader2, Package } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useState } from 'react';

interface Props {
    isOpen: boolean;
    onClose: () => void;
}

export default function CreateBatchModal({ isOpen, onClose }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        submission_period: '',
        notes: '',
    });

    const [vettedCount, setVettedCount] = useState<number | null>(null);
    const [batchExists, setBatchExists] = useState(false);
    const [loadingCount, setLoadingCount] = useState(false);

    const fetchVettedCount = useCallback((period: string) => {
        if (!period) return;
        setLoadingCount(true);
        fetch(
            `/admin/insurance/batches/vetted-claims-count?period=${period}-01`,
            { headers: { Accept: 'application/json' } },
        )
            .then((res) => res.json())
            .then((json) => {
                setVettedCount(json.count ?? 0);
                setBatchExists(json.batch_exists ?? false);
            })
            .catch(() => {
                setVettedCount(null);
                setBatchExists(false);
            })
            .finally(() => setLoadingCount(false));
    }, []);

    useEffect(() => {
        if (isOpen && data.submission_period) {
            fetchVettedCount(data.submission_period);
        }
    }, [isOpen, data.submission_period, fetchVettedCount]);

    const getBatchName = () => {
        if (!data.submission_period) return '';
        const date = new Date(data.submission_period + '-01');
        return (
            date.toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric',
            }) + ' Claims'
        );
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/insurance/batches', {
            onSuccess: () => {
                reset();
                setVettedCount(null);
                onClose();
            },
        });
    };

    const handleClose = () => {
        reset();
        setVettedCount(null);
        setBatchExists(false);
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[420px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Package className="h-5 w-5" />
                        Create Batch
                    </DialogTitle>
                    <DialogDescription>
                        Select a month to create a batch. All vetted claims for
                        that month will be added automatically.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="submission_period">Month *</Label>
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
                    </div>

                    {data.submission_period && (
                        <div className="rounded-lg border bg-muted/50 p-4 space-y-2">
                            {batchExists && (
                                <div className="rounded-md bg-destructive/10 border border-destructive/20 p-3 text-sm text-destructive">
                                    A batch already exists for this month. Please select a different period.
                                </div>
                            )}
                            <div className="flex items-center gap-2 text-sm">
                                <FileText className="h-4 w-4 text-muted-foreground" />
                                <span className="text-muted-foreground">
                                    Batch name:
                                </span>
                                <span className="font-medium">
                                    {getBatchName()}
                                </span>
                            </div>
                            <div className="flex items-center gap-2 text-sm">
                                <Package className="h-4 w-4 text-muted-foreground" />
                                <span className="text-muted-foreground">
                                    Vetted claims:
                                </span>
                                <span className="font-medium">
                                    {loadingCount
                                        ? 'Checking...'
                                        : vettedCount !== null
                                          ? `${vettedCount} claim(s)`
                                          : '—'}
                                </span>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || batchExists || !data.submission_period}>
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
