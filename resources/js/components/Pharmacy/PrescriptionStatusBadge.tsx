import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface PrescriptionStatusBadgeProps {
    status:
        | 'prescribed'
        | 'reviewed'
        | 'dispensed'
        | 'partially_dispensed'
        | 'not_dispensed'
        | 'cancelled';
    className?: string;
}

export function PrescriptionStatusBadge({
    status,
    className,
}: PrescriptionStatusBadgeProps) {
    const getStatusConfig = () => {
        switch (status) {
            case 'prescribed':
                return {
                    label: 'Prescribed',
                    variant: 'secondary' as const,
                    className:
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                };
            case 'reviewed':
                return {
                    label: 'Reviewed',
                    variant: 'secondary' as const,
                    className:
                        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                };
            case 'dispensed':
                return {
                    label: 'Dispensed',
                    variant: 'secondary' as const,
                    className:
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                };
            case 'partially_dispensed':
                return {
                    label: 'Partially Dispensed',
                    variant: 'secondary' as const,
                    className:
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                };
            case 'not_dispensed':
                return {
                    label: 'External',
                    variant: 'secondary' as const,
                    className:
                        'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                };
            case 'cancelled':
                return {
                    label: 'Cancelled',
                    variant: 'secondary' as const,
                    className:
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                };
            default:
                return {
                    label: status,
                    variant: 'secondary' as const,
                    className: '',
                };
        }
    };

    const config = getStatusConfig();

    return (
        <Badge
            variant={config.variant}
            className={cn(config.className, className)}
        >
            {config.label}
        </Badge>
    );
}
