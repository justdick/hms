import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { AlertCircle, ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    price: number;
    sample_type: string;
}

interface ResultParameter {
    value: string | number;
    unit?: string;
    range?: string;
    flag?: 'normal' | 'high' | 'low' | 'critical';
}

interface LabOrder {
    id: number;
    lab_service: LabService;
    status:
        | 'ordered'
        | 'sample_collected'
        | 'in_progress'
        | 'completed'
        | 'cancelled';
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
    ordered_at: string;
    sample_collected_at?: string;
    result_entered_at?: string;
    result_values?: Record<string, ResultParameter>;
    result_notes?: string;
    ordered_by?: {
        id: number;
        name: string;
    };
}

interface Props {
    order: LabOrder;
    defaultExpanded?: boolean;
}

export function PreviousLabResultCard({
    order,
    defaultExpanded = false,
}: Props) {
    const [isExpanded, setIsExpanded] = useState(defaultExpanded);

    const hasResults =
        order.status === 'completed' &&
        (order.result_values || order.result_notes);

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'completed':
                return 'bg-green-100 dark:bg-green-950 text-green-700 dark:text-green-400 border-green-200 dark:border-green-900';
            case 'in_progress':
                return 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-900';
            case 'sample_collected':
                return 'bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-400 border-purple-200 dark:border-purple-900';
            case 'cancelled':
                return 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-gray-700';
            default:
                return 'bg-yellow-100 dark:bg-yellow-950 text-yellow-700 dark:text-yellow-400 border-yellow-200 dark:border-yellow-900';
        }
    };

    const getValueColor = (flag?: string) => {
        switch (flag) {
            case 'high':
            case 'critical':
                return 'text-red-600 dark:text-red-400 font-semibold';
            case 'low':
                return 'text-orange-600 dark:text-orange-400 font-semibold';
            default:
                return 'text-gray-900 dark:text-gray-100';
        }
    };

    return (
        <Card className="dark:border-gray-800 dark:bg-gray-900">
            <CardContent className="pt-4">
                <div
                    className={`flex items-start justify-between ${hasResults ? 'cursor-pointer' : ''}`}
                    onClick={() => hasResults && setIsExpanded(!isExpanded)}
                >
                    <div className="flex-1">
                        <div className="flex items-start gap-2">
                            {hasResults && (
                                <div className="mt-1">
                                    {isExpanded ? (
                                        <ChevronDown className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                    ) : (
                                        <ChevronRight className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                    )}
                                </div>
                            )}
                            <div>
                                <h5 className="font-semibold text-gray-900 dark:text-gray-100">
                                    {order.lab_service.name}
                                </h5>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Code: {order.lab_service.code} • Category:{' '}
                                    {order.lab_service.category}
                                </p>
                                {order.special_instructions && (
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Instructions:{' '}
                                        {order.special_instructions}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col items-end gap-2">
                        <div className="flex gap-2">
                            <Badge
                                variant="outline"
                                className={getStatusColor(order.status)}
                            >
                                {order.status === 'sample_collected'
                                    ? 'Sample Collected'
                                    : order.status.toUpperCase()}
                            </Badge>
                            {order.priority !== 'routine' && (
                                <Badge
                                    variant="destructive"
                                    className="dark:bg-red-900"
                                >
                                    {order.priority.toUpperCase()}
                                </Badge>
                            )}
                        </div>
                        {hasResults && (
                            <span className="text-xs font-medium text-green-600 dark:text-green-400">
                                Results Ready
                            </span>
                        )}
                    </div>
                </div>

                {/* Expanded Results Section */}
                {isExpanded && hasResults && (
                    <div className="mt-4 space-y-4 border-t pt-4 dark:border-gray-800">
                        {/* Result Parameters Table */}
                        {order.result_values &&
                            Object.keys(order.result_values).length > 0 && (
                                <div>
                                    <h6 className="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        Test Results
                                    </h6>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b dark:border-gray-800">
                                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">
                                                        Parameter
                                                    </th>
                                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">
                                                        Value
                                                    </th>
                                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">
                                                        Reference Range
                                                    </th>
                                                    <th className="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">
                                                        Status
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {Object.entries(
                                                    order.result_values,
                                                ).map(([param, result]) => (
                                                    <tr
                                                        key={param}
                                                        className="border-b hover:bg-gray-50 dark:border-gray-800/50 dark:hover:bg-gray-800/50"
                                                    >
                                                        <td className="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                                                            {param}
                                                        </td>
                                                        <td
                                                            className={`px-3 py-2 ${getValueColor(result.flag)}`}
                                                        >
                                                            {result.value}{' '}
                                                            {result.unit && (
                                                                <span className="text-gray-500 dark:text-gray-400">
                                                                    {
                                                                        result.unit
                                                                    }
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 text-gray-600 dark:text-gray-400">
                                                            {result.range ||
                                                                '-'}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {result.flag &&
                                                            result.flag !==
                                                                'normal' ? (
                                                                <div className="flex items-center gap-1">
                                                                    <AlertCircle className="h-3 w-3 text-red-500 dark:text-red-400" />
                                                                    <span
                                                                        className={getValueColor(
                                                                            result.flag,
                                                                        )}
                                                                    >
                                                                        {result.flag.toUpperCase()}
                                                                    </span>
                                                                </div>
                                                            ) : (
                                                                <span className="text-green-600 dark:text-green-400">
                                                                    Normal
                                                                </span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                        {/* Result Notes */}
                        {order.result_notes && (
                            <div>
                                <h6 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    Clinical Notes
                                </h6>
                                <div className="rounded-lg bg-blue-50 p-3 text-sm whitespace-pre-wrap text-gray-700 dark:bg-blue-950/30 dark:text-gray-300">
                                    {order.result_notes}
                                </div>
                            </div>
                        )}

                        {/* Timestamps */}
                        <div className="grid grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-400">
                            {order.ordered_at && (
                                <div>
                                    <span className="font-medium">
                                        Ordered:
                                    </span>{' '}
                                    {formatDateTime(order.ordered_at)}
                                </div>
                            )}
                            {order.sample_collected_at && (
                                <div>
                                    <span className="font-medium">
                                        Sample Collected:
                                    </span>{' '}
                                    {formatDateTime(order.sample_collected_at)}
                                </div>
                            )}
                            {order.result_entered_at && (
                                <div className="col-span-2">
                                    <span className="font-medium">
                                        Results Entered:
                                    </span>{' '}
                                    {formatDateTime(order.result_entered_at)}
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
