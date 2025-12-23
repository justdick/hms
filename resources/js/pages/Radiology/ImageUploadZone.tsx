import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import {
    AlertCircle,
    CheckCircle,
    FileText,
    Loader2,
    Upload,
    X,
} from 'lucide-react';
import * as React from 'react';

interface FileWithPreview {
    file: File;
    id: string;
    preview: string | null;
    description: string;
    status: 'pending' | 'uploading' | 'success' | 'error';
    progress: number;
    error?: string;
}

interface Props {
    labOrderId: number;
    onUploadComplete?: () => void;
    maxFileSize?: number; // in MB
    acceptedTypes?: string[];
}

const DEFAULT_MAX_SIZE = 50; // 50MB
const DEFAULT_ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

export function ImageUploadZone({
    labOrderId,
    onUploadComplete,
    maxFileSize = DEFAULT_MAX_SIZE,
    acceptedTypes = DEFAULT_ACCEPTED_TYPES,
}: Props) {
    const [files, setFiles] = React.useState<FileWithPreview[]>([]);
    const [isDragging, setIsDragging] = React.useState(false);
    const [isUploading, setIsUploading] = React.useState(false);
    const fileInputRef = React.useRef<HTMLInputElement>(null);

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const validateFile = (file: File): string | null => {
        // Check file type
        if (!acceptedTypes.includes(file.type)) {
            return `Invalid file type. Accepted: ${acceptedTypes.map((t) => t.split('/')[1].toUpperCase()).join(', ')}`;
        }

        // Check file size
        const maxSizeBytes = maxFileSize * 1024 * 1024;
        if (file.size > maxSizeBytes) {
            return `File too large. Maximum size: ${maxFileSize}MB`;
        }

        return null;
    };

    const createFilePreview = (file: File): string | null => {
        if (file.type.startsWith('image/')) {
            return URL.createObjectURL(file);
        }
        return null;
    };

    const addFiles = (newFiles: FileList | File[]) => {
        const fileArray = Array.from(newFiles);
        const newFileItems: FileWithPreview[] = fileArray.map((file) => {
            const error = validateFile(file);
            return {
                file,
                id: `${file.name}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
                preview: createFilePreview(file),
                description: '',
                status: error ? 'error' : 'pending',
                progress: 0,
                error: error || undefined,
            };
        });

        setFiles((prev) => [...prev, ...newFileItems]);
    };

    const removeFile = (id: string) => {
        setFiles((prev) => {
            const file = prev.find((f) => f.id === id);
            if (file?.preview) {
                URL.revokeObjectURL(file.preview);
            }
            return prev.filter((f) => f.id !== id);
        });
    };

    const updateFileDescription = (id: string, description: string) => {
        setFiles((prev) =>
            prev.map((f) => (f.id === id ? { ...f, description } : f)),
        );
    };

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        const droppedFiles = e.dataTransfer.files;
        if (droppedFiles.length > 0) {
            addFiles(droppedFiles);
        }
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFiles = e.target.files;
        if (selectedFiles && selectedFiles.length > 0) {
            addFiles(selectedFiles);
        }
        // Reset input so same file can be selected again
        e.target.value = '';
    };

    const uploadFiles = async () => {
        const pendingFiles = files.filter((f) => f.status === 'pending');
        if (pendingFiles.length === 0) return;

        setIsUploading(true);

        for (const fileItem of pendingFiles) {
            // Update status to uploading
            setFiles((prev) =>
                prev.map((f) =>
                    f.id === fileItem.id
                        ? { ...f, status: 'uploading', progress: 0 }
                        : f,
                ),
            );

            const formData = new FormData();
            formData.append('file', fileItem.file);
            formData.append('description', fileItem.description);

            try {
                // Use fetch for progress tracking
                const xhr = new XMLHttpRequest();

                await new Promise<void>((resolve, reject) => {
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const progress = Math.round(
                                (e.loaded / e.total) * 100,
                            );
                            setFiles((prev) =>
                                prev.map((f) =>
                                    f.id === fileItem.id
                                        ? { ...f, progress }
                                        : f,
                                ),
                            );
                        }
                    });

                    xhr.addEventListener('load', () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            setFiles((prev) =>
                                prev.map((f) =>
                                    f.id === fileItem.id
                                        ? {
                                              ...f,
                                              status: 'success',
                                              progress: 100,
                                          }
                                        : f,
                                ),
                            );
                            resolve();
                        } else {
                            let errorMessage = 'Upload failed';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMessage =
                                    response.message ||
                                    response.errors?.file?.[0] ||
                                    errorMessage;
                            } catch {
                                // Use default error message
                            }
                            setFiles((prev) =>
                                prev.map((f) =>
                                    f.id === fileItem.id
                                        ? {
                                              ...f,
                                              status: 'error',
                                              error: errorMessage,
                                          }
                                        : f,
                                ),
                            );
                            reject(new Error(errorMessage));
                        }
                    });

                    xhr.addEventListener('error', () => {
                        setFiles((prev) =>
                            prev.map((f) =>
                                f.id === fileItem.id
                                    ? {
                                          ...f,
                                          status: 'error',
                                          error: 'Network error',
                                      }
                                    : f,
                            ),
                        );
                        reject(new Error('Network error'));
                    });

                    xhr.open(
                        'POST',
                        `/radiology/orders/${labOrderId}/attachments`,
                    );
                    xhr.setRequestHeader(
                        'X-CSRF-TOKEN',
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                    );
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.send(formData);
                });
            } catch (error) {
                // Error already handled in xhr event handlers
                console.error('Upload error:', error);
            }
        }

        setIsUploading(false);

        // Check if all files uploaded successfully
        const allSuccess = files.every(
            (f) => f.status === 'success' || f.status === 'error',
        );
        if (allSuccess && onUploadComplete) {
            onUploadComplete();
        }
    };

    const pendingCount = files.filter((f) => f.status === 'pending').length;
    const hasErrors = files.some((f) => f.status === 'error');

    // Cleanup previews on unmount
    React.useEffect(() => {
        return () => {
            files.forEach((f) => {
                if (f.preview) {
                    URL.revokeObjectURL(f.preview);
                }
            });
        };
    }, []);

    return (
        <div className="space-y-4">
            {/* Drop Zone */}
            <div
                className={cn(
                    'relative rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                    isDragging
                        ? 'border-primary bg-primary/5'
                        : 'border-muted-foreground/25 hover:border-muted-foreground/50',
                )}
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
            >
                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    accept={acceptedTypes.join(',')}
                    onChange={handleFileSelect}
                    className="hidden"
                />
                <div className="flex flex-col items-center gap-2">
                    <Upload className="h-10 w-10 text-muted-foreground" />
                    <div>
                        <p className="text-sm font-medium">
                            Drag and drop files here, or{' '}
                            <button
                                type="button"
                                className="text-primary underline hover:no-underline"
                                onClick={() => fileInputRef.current?.click()}
                            >
                                browse
                            </button>
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            JPEG, PNG, PDF up to {maxFileSize}MB each
                        </p>
                    </div>
                </div>
            </div>

            {/* File List */}
            {files.length > 0 && (
                <div className="space-y-3">
                    <Label>Selected Files ({files.length})</Label>
                    <div className="max-h-[300px] space-y-2 overflow-y-auto">
                        {files.map((fileItem) => (
                            <div
                                key={fileItem.id}
                                className={cn(
                                    'flex items-start gap-3 rounded-lg border p-3',
                                    fileItem.status === 'error' &&
                                        'border-destructive bg-destructive/5',
                                    fileItem.status === 'success' &&
                                        'border-green-500 bg-green-50 dark:bg-green-950/20',
                                )}
                            >
                                {/* Preview */}
                                <div className="h-16 w-16 flex-shrink-0 overflow-hidden rounded-md bg-muted">
                                    {fileItem.preview ? (
                                        <img
                                            src={fileItem.preview}
                                            alt={fileItem.file.name}
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-full w-full items-center justify-center">
                                            <FileText className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                    )}
                                </div>

                                {/* File Info */}
                                <div className="min-w-0 flex-1 space-y-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p
                                                className="truncate text-sm font-medium"
                                                title={fileItem.file.name}
                                            >
                                                {fileItem.file.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatFileSize(
                                                    fileItem.file.size,
                                                )}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            {fileItem.status ===
                                                'uploading' && (
                                                <Loader2 className="h-4 w-4 animate-spin text-primary" />
                                            )}
                                            {fileItem.status === 'success' && (
                                                <CheckCircle className="h-4 w-4 text-green-600" />
                                            )}
                                            {fileItem.status === 'error' && (
                                                <AlertCircle className="h-4 w-4 text-destructive" />
                                            )}
                                            {fileItem.status !==
                                                'uploading' && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-6 w-6"
                                                    onClick={() =>
                                                        removeFile(fileItem.id)
                                                    }
                                                >
                                                    <X className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>

                                    {/* Description Input */}
                                    {fileItem.status === 'pending' && (
                                        <Input
                                            placeholder="Description (e.g., PA View, Lateral View)"
                                            value={fileItem.description}
                                            onChange={(e) =>
                                                updateFileDescription(
                                                    fileItem.id,
                                                    e.target.value,
                                                )
                                            }
                                            className="h-8 text-sm"
                                        />
                                    )}

                                    {/* Progress Bar */}
                                    {fileItem.status === 'uploading' && (
                                        <Progress
                                            value={fileItem.progress}
                                            className="h-1"
                                        />
                                    )}

                                    {/* Error Message */}
                                    {fileItem.status === 'error' &&
                                        fileItem.error && (
                                            <p className="text-xs text-destructive">
                                                {fileItem.error}
                                            </p>
                                        )}

                                    {/* Success Message */}
                                    {fileItem.status === 'success' && (
                                        <p className="text-xs text-green-600">
                                            Uploaded successfully
                                        </p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Upload Button */}
            {files.length > 0 && (
                <div className="flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        {pendingCount > 0 &&
                            `${pendingCount} file(s) ready to upload`}
                        {hasErrors && (
                            <span className="ml-2 text-destructive">
                                Some files have errors
                            </span>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setFiles([])}
                            disabled={isUploading}
                        >
                            Clear All
                        </Button>
                        <Button
                            type="button"
                            onClick={uploadFiles}
                            disabled={isUploading || pendingCount === 0}
                        >
                            {isUploading ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Uploading...
                                </>
                            ) : (
                                <>
                                    <Upload className="mr-2 h-4 w-4" />
                                    Upload {pendingCount} File
                                    {pendingCount !== 1 ? 's' : ''}
                                </>
                            )}
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
