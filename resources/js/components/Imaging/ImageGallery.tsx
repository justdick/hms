'use client';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    FileText,
    X,
    ZoomIn,
} from 'lucide-react';
import * as React from 'react';

export interface ImageAttachment {
    id: number;
    file_path?: string;
    file_name: string;
    file_type: string;
    file_size?: number;
    description?: string | null;
    is_external: boolean;
    external_facility_name?: string | null;
    external_study_date?: string | null;
    uploaded_by?: { id: number; name: string };
    uploaded_at?: string;
    url?: string;
    thumbnail_url?: string | null;
}

interface ImageGalleryProps {
    images: ImageAttachment[];
    className?: string;
    columns?: 2 | 3 | 4;
    showDownload?: boolean;
}

const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

export function ImageGallery({
    images,
    className,
    columns = 3,
    showDownload = true,
}: ImageGalleryProps) {
    const [lightboxOpen, setLightboxOpen] = React.useState(false);
    const [currentIndex, setCurrentIndex] = React.useState(0);

    const openLightbox = (index: number) => {
        setCurrentIndex(index);
        setLightboxOpen(true);
    };

    const goToPrevious = () => {
        setCurrentIndex((prev) => (prev === 0 ? images.length - 1 : prev - 1));
    };

    const goToNext = () => {
        setCurrentIndex((prev) => (prev === images.length - 1 ? 0 : prev + 1));
    };

    // Handle keyboard navigation
    React.useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (!lightboxOpen) return;

            if (e.key === 'ArrowLeft') {
                goToPrevious();
            } else if (e.key === 'ArrowRight') {
                goToNext();
            } else if (e.key === 'Escape') {
                setLightboxOpen(false);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [lightboxOpen, images.length]);

    const currentImage = images[currentIndex];

    const gridCols = {
        2: 'grid-cols-2',
        3: 'grid-cols-2 sm:grid-cols-3',
        4: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
    };

    if (images.length === 0) {
        return null;
    }

    return (
        <>
            {/* Thumbnail Grid */}
            <div className={cn('grid gap-3', gridCols[columns], className)}>
                {images.map((image, index) => (
                    <div
                        key={image.id}
                        className="group relative cursor-pointer overflow-hidden rounded-lg border bg-muted/30 transition-all hover:border-primary hover:shadow-md"
                        onClick={() => openLightbox(index)}
                    >
                        <div className="aspect-square overflow-hidden bg-muted">
                            {image.file_type.startsWith('image/') ? (
                                <img
                                    src={image.url}
                                    alt={image.description || image.file_name}
                                    className="h-full w-full object-cover transition-transform group-hover:scale-105"
                                    loading="lazy"
                                />
                            ) : (
                                <div className="flex h-full w-full flex-col items-center justify-center gap-2 bg-muted">
                                    <FileText className="h-10 w-10 text-muted-foreground" />
                                    <span className="text-xs text-muted-foreground">
                                        PDF
                                    </span>
                                </div>
                            )}
                        </div>

                        {/* Hover overlay */}
                        <div className="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                            <ZoomIn className="h-8 w-8 text-white" />
                        </div>

                        {/* Image info */}
                        <div className="p-2">
                            <p
                                className="truncate text-sm font-medium"
                                title={image.description || image.file_name}
                            >
                                {image.description || image.file_name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {formatFileSize(image.file_size ?? 0)}
                            </p>
                        </div>
                    </div>
                ))}
            </div>

            {/* Lightbox Dialog */}
            <Dialog open={lightboxOpen} onOpenChange={setLightboxOpen}>
                <DialogContent
                    className="max-w-5xl overflow-hidden bg-black/95 p-0"
                    showCloseButton={false}
                >
                    <DialogHeader className="sr-only">
                        <DialogTitle>
                            {currentImage?.description ||
                                currentImage?.file_name ||
                                'Image Viewer'}
                        </DialogTitle>
                    </DialogHeader>

                    {/* Close button */}
                    <Button
                        variant="ghost"
                        size="icon"
                        className="absolute top-2 right-2 z-50 h-10 w-10 rounded-full bg-black/50 text-white hover:bg-black/70"
                        onClick={() => setLightboxOpen(false)}
                    >
                        <X className="h-5 w-5" />
                    </Button>

                    {currentImage && (
                        <div className="relative flex flex-col">
                            {/* Main image area */}
                            <div className="relative flex min-h-[60vh] items-center justify-center p-4">
                                {/* Navigation buttons */}
                                {images.length > 1 && (
                                    <>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="absolute left-2 z-40 h-12 w-12 rounded-full bg-black/50 text-white hover:bg-black/70"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                goToPrevious();
                                            }}
                                        >
                                            <ChevronLeft className="h-8 w-8" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="absolute right-2 z-40 h-12 w-12 rounded-full bg-black/50 text-white hover:bg-black/70"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                goToNext();
                                            }}
                                        >
                                            <ChevronRight className="h-8 w-8" />
                                        </Button>
                                    </>
                                )}

                                {/* Image display */}
                                {currentImage.file_type.startsWith('image/') ? (
                                    <img
                                        src={currentImage.url}
                                        alt={
                                            currentImage.description ||
                                            currentImage.file_name
                                        }
                                        className="max-h-[70vh] max-w-full object-contain"
                                    />
                                ) : (
                                    <div className="flex flex-col items-center justify-center gap-4 py-12">
                                        <FileText className="h-20 w-20 text-white" />
                                        <p className="text-lg text-white">
                                            PDF Document
                                        </p>
                                        <Button variant="secondary" asChild>
                                            <a
                                                href={currentImage.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Open PDF in New Tab
                                            </a>
                                        </Button>
                                    </div>
                                )}
                            </div>

                            {/* Footer with info and controls */}
                            <div className="flex items-center justify-between border-t border-white/10 bg-black/80 px-4 py-3">
                                <div className="text-white">
                                    <p className="font-medium">
                                        {currentImage.description ||
                                            currentImage.file_name}
                                    </p>
                                    <p className="text-sm text-white/70">
                                        {images.length > 1 && (
                                            <span className="mr-2">
                                                {currentIndex + 1} of{' '}
                                                {images.length}
                                            </span>
                                        )}
                                        {formatFileSize(currentImage.file_size ?? 0)}
                                    </p>
                                </div>

                                {showDownload && (
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        asChild
                                    >
                                        <a
                                            href={`/radiology/attachments/${currentImage.id}/download`}
                                            download
                                        >
                                            <Download className="mr-2 h-4 w-4" />
                                            Download
                                        </a>
                                    </Button>
                                )}
                            </div>

                            {/* Thumbnail strip for multiple images */}
                            {images.length > 1 && (
                                <div className="flex gap-2 overflow-x-auto bg-black/90 p-3">
                                    {images.map((img, idx) => (
                                        <button
                                            key={img.id}
                                            onClick={() => setCurrentIndex(idx)}
                                            className={cn(
                                                'h-16 w-16 flex-shrink-0 overflow-hidden rounded border-2 transition-all',
                                                idx === currentIndex
                                                    ? 'border-primary'
                                                    : 'border-transparent opacity-60 hover:opacity-100',
                                            )}
                                        >
                                            {img.file_type.startsWith(
                                                'image/',
                                            ) ? (
                                                <img
                                                    src={img.url}
                                                    alt=""
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-full w-full items-center justify-center bg-muted">
                                                    <FileText className="h-6 w-6 text-muted-foreground" />
                                                </div>
                                            )}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

export default ImageGallery;
