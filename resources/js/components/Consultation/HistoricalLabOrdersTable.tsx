import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { TestTube } from 'lucide-react';

interface LabService {
    id: number;
    name: string;
    code?: string;
    category?: string;
}

interface Doctor {
    id: number;
    name: string;
}

interface LabOrder {
    id: number;
    lab_service: LabService;
    status: string;
    priority?: string;
    ordered_at: string;
    sample_collected_at?: string;
    result_at?: string;
    ordered_by?: Doctor;
    consultation?: {
        doctor: Doctor;
    };
}

interface Props {
    labOrders: LabOrder[];
}

export function HistoricalLabOrdersTable({ labOrders }: Props) {
    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusBadge = (status: string) => {
        const statusConfig: Record<string, { variant: any; label: string }> = {
            pending: { variant: 'secondary', label: 'Pending' },
            collected: { variant: 'default', label: 'Sample Collected' },
            processing: { variant: 'default', label: 'Processing' },
            completed: { variant: 'secondary', label: 'Completed' },
            cancelled: { variant: 'destructive', label: 'Cancelled' },
        };

        const config = statusConfig[status] || {
            variant: 'default',
            label: status,
        };
        return (
            <Badge variant={config.variant as any} className="capitalize">
                {config.label}
            </Badge>
        );
    };

    const getPriorityBadge = (priority?: string) => {
        if (!priority) return <span className="text-gray-400">Normal</span>;

        const priorityConfig: Record<string, { variant: any; label: string }> =
            {
                routine: { variant: 'outline', label: 'Routine' },
                urgent: { variant: 'default', label: 'Urgent' },
                stat: { variant: 'destructive', label: 'STAT' },
            };

        const config = priorityConfig[priority.toLowerCase()] || {
            variant: 'outline',
            label: priority,
        };
        return (
            <Badge variant={config.variant as any} className="capitalize">
                {config.label}
            </Badge>
        );
    };

    if (labOrders.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <TestTube className="h-5 w-5" />
                        Lab Orders History During Admission
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                        <TestTube className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                        <p>No lab orders placed yet for this admission</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <TestTube className="h-5 w-5 text-teal-600 dark:text-teal-400" />
                    Lab Orders History During Admission
                </CardTitle>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {labOrders.length} lab order(s) during this admission
                </p>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date Ordered</TableHead>
                                <TableHead>Test Name</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Priority</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Ordered By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {labOrders.map((order) => (
                                <TableRow key={order.id}>
                                    <TableCell className="font-medium">
                                        {formatDateTime(order.ordered_at)}
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <p className="font-medium">
                                                {order.lab_service.name}
                                            </p>
                                            {order.lab_service.code && (
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    Code:{' '}
                                                    {order.lab_service.code}
                                                </p>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className="capitalize"
                                        >
                                            {order.lab_service.category ||
                                                'General'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {getPriorityBadge(order.priority)}
                                    </TableCell>
                                    <TableCell>
                                        {getStatusBadge(order.status)}
                                    </TableCell>
                                    <TableCell className="text-sm text-gray-600 dark:text-gray-400">
                                        {order.ordered_by?.name ||
                                            order.consultation?.doctor?.name ||
                                            'N/A'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    );
}
