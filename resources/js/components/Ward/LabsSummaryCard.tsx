import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertTriangle, TestTube } from 'lucide-react';

interface LabService {
    id: number;
    name: string;
    code: string;
    price: number;
}

interface LabOrder {
    id: number;
    lab_service?: LabService;
    status: string;
    ordered_at: string;
    priority: string;
    special_instructions?: string;
    result_values?: any;
    result_notes?: string;
}

interface Props {
    labOrders: LabOrder[];
    onClick: () => void;
}

export function LabsSummaryCard({ labOrders, onClick }: Props) {
    const urgentLabs = labOrders.filter(
        (lab) => lab.priority === 'urgent' && lab.status !== 'completed',
    );

    // Display up to 5 most recent lab orders
    const displayLabOrders = labOrders.slice(0, 5);

    return (
        <Card
            className="cursor-pointer transition-all hover:shadow-md hover:border-blue-300 dark:hover:border-blue-700"
            onClick={onClick}
        >
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2 text-lg">
                        <TestTube className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                        Laboratory
                    </CardTitle>
                    {urgentLabs.length > 0 && (
                        <Badge
                            variant="outline"
                            className="border-red-500 text-red-700 dark:border-red-600 dark:text-red-400"
                        >
                            <AlertTriangle className="mr-1 h-3 w-3" />
                            {urgentLabs.length} urgent
                        </Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {labOrders.length > 0 ? (
                    <div className="space-y-3">
                        <div className="space-y-2">
                            {displayLabOrders.map((labOrder) => (
                                <div
                                    key={labOrder.id}
                                    className="rounded-lg border p-2 dark:border-gray-700"
                                >
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {labOrder.lab_service?.name ||
                                                'Lab Test'}
                                        </p>
                                        <Badge
                                            variant={
                                                labOrder.status === 'completed'
                                                    ? 'default'
                                                    : labOrder.status ===
                                                        'in_progress'
                                                      ? 'secondary'
                                                      : 'outline'
                                            }
                                            className="text-xs"
                                        >
                                            {labOrder.status}
                                        </Badge>
                                    </div>
                                    {labOrder.priority === 'urgent' && (
                                        <p className="text-xs text-red-600 dark:text-red-400">
                                            Urgent
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                        {labOrders.length > 5 && (
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                +{labOrders.length - 5} more lab order
                                {labOrders.length - 5 !== 1 ? 's' : ''}
                            </p>
                        )}
                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Total: {labOrders.length} lab order
                            {labOrders.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                ) : (
                    <div className="py-4 text-center">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            No labs ordered
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
