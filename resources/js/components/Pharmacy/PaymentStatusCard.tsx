import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { CheckCircle, Clock, XCircle } from 'lucide-react';

interface PaymentStatusCardProps {
    status: 'paid' | 'pending' | 'voided';
    amount?: number;
    canDispense: boolean;
    className?: string;
}

export function PaymentStatusCard({
    status,
    amount,
    canDispense,
    className,
}: PaymentStatusCardProps) {
    const getStatusConfig = () => {
        switch (status) {
            case 'paid':
                return {
                    icon: CheckCircle,
                    label: 'Paid',
                    color: 'text-green-600 dark:text-green-400',
                    bgColor: 'bg-green-50 dark:bg-green-950/20',
                    borderColor: 'border-green-200 dark:border-green-800',
                };
            case 'voided':
                return {
                    icon: XCircle,
                    label: 'Voided',
                    color: 'text-gray-600 dark:text-gray-400',
                    bgColor: 'bg-gray-50 dark:bg-gray-950/20',
                    borderColor: 'border-gray-200 dark:border-gray-800',
                };
            default:
                return {
                    icon: Clock,
                    label: 'Pending Payment',
                    color: 'text-yellow-600 dark:text-yellow-400',
                    bgColor: 'bg-yellow-50 dark:bg-yellow-950/20',
                    borderColor: 'border-yellow-200 dark:border-yellow-800',
                };
        }
    };

    const config = getStatusConfig();
    const Icon = config.icon;

    return (
        <Card className={cn(config.bgColor, config.borderColor, className)}>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center justify-between text-sm">
                    <span>Payment Status</span>
                    <Badge
                        variant="outline"
                        className={cn('border-0', config.color)}
                    >
                        {config.label}
                    </Badge>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Icon className={cn('h-5 w-5', config.color)} />
                        <div>
                            {amount !== undefined && (
                                <p className="text-lg font-semibold">
                                    KSH {amount.toLocaleString()}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                {canDispense
                                    ? 'Ready to dispense'
                                    : 'Payment required'}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
