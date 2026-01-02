'use client';

import { router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDown,
    Calendar,
    CheckCircle,
    Clock,
    Eye,
    FlaskConical,
    TestTube,
    X,
} from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import lab from '@/routes/lab';
import { AlertCircle, AlertTriangle, FileText, Timer } from 'lucide-react';

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    sample_type: string;
    turnaround_time: string;
    price: string;
    test_parameters?: {
        parameters: Parameter[];
    };
}

interface Parameter {
    name: string;
    label: string;
    type: 'numeric' | 'text' | 'select' | 'boolean';
    unit?: string;
    normal_range?: {
        min?: number;
        max?: number;
    };
    options?: string[];
    required: boolean;
}

export interface ConsultationTest {
    id: number;
    consultation_id: number;
    lab_service_id: number;
    lab_service: LabService;
    ordered_at: string;
    status:
        | 'ordered'
        | 'sample_collected'
        | 'in_progress'
        | 'completed'
        | 'cancelled'
        | 'external_referral';
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
    sample_collected_at?: string;
    result_entered_at?: string;
    result_values?: any;
    result_notes?: string;
}

const statusConfig = {
    ordered: {
        label: 'Ordered',
        icon: FileText,
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    },
    sample_collected: {
        label: 'Collected',
        icon: TestTube,
        className:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    },
    in_progress: {
        label: 'In Progress',
        icon: Timer,
        className:
            'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    },
    completed: {
        label: 'Completed',
        icon: CheckCircle,
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    },
    cancelled: {
        label: 'Cancelled',
        icon: AlertCircle,
        className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    },
    external_referral: {
        label: 'External Referral',
        icon: FileText,
        className:
            'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    },
};

const priorityConfig = {
    routine: {
        label: 'Routine',
        className:
            'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    },
    urgent: {
        label: 'Urgent',
        className:
            'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    },
    stat: {
        label: 'STAT',
        className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    },
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString();
};

// Action Buttons Component
const TestActionButtons = ({ test }: { test: ConsultationTest }) => {
    const [showResultsDialog, setShowResultsDialog] = useState(false);
    const [showCancelDialog, setShowCancelDialog] = useState(false);
    const [resultValues, setResultValues] = useState<Record<string, string | number | boolean>>(test.result_values || {});
    const [resultNotes, setResultNotes] = useState(test.result_notes || '');
    const [cancelReason, setCancelReason] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);

    const handleCollectSample = () => {
        setIsProcessing(true);
        router.patch(
            lab.orders.collectSample.url(test.id),
            {},
            {
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const handleStartProcessing = () => {
        setIsProcessing(true);
        router.patch(
            lab.orders.startProcessing.url(test.id),
            {},
            {
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const handleCompleteTest = () => {
        setIsProcessing(true);
        router.patch(
            lab.orders.complete.url(test.id),
            {
                result_values: resultValues,
                result_notes: resultNotes,
            },
            {
                onSuccess: () => {
                    setShowResultsDialog(false);
                },
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const handleCancelOrder = () => {
        setIsProcessing(true);
        router.patch(
            lab.orders.cancel.url(test.id),
            {
                reason: cancelReason,
            },
            {
                onSuccess: () => {
                    setShowCancelDialog(false);
                },
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const canCollectSample = test.status === 'ordered';
    const canStartProcessing = ['ordered', 'sample_collected'].includes(
        test.status,
    );
    const canComplete = test.status === 'in_progress';
    const canCancel = !['completed', 'cancelled'].includes(test.status);
    const isCompleted = test.status === 'completed';

    return (
        <>
            <div className="flex items-center gap-2">
                {canCollectSample && (
                    <Button
                        size="sm"
                        onClick={handleCollectSample}
                        disabled={isProcessing}
                    >
                        <TestTube className="mr-1 h-3 w-3" />
                        Collect Sample
                    </Button>
                )}
                {canStartProcessing && !canCollectSample && (
                    <Button
                        size="sm"
                        onClick={handleStartProcessing}
                        disabled={isProcessing}
                    >
                        <FlaskConical className="mr-1 h-3 w-3" />
                        Start Process
                    </Button>
                )}
                {canComplete && (
                    <Button
                        size="sm"
                        onClick={() => setShowResultsDialog(true)}
                    >
                        <CheckCircle className="mr-1 h-3 w-3" />
                        Enter Results
                    </Button>
                )}
                {isCompleted && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setShowResultsDialog(true)}
                    >
                        <Eye className="mr-1 h-3 w-3" />
                        View Results
                    </Button>
                )}
                {canCancel && (
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setShowCancelDialog(true)}
                        disabled={isProcessing}
                    >
                        <X className="h-3 w-3" />
                    </Button>
                )}
            </div>

            {/* Results Entry Dialog */}
            <Dialog
                open={showResultsDialog}
                onOpenChange={setShowResultsDialog}
            >
                <DialogContent className="max-h-[80vh] max-w-3xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {isCompleted
                                ? 'Test Results'
                                : 'Enter Test Results'}
                        </DialogTitle>
                        <DialogDescription>
                            {test.lab_service.name} ({test.lab_service.code})
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {test.lab_service.test_parameters?.parameters &&
                        test.lab_service.test_parameters.parameters.length >
                            0 ? (
                            <>
                                <div className="space-y-4">
                                    {test.lab_service.test_parameters.parameters.map(
                                        (param, index) => {
                                            const rawValue =
                                                resultValues[param.name];
                                            const value =
                                                rawValue !== undefined
                                                    ? String(rawValue)
                                                    : '';
                                            const isOutOfRange =
                                                param.type === 'numeric' &&
                                                param.normal_range &&
                                                value !== '' &&
                                                (parseFloat(value) <
                                                    (param.normal_range.min ||
                                                        0) ||
                                                    parseFloat(value) >
                                                        (param.normal_range
                                                            .max || Infinity));

                                            switch (param.type) {
                                                case 'numeric':
                                                    return (
                                                        <div
                                                            key={param.name}
                                                            className="space-y-2"
                                                        >
                                                            <Label 
                                                                htmlFor={`param-${param.name}`}
                                                                className="text-sm font-medium"
                                                            >
                                                                {param.label}{' '}
                                                                {param.unit &&
                                                                    `(${param.unit})`}
                                                                {param.required && (
                                                                    <span className="text-red-500">
                                                                        *
                                                                    </span>
                                                                )}
                                                            </Label>
                                                            <Input
                                                                id={`param-${param.name}`}
                                                                name={`param-${param.name}`}
                                                                type="number"
                                                                step="any"
                                                                autoComplete="off"
                                                                placeholder="Enter value"
                                                                value={value}
                                                                onChange={(e) =>
                                                                    setResultValues(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            [param.name]:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        }),
                                                                    )
                                                                }
                                                                className={
                                                                    isOutOfRange
                                                                        ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                                                        : ''
                                                                }
                                                                disabled={
                                                                    isCompleted
                                                                }
                                                            />
                                                            {param.normal_range && (
                                                                <p className="text-xs text-muted-foreground">
                                                                    Normal
                                                                    range:{' '}
                                                                    {
                                                                        param
                                                                            .normal_range
                                                                            .min
                                                                    }{' '}
                                                                    -{' '}
                                                                    {
                                                                        param
                                                                            .normal_range
                                                                            .max
                                                                    }{' '}
                                                                    {param.unit}
                                                                </p>
                                                            )}
                                                            {isOutOfRange && (
                                                                <p className="flex items-center gap-1 text-xs text-red-600">
                                                                    <AlertTriangle className="h-3 w-3" />
                                                                    Value is
                                                                    outside
                                                                    normal range
                                                                </p>
                                                            )}
                                                        </div>
                                                    );
                                                case 'text':
                                                    return (
                                                        <div
                                                            key={param.name}
                                                            className="space-y-2"
                                                        >
                                                            <Label 
                                                                htmlFor={`param-${param.name}`}
                                                                className="text-sm font-medium"
                                                            >
                                                                {param.label}
                                                                {param.required && (
                                                                    <span className="text-red-500">
                                                                        *
                                                                    </span>
                                                                )}
                                                            </Label>
                                                            <Textarea
                                                                id={`param-${param.name}`}
                                                                name={`param-${param.name}`}
                                                                autoComplete="off"
                                                                placeholder="Enter text"
                                                                value={value}
                                                                onChange={(e) =>
                                                                    setResultValues(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            [param.name]:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        }),
                                                                    )
                                                                }
                                                                rows={3}
                                                                disabled={
                                                                    isCompleted
                                                                }
                                                            />
                                                        </div>
                                                    );
                                                case 'select':
                                                    return (
                                                        <div
                                                            key={param.name}
                                                            className="space-y-2"
                                                        >
                                                            <Label className="text-sm font-medium">
                                                                {param.label}
                                                                {param.required && (
                                                                    <span className="text-red-500">
                                                                        *
                                                                    </span>
                                                                )}
                                                            </Label>
                                                            <Select
                                                                value={value}
                                                                onValueChange={(
                                                                    selectedValue,
                                                                ) =>
                                                                    setResultValues(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            [param.name]:
                                                                                selectedValue,
                                                                        }),
                                                                    )
                                                                }
                                                                disabled={
                                                                    isCompleted
                                                                }
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Select option" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {param.options?.map(
                                                                        (
                                                                            option,
                                                                            i,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    i
                                                                                }
                                                                                value={
                                                                                    option
                                                                                }
                                                                            >
                                                                                {
                                                                                    option
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    );
                                                case 'boolean':
                                                    return (
                                                        <div
                                                            key={param.name}
                                                            className="flex items-center space-x-2"
                                                        >
                                                            <Checkbox
                                                                id={`param-${param.name}`}
                                                                checked={
                                                                    rawValue ===
                                                                        'true' ||
                                                                    rawValue ===
                                                                        true
                                                                }
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    setResultValues(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            [param.name]:
                                                                                checked,
                                                                        }),
                                                                    )
                                                                }
                                                                disabled={
                                                                    isCompleted
                                                                }
                                                            />
                                                            <Label 
                                                                htmlFor={`param-${param.name}`}
                                                                className="text-sm font-medium"
                                                            >
                                                                {param.label}
                                                                {param.required && (
                                                                    <span className="text-red-500">
                                                                        *
                                                                    </span>
                                                                )}
                                                            </Label>
                                                        </div>
                                                    );
                                                default:
                                                    return null;
                                            }
                                        },
                                    )}
                                </div>
                                <Separator />
                            </>
                        ) : (
                            <div>
                                <Label htmlFor="result_values">
                                    Result Values (JSON format)
                                </Label>
                                <Textarea
                                    id="result_values"
                                    placeholder='{"parameter1": "value1", "parameter2": "value2"}'
                                    value={JSON.stringify(
                                        resultValues,
                                        null,
                                        2,
                                    )}
                                    onChange={(e) => {
                                        try {
                                            setResultValues(
                                                JSON.parse(e.target.value),
                                            );
                                        } catch {
                                            // Handle invalid JSON gracefully
                                        }
                                    }}
                                    rows={6}
                                    disabled={isCompleted}
                                />
                            </div>
                        )}

                        <div>
                            <Label htmlFor="result_notes">Result Notes</Label>
                            <Textarea
                                id="result_notes"
                                placeholder="Enter any notes about the test results..."
                                value={resultNotes}
                                onChange={(e) => setResultNotes(e.target.value)}
                                rows={4}
                                disabled={isCompleted}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowResultsDialog(false)}
                        >
                            {isCompleted ? 'Close' : 'Cancel'}
                        </Button>
                        {!isCompleted && (
                            <Button
                                onClick={handleCompleteTest}
                                disabled={isProcessing}
                            >
                                {isProcessing ? 'Saving...' : 'Save Results'}
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Cancel Dialog */}
            <Dialog open={showCancelDialog} onOpenChange={setShowCancelDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-destructive" />
                            Cancel Lab Order
                        </DialogTitle>
                        <DialogDescription>
                            Please provide a reason for cancelling this lab
                            order.
                        </DialogDescription>
                    </DialogHeader>

                    <div>
                        <Label htmlFor="cancel_reason">
                            Cancellation Reason
                        </Label>
                        <Textarea
                            id="cancel_reason"
                            placeholder="Enter the reason for cancellation..."
                            value={cancelReason}
                            onChange={(e) => setCancelReason(e.target.value)}
                            rows={3}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowCancelDialog(false)}
                        >
                            Keep Order
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleCancelOrder}
                            disabled={!cancelReason.trim() || isProcessing}
                        >
                            {isProcessing ? 'Cancelling...' : 'Cancel Order'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
};

export const consultationTestColumns: ColumnDef<ConsultationTest>[] = [
    {
        id: 'test_name',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Test Name
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const service = row.original.lab_service;
            return (
                <div className="space-y-1">
                    <div className="font-medium">{service.name}</div>
                    <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                        {service.code}
                    </code>
                </div>
            );
        },
        sortingFn: (rowA, rowB) => {
            return rowA.original.lab_service.name.localeCompare(
                rowB.original.lab_service.name,
            );
        },
    },
    {
        accessorKey: 'lab_service.category',
        header: 'Category',
        cell: ({ row }) => {
            return (
                <Badge variant="outline" className="text-xs">
                    {row.getValue('lab_service.category')}
                </Badge>
            );
        },
    },
    {
        accessorKey: 'status',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Status
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const status = row.getValue('status') as ConsultationTest['status'];
            const config = statusConfig[status] || statusConfig.ordered;
            const Icon = config.icon;

            return (
                <Badge className={config.className} variant="outline">
                    <Icon className="mr-1 h-3 w-3" />
                    {config.label}
                </Badge>
            );
        },
    },
    {
        accessorKey: 'priority',
        header: 'Priority',
        cell: ({ row }) => {
            const priority = row.getValue(
                'priority',
            ) as ConsultationTest['priority'];
            const config = priorityConfig[priority] || priorityConfig.routine;

            return (
                <Badge variant="outline" className={config.className}>
                    {config.label}
                </Badge>
            );
        },
    },
    {
        id: 'sample_type',
        header: 'Sample',
        cell: ({ row }) => {
            return (
                <div className="text-sm">
                    {row.original.lab_service.sample_type}
                </div>
            );
        },
    },
    {
        accessorKey: 'ordered_at',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Ordered
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                    <Calendar className="h-3 w-3" />
                    {formatDateTime(row.getValue('ordered_at'))}
                </div>
            );
        },
    },
    {
        id: 'turnaround',
        header: 'Turnaround',
        cell: ({ row }) => {
            return (
                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                    <Clock className="h-3 w-3" />
                    {row.original.lab_service.turnaround_time}
                </div>
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            return <TestActionButtons test={row.original} />;
        },
    },
];
