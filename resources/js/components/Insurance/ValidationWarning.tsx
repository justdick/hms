import { AlertTriangle } from 'lucide-react';

interface ValidationWarningProps {
    message: string;
    severity?: 'warning' | 'error';
    className?: string;
}

export function ValidationWarning({
    message,
    severity = 'warning',
    className = '',
}: ValidationWarningProps) {
    const colors =
        severity === 'error'
            ? {
                  bg: 'bg-red-50 dark:bg-red-950',
                  border: 'border-red-300 dark:border-red-700',
                  text: 'text-red-900 dark:text-red-100',
                  icon: 'text-red-600 dark:text-red-400',
              }
            : {
                  bg: 'bg-yellow-50 dark:bg-yellow-950',
                  border: 'border-yellow-300 dark:border-yellow-700',
                  text: 'text-yellow-900 dark:text-yellow-100',
                  icon: 'text-yellow-600 dark:text-yellow-400',
              };

    return (
        <div
            className={`rounded-lg border-2 ${colors.border} ${colors.bg} p-3 ${className}`}
            role={severity === 'error' ? 'alert' : 'status'}
            aria-live={severity === 'error' ? 'assertive' : 'polite'}
            aria-atomic="true"
        >
            <div className="flex items-start gap-2">
                <AlertTriangle className={`mt-0.5 h-5 w-5 ${colors.icon}`} aria-hidden="true" />
                <p className={`text-sm ${colors.text}`}>{message}</p>
            </div>
        </div>
    );
}

