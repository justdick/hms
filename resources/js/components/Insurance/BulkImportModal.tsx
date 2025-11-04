import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import axios from 'axios';
import {
    AlertCircle,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    Loader2,
    Upload,
    X,
} from 'lucide-react';
import { useCallback, useState } from 'react';

interface Props {
    open: boolean;
    onClose: () => void;
    planId: number;
    category: string;
    onSuccess?: () => void;
}

interface ImportResult {
    created: number;
    updated: number;
    skipped: number;
    errors: Array<{
        row: number;
        error: string;
    }>;
}

type Step = 'upload' | 'preview' | 'importing' | 'complete';

export default function BulkImportModal({
    open,
    onClose,
    planId,
    category,
    onSuccess,
}: Props) {
    const [step, setStep] = useState<Step>('upload');
    const [file, setFile] = useState<File | null>(null);
    const [dragActive, setDragActive] = useState(false);
    const [importing, setImporting] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const [error, setError] = useState<string | null>(null);

    const handleDownloadTemplate = async () => {
        setDownloading(true);
        setError(null);
        
        try {
            const response = await axios.get(
                `/admin/insurance/plans/${planId}/coverage-rules/template/${category}`,
                { responseType: 'blob' }
            );
            
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute(
                'download',
                `coverage_template_${category}_${new Date().toISOString().split('T')[0]}.xlsx`
            );
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err: any) {
            setError(err.response?.data?.error || 'Failed to download template. Please try again.');
        } finally {
            setDownloading(false);
        }
    };

    const handleDrag = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    }, []);

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFileSelect(e.dataTransfer.files[0]);
        }
    }, []);

    const handleFileSelect = (selectedFile: File) => {
        // Validate file type
        const validTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
        ];

        if (!validTypes.includes(selectedFile.type)) {
            setError('Please upload an Excel file (.xlsx or .xls)');
            return;
        }

        setFile(selectedFile);
        setError(null);
    };

    const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            handleFileSelect(e.target.files[0]);
        }
    };

    const handleUpload = () => {
        if (!file) return;
        handleImport();
    };

    const handleImport = async () => {
        if (!file) return;

        setImporting(true);
        setError(null);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('category', category);

        try {
            const response = await axios.post(
                `/admin/insurance/plans/${planId}/coverage-rules/import`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                }
            );

            if (response.data.success) {
                setImportResult(response.data.results);
                setStep('complete');

                // Refresh the page data
                if (onSuccess) {
                    onSuccess();
                }
            }
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to import coverage rules');
        } finally {
            setImporting(false);
        }
    };

    const handleClose = () => {
        setStep('upload');
        setFile(null);
        setImportResult(null);
        setError(null);
        onClose();
    };

    const renderUploadStep = () => (
        <>
            <DialogHeader>
                <DialogTitle>Bulk Import Coverage Rules</DialogTitle>
                <DialogDescription>
                    Download a pre-populated template and edit coverage settings for {category}
                </DialogDescription>
            </DialogHeader>

            <div className="space-y-4 py-4">
                {/* Pre-populated Template Info */}
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950">
                    <div className="flex items-start gap-3">
                        <FileSpreadsheet className="mt-0.5 size-5 text-blue-600 dark:text-blue-400" />
                        <div className="flex-1">
                            <h4 className="font-medium text-blue-900 dark:text-blue-100">
                                New: Pre-populated Templates!
                            </h4>
                            <p className="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                Download a template with ALL items already filled in from your system inventory
                            </p>
                            <ul className="mt-2 space-y-1 text-sm text-blue-700 dark:text-blue-300">
                                <li>• All items pre-filled with codes, names, and prices</li>
                                <li>• Just edit coverage_type and coverage_value columns</li>
                                <li>• Supports: <span className="font-mono">percentage</span>, <span className="font-mono">fixed_amount</span>, <span className="font-mono">full</span>, <span className="font-mono">excluded</span></li>
                                <li>• No manual data entry required!</li>
                            </ul>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="mt-3"
                                onClick={handleDownloadTemplate}
                                disabled={downloading}
                            >
                                {downloading ? (
                                    <>
                                        <Loader2 className="mr-2 size-4 animate-spin" />
                                        Downloading...
                                    </>
                                ) : (
                                    <>
                                        <Download className="mr-2 size-4" />
                                        Download Pre-populated Template
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* File Upload Area */}
                <div
                    className={`relative rounded-lg border-2 border-dashed p-8 text-center transition-colors ${
                        dragActive
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-950'
                            : 'border-gray-300 dark:border-gray-700'
                    }`}
                    onDragEnter={handleDrag}
                    onDragLeave={handleDrag}
                    onDragOver={handleDrag}
                    onDrop={handleDrop}
                >
                    <input
                        type="file"
                        id="file-upload"
                        className="hidden"
                        accept=".xlsx,.xls"
                        onChange={handleFileInputChange}
                    />

                    {file ? (
                        <div className="flex items-center justify-center gap-3">
                            <FileSpreadsheet className="size-8 text-green-600" />
                            <div className="text-left">
                                <p className="font-medium">{file.name}</p>
                                <p className="text-sm text-gray-500">
                                    {(file.size / 1024).toFixed(2)} KB
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setFile(null)}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    ) : (
                        <>
                            <Upload className="mx-auto size-12 text-gray-400" />
                            <p className="mt-2 text-sm font-medium">
                                Drag and drop your file here, or
                            </p>
                            <label htmlFor="file-upload">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="mt-2"
                                    onClick={() =>
                                        document.getElementById('file-upload')?.click()
                                    }
                                    aria-label="Browse and select Excel file for import"
                                >
                                    Browse Files
                                </Button>
                            </label>
                            <p className="mt-2 text-xs text-gray-500">
                                Supports .xlsx and .xls files
                            </p>
                        </>
                    )}
                </div>

                {error && (
                    <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        <AlertCircle className="mt-0.5 size-4 shrink-0" />
                        <span>{error}</span>
                    </div>
                )}
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={handleClose}>
                    Cancel
                </Button>
                <Button type="button" onClick={handleUpload} disabled={!file || importing}>
                    {importing ? (
                        <>
                            <Loader2 className="mr-2 size-4 animate-spin" />
                            Importing...
                        </>
                    ) : (
                        'Upload and Import'
                    )}
                </Button>
            </DialogFooter>
        </>
    );



    const renderCompleteStep = () => {
        const hasErrors = importResult && importResult.errors.length > 0;
        const hasSuccess = importResult && (importResult.created > 0 || importResult.updated > 0);

        return (
            <>
                <DialogHeader>
                    <DialogTitle>Import Results</DialogTitle>
                </DialogHeader>

                <div className="max-h-[60vh] space-y-4 overflow-y-auto py-4">
                    {/* Success Icon */}
                    {hasSuccess && !hasErrors && (
                        <div className="text-center">
                            <div className="animate-in zoom-in-50 duration-300">
                                <CheckCircle2 className="mx-auto size-16 text-green-600 dark:text-green-400" />
                            </div>
                            <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Successfully Imported!
                            </h3>
                        </div>
                    )}

                    {/* Summary Stats */}
                    {importResult && (
                        <div className="grid grid-cols-1 gap-4 xs:grid-cols-3">
                            <div className="rounded-lg border bg-green-50 p-3 dark:bg-green-950">
                                <p className="text-sm text-green-600 dark:text-green-400">
                                    Created
                                </p>
                                <p className="text-2xl font-bold text-green-700 dark:text-green-300">
                                    {importResult.created}
                                </p>
                            </div>
                            <div className="rounded-lg border bg-blue-50 p-3 dark:bg-blue-950">
                                <p className="text-sm text-blue-600 dark:text-blue-400">
                                    Updated
                                </p>
                                <p className="text-2xl font-bold text-blue-700 dark:text-blue-300">
                                    {importResult.updated}
                                </p>
                            </div>
                            <div className="rounded-lg border bg-gray-50 p-3 dark:bg-gray-900">
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    Skipped
                                </p>
                                <p className="text-2xl font-bold text-gray-700 dark:text-gray-300">
                                    {importResult.skipped}
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Success Message */}
                    {hasSuccess && !hasErrors && (
                        <div className="rounded-lg border-2 border-green-300 bg-green-50 p-4 dark:border-green-700 dark:bg-green-950">
                            <p className="text-sm font-medium text-green-900 dark:text-green-100">
                                Next Steps
                            </p>
                            <ul className="mt-2 space-y-1 text-sm text-green-800 dark:text-green-200">
                                <li>• All imported rules are now active</li>
                                <li>• They will be used immediately for coverage calculations</li>
                                <li>• You can view them in the coverage dashboard</li>
                            </ul>
                        </div>
                    )}

                    {/* Errors List */}
                    {hasErrors && (
                        <div>
                            <h4 className="mb-2 flex items-center gap-2 font-medium text-red-700 dark:text-red-300">
                                <AlertCircle className="size-4" />
                                Errors ({importResult.errors.length})
                            </h4>
                            <div className="max-h-60 space-y-2 overflow-y-auto rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950">
                                {importResult.errors.map((error, idx) => (
                                    <div
                                        key={idx}
                                        className="rounded border-l-4 border-red-500 bg-white p-2 text-sm dark:bg-gray-900"
                                    >
                                        <span className="font-medium text-red-900 dark:text-red-100">
                                            Row {error.row}:
                                        </span>{' '}
                                        <span className="text-red-800 dark:text-red-200">
                                            {error.error}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Fix the errors in your file and re-upload to import the skipped rows.
                            </p>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button type="button" onClick={handleClose}>
                        Close
                    </Button>
                </DialogFooter>
            </>
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto sm:max-h-[85vh]">
                {step === 'upload' && renderUploadStep()}
                {step === 'complete' && renderCompleteStep()}
            </DialogContent>
        </Dialog>
    );
}
