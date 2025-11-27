import { CheckCircle2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface SuccessMessageProps {
    message: string;
    nextSteps?: string;
    duration?: number;
    onClose?: () => void;
    className?: string;
}

export function SuccessMessage({
    message,
    nextSteps,
    duration = 5000,
    onClose,
    className = '',
}: SuccessMessageProps) {
    const [isVisible, setIsVisible] = useState(true);

    useEffect(() => {
        if (duration > 0) {
            const timer = setTimeout(() => {
                setIsVisible(false);
                if (onClose) {
                    setTimeout(onClose, 300); // Wait for fade out animation
                }
            }, duration);

            return () => clearTimeout(timer);
        }
    }, [duration, onClose]);

    if (!isVisible) {
        return null;
    }

    return (
        <div
            className={`rounded-lg border-2 border-green-300 bg-green-50 p-4 transition-all duration-300 animate-in fade-in slide-in-from-top-2 dark:border-green-700 dark:bg-green-950 ${
                !isVisible ? 'animate-out fade-out slide-out-to-top-2' : ''
            } ${className}`}
            role="status"
            aria-live="polite"
            aria-atomic="true"
        >
            <div className="flex items-start gap-3">
                <CheckCircle2
                    className="mt-0.5 h-6 w-6 flex-shrink-0 text-green-600 dark:text-green-400"
                    aria-hidden="true"
                />
                <div className="flex-1">
                    <p className="font-semibold text-green-900 dark:text-green-100">
                        {message}
                    </p>
                    {nextSteps && (
                        <p className="mt-1 text-sm text-green-800 dark:text-green-200">
                            {nextSteps}
                        </p>
                    )}
                </div>
                {onClose && (
                    <button
                        onClick={() => {
                            setIsVisible(false);
                            setTimeout(onClose, 300);
                        }}
                        className="text-green-600 transition-colors hover:text-green-800 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:outline-none dark:text-green-400 dark:hover:text-green-200"
                        aria-label="Close success message"
                    >
                        <span className="sr-only">Close</span>
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                )}
            </div>
        </div>
    );
}
