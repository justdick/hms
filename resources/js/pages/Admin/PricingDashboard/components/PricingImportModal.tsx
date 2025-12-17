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
import { Progress } from '@/components/ui/progress';
import { router } from '@inertiajs/react';
import { AlertCircle, CheckCircle, FileSpreadsheet, Upload } from 'lucide-react';
import { ChangeEvent, useState } from 'react';

interface PricingImportModalProps {
    open: boolean;
    onClose: () => void;
    planId: number | null;
}

interface ImportResult {
    success: boolean;
    message: string;
    details?: {
        updated: number;
        skipped: number;
        errors: number;
    };
}

export function PricingImportModal({
    open,
    onClose,
    planId,
}: PricingImportModalProps) {
    const [file, setFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const [progress, setProgress] = useState(0);
    const [result, setResult] = useState<ImportResult | null>(null);

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0];
        if (selectedFile) {
            // Validate file type
            const validTypes = [
                'text/csv',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
            if (
                !validTypes.includes(selectedFile.type) &&
                !selectedFile.name.endsWith('.csv')
            ) {
                setResult({
                    success: false,
                    message: 'Please select a CSV or Excel file',
                });
                return;
            }
            setFile(selectedFile);
            setResult(null);
        }
    };

    const handleSubmit = () => {
        if (!file) return;

        setProcessing(true);
        setProgress(0);
        setResult(null);

        // Simulate progress
        const progressInterval = setInterval(() => {
            setProgress((prev) => Math.min(prev + 10, 90));
        }, 200);

        const formData = new FormData();
        formData.append('file', file);
        if (planId) {
            formData.append('plan_id', planId.toString());
        }

        router.post('/admin/pricing-dashboard/import', formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: (page) => {
                clearInterval(progressInterval);
                setProgress(100);
                setProcessing(false);
                
                const flash = page.props.flash as { success?: string; error?: string } | undefined;
                if (flash?.success) {
                    // Parse the success message for details
                    const match = flash.success.match(
                        /(\d+) items updated, (\d+) skipped/,
                    );
                    setResult({
                        success: true,
                        message: flash.success,
                        details: match
                            ? {
                                  updated: parseInt(match[1]),
                                  skipped: parseInt(match[2]),
                                  errors: 0,
                              }
                            : undefined,
                    });
                } else {
                    setResult({
                        success: true,
                        message: 'Import completed successfully',
                    });
                }
            },
            onError: (errors) => {
                clearInterval(progressInterval);
                setProgress(0);
                setProcessing(false);
                setResult({
                    success: false,
                    message:
                        Object.values(errors).flat().join(', ') ||
                        'Failed to import file',
                });
            },
        });
    };

    const handleClose = () => {
        if (!processing) {
            onClose();
            setFile(null);
            setResult(null);
            setProgress(0);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Upload className="h-5 w-5" />
                        Import Pricing Data
                    </DialogTitle>
                    <DialogDescription>
                        Upload a CSV file to bulk update pricing data.
                        {planId
                            ? ' Copay amounts will be updated for the selected insurance plan.'
                            : ' Only cash prices will be updated.'}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* File Input */}
                    <div className="space-y-2">
                        <Label htmlFor="import-file">Select File</Label>
                        <div className="flex items-center gap-2">
                            <Input
                                id="import-file"
                                type="file"
                                accept=".csv,.xlsx,.xls"
                                onChange={handleFileChange}
                                disabled={processing}
                                className="cursor-pointer"
                            />
                        </div>
                        {file && (
                            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <FileSpreadsheet className="h-4 w-4" />
                                {file.name} ({(file.size / 1024).toFixed(1)} KB)
                            </div>
                        )}
                    </div>

                    {/* File Format Info */}
                    <div className="rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-900">
                        <p className="font-medium text-gray-700 dark:text-gray-300">
                            Expected columns:
                        </p>
                        <ul className="mt-1 list-inside list-disc text-gray-600 dark:text-gray-400">
                            <li>
                                <code className="rounded bg-gray-200 px-1 dark:bg-gray-800">
                                    Code
                                </code>{' '}
                                - Item code (required)
                            </li>
                            <li>
                                <code className="rounded bg-gray-200 px-1 dark:bg-gray-800">
                                    Cash Price
                                </code>{' '}
                                - New cash price
                            </li>
                            {planId && (
                                <li>
                                    <code className="rounded bg-gray-200 px-1 dark:bg-gray-800">
                                        Patient Copay
                                    </code>{' '}
                                    - Copay amount
                                </li>
                            )}
                        </ul>
                        <p className="mt-2 text-xs text-gray-500">
                            Download the template for the correct format.
                        </p>
                    </div>

                    {/* Progress Bar */}
                    {processing && (
                        <div className="space-y-2">
                            <Progress value={progress} />
                            <p className="text-center text-sm text-gray-500">
                                Processing... {progress}%
                            </p>
                        </div>
                    )}

                    {/* Result Message */}
                    {result && (
                        <div
                            className={`rounded-md p-3 ${
                                result.success
                                    ? 'bg-green-50 dark:bg-green-950'
                                    : 'bg-red-50 dark:bg-red-950'
                            }`}
                        >
                            <div className="flex items-center gap-2">
                                {result.success ? (
                                    <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                                ) : (
                                    <AlertCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
                                )}
                                <span
                                    className={`text-sm font-medium ${
                                        result.success
                                            ? 'text-green-700 dark:text-green-300'
                                            : 'text-red-700 dark:text-red-300'
                                    }`}
                                >
                                    {result.message}
                                </span>
                            </div>
                            {result.details && (
                                <div className="mt-2 grid grid-cols-3 gap-2 text-center text-xs">
                                    <div className="rounded bg-green-100 p-2 dark:bg-green-900">
                                        <div className="font-bold text-green-700 dark:text-green-300">
                                            {result.details.updated}
                                        </div>
                                        <div className="text-green-600 dark:text-green-400">
                                            Updated
                                        </div>
                                    </div>
                                    <div className="rounded bg-yellow-100 p-2 dark:bg-yellow-900">
                                        <div className="font-bold text-yellow-700 dark:text-yellow-300">
                                            {result.details.skipped}
                                        </div>
                                        <div className="text-yellow-600 dark:text-yellow-400">
                                            Skipped
                                        </div>
                                    </div>
                                    <div className="rounded bg-red-100 p-2 dark:bg-red-900">
                                        <div className="font-bold text-red-700 dark:text-red-300">
                                            {result.details.errors}
                                        </div>
                                        <div className="text-red-600 dark:text-red-400">
                                            Errors
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={handleClose}
                        disabled={processing}
                    >
                        {result?.success ? 'Close' : 'Cancel'}
                    </Button>
                    {!result?.success && (
                        <Button
                            onClick={handleSubmit}
                            disabled={processing || !file}
                        >
                            {processing ? 'Importing...' : 'Import'}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
