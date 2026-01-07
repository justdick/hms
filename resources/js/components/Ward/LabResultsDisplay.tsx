import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { AlertCircle, AlertTriangle, Check, FlaskConical } from 'lucide-react';

interface LabService {
    id: number;
    name: string;
    code: string;
    price: number;
    test_parameters?: {
        parameters: Array<{
            name: string;
            label: string;
            type: string;
            unit?: string;
            normal_range?: {
                min?: number;
                max?: number;
            };
        }>;
    };
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
    order: LabOrder;
}

export function LabResultsDisplay({ order }: Props) {
    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
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

    const getStatusIcon = (flag?: string) => {
        switch (flag) {
            case 'high':
            case 'critical':
                return (
                    <AlertCircle className="h-4 w-4 text-red-500 dark:text-red-400" />
                );
            case 'low':
                return (
                    <AlertTriangle className="h-4 w-4 text-orange-500 dark:text-orange-400" />
                );
            default:
                return (
                    <Check className="h-4 w-4 text-green-500 dark:text-green-400" />
                );
        }
    };

    // If no results yet
    if (order.status !== 'completed' || !order.result_values) {
        return (
            <div className="rounded-lg border p-8 text-center dark:border-gray-700">
                <FlaskConical className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                <p className="mb-2 font-medium text-gray-600 dark:text-gray-400">
                    Results Pending
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-500">
                    {order.status === 'pending' && 'Sample collection pending'}
                    {order.status === 'in_progress' && 'Analysis in progress'}
                    {order.status === 'cancelled' && 'Test cancelled'}
                </p>
            </div>
        );
    }

    // Parse result values
    const results = Object.entries(order.result_values).map(
        ([key, result]: [string, any]) => {
            const isObject = typeof result === 'object' && result !== null;
            const value = isObject ? result.value : result;
            const unit = isObject ? result.unit : '';
            
            // Try to get range from result, or fall back to test parameters
            let range = isObject ? result.range : '';
            let flag = isObject ? result.flag : 'normal';
            
            // If no range in result, try to get from test parameters
            if (!range && order.lab_service?.test_parameters?.parameters) {
                const param = order.lab_service.test_parameters.parameters.find(
                    p => p.name === key || p.name.toLowerCase() === key.toLowerCase()
                );
                if (param?.normal_range) {
                    const { min, max } = param.normal_range;
                    if (min !== undefined && max !== undefined) {
                        range = `${min}-${max}`;
                    } else if (min !== undefined) {
                        range = `>${min}`;
                    } else if (max !== undefined) {
                        range = `<${max}`;
                    }
                    
                    // Also calculate flag if not set
                    if (flag === 'normal' && param.type === 'numeric') {
                        const numValue = parseFloat(String(value));
                        if (!isNaN(numValue)) {
                            if (min !== undefined && numValue < min) {
                                flag = 'low';
                            } else if (max !== undefined && numValue > max) {
                                flag = 'high';
                            }
                        }
                    }
                }
            }
            
            return {
                parameter: key.replace(/_/g, ' '),
                value,
                unit,
                range,
                flag,
            };
        },
    );

    return (
        <div className="space-y-4">
            {/* Test Header */}
            <div className="rounded-lg border bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div className="flex items-start justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {order.lab_service?.name || 'Lab Test'}
                        </h3>
                        {order.lab_service?.code && (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Code: {order.lab_service.code}
                            </p>
                        )}
                    </div>
                    <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        <Check className="mr-1 h-3 w-3" />
                        Completed
                    </Badge>
                </div>
            </div>

            {/* Results Table */}
            <div className="rounded-lg border dark:border-gray-700">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-gray-50 dark:bg-gray-800">
                            <TableHead className="font-semibold text-gray-900 dark:text-gray-100">
                                Parameter
                            </TableHead>
                            <TableHead className="font-semibold text-gray-900 dark:text-gray-100">
                                Value
                            </TableHead>
                            <TableHead className="font-semibold text-gray-900 dark:text-gray-100">
                                Reference Range
                            </TableHead>
                            <TableHead className="font-semibold text-gray-900 dark:text-gray-100">
                                Status
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {results.map((result, index) => (
                            <TableRow
                                key={index}
                                className="hover:bg-gray-50 dark:hover:bg-gray-800"
                            >
                                <TableCell className="font-medium text-gray-900 capitalize dark:text-gray-100">
                                    {result.parameter}
                                </TableCell>
                                <TableCell
                                    className={getValueColor(result.flag)}
                                >
                                    {result.value}
                                    {result.unit && (
                                        <span className="ml-1 font-normal text-gray-500 dark:text-gray-400">
                                            {result.unit}
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="text-gray-600 dark:text-gray-400">
                                    {result.range || '-'}
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                        {getStatusIcon(result.flag)}
                                        <span
                                            className={
                                                result.flag === 'normal'
                                                    ? 'text-green-600 dark:text-green-400'
                                                    : getValueColor(result.flag)
                                            }
                                        >
                                            {result.flag === 'normal'
                                                ? 'Normal'
                                                : result.flag.toUpperCase()}
                                        </span>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {/* Result Notes */}
            {order.result_notes && (
                <div className="rounded-lg border p-4 dark:border-gray-700">
                    <h4 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Clinical Notes
                    </h4>
                    <p className="text-sm text-gray-700 dark:text-gray-300">
                        {order.result_notes}
                    </p>
                </div>
            )}

            {/* Special Instructions */}
            {order.special_instructions && (
                <div className="flex items-start gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-800 dark:bg-yellow-950">
                    <AlertCircle className="mt-0.5 h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                    <div>
                        <p className="text-sm font-medium text-yellow-900 dark:text-yellow-200">
                            Special Instructions
                        </p>
                        <p className="text-sm text-yellow-800 dark:text-yellow-300">
                            {order.special_instructions}
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}
