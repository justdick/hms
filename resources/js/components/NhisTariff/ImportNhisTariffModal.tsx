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
import { FormEvent, useRef } from 'react';

interface ImportNhisTariffModalProps {
    isOpen: boolean;
    onClose: () => void;
}

export function ImportNhisTariffModal({
    isOpen,
    onClose,
}: ImportNhisTariffModalProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset, progress } =
        useForm<{
            file: File | null;
        }>({
            file: null,
        });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setData('file', file);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!data.file) return;

        post('/admin/nhis-tariffs/import', {
            forceFormData: true,
            onSuccess: () => {
                onClose();
                reset();
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    };

    const handleClose = () => {
        onClose();
        reset();
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Import NHIS Tariffs</DialogTitle>
                    <DialogDescription>
                        Upload a CSV or Excel file containing NHIS tariff data.
                        Existing tariffs with matching codes will be updated.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="file">File *</Label>
                        <Input
                            ref={fileInputRef}
                            id="file"
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            onChange={handleFileChange}
                            required
                        />
                        {errors.file && (
                            <p className="text-sm text-red-600">
                                {errors.file}
                            </p>
                        )}
                        <p className="text-sm text-gray-500">
                            Accepted formats: CSV, Excel (.xlsx, .xls)
                        </p>
                    </div>

                    <div className="rounded-lg border bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/30">
                        <h4 className="mb-2 font-medium text-blue-900 dark:text-blue-100">
                            Expected columns:
                        </h4>
                        <ul className="list-inside list-disc space-y-1 text-sm text-blue-800 dark:text-blue-200">
                            <li>
                                <strong>nhis_code</strong> - Unique NHIS code
                                (required)
                            </li>
                            <li>
                                <strong>name</strong> - Tariff name (required)
                            </li>
                            <li>
                                <strong>category</strong> - medicine, lab,
                                procedure, consultation, or consumable
                                (required)
                            </li>
                            <li>
                                <strong>price</strong> - Price in GHS (required)
                            </li>
                            <li>
                                <strong>unit</strong> - Unit of measure
                                (optional)
                            </li>
                        </ul>
                    </div>

                    {progress && (
                        <div className="space-y-2">
                            <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                <div
                                    className="h-full bg-blue-600 transition-all"
                                    style={{ width: `${progress.percentage}%` }}
                                />
                            </div>
                            <p className="text-sm text-gray-500">
                                Uploading... {progress.percentage}%
                            </p>
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
                        <Button
                            type="submit"
                            disabled={processing || !data.file}
                        >
                            {processing ? 'Importing...' : 'Import Tariffs'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
