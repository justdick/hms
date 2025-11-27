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

interface ImportMappingModalProps {
    isOpen: boolean;
    onClose: () => void;
}

export function ImportMappingModal({
    isOpen,
    onClose,
}: ImportMappingModalProps) {
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

        post('/admin/nhis-mappings/import', {
            forceFormData: true,
            onSuccess: () => {
                handleClose();
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
                    <DialogTitle>Import NHIS Mappings</DialogTitle>
                    <DialogDescription>
                        Upload a CSV file to bulk-map hospital items to NHIS
                        tariff codes.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="file">File *</Label>
                        <Input
                            ref={fileInputRef}
                            id="file"
                            type="file"
                            accept=".csv"
                            onChange={handleFileChange}
                            required
                        />
                        {errors.file && (
                            <p className="text-sm text-red-600">
                                {errors.file}
                            </p>
                        )}
                        <p className="text-sm text-gray-500">
                            Accepted format: CSV
                        </p>
                    </div>

                    <div className="rounded-lg border bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/30">
                        <h4 className="mb-2 font-medium text-blue-900 dark:text-blue-100">
                            Expected columns:
                        </h4>
                        <ul className="list-inside list-disc space-y-1 text-sm text-blue-800 dark:text-blue-200">
                            <li>
                                <strong>item_type</strong> - drug, lab_service,
                                procedure, or consumable (required)
                            </li>
                            <li>
                                <strong>item_code</strong> - Hospital item code
                                (required)
                            </li>
                            <li>
                                <strong>nhis_code</strong> - NHIS tariff code
                                (required)
                            </li>
                        </ul>
                    </div>

                    <div className="rounded-lg border bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
                        <h4 className="mb-2 font-medium text-amber-900 dark:text-amber-100">
                            Note:
                        </h4>
                        <p className="text-sm text-amber-800 dark:text-amber-200">
                            Existing mappings for the same item will be updated
                            with the new NHIS tariff code. Items or NHIS codes
                            that don't exist will be skipped with an error
                            message.
                        </p>
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
                            {processing ? 'Importing...' : 'Import Mappings'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
