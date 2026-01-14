import { ServiceBlockAlert } from '@/components/billing/ServiceBlockAlert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import lab from '@/routes/lab';
import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Calendar,
    CheckCircle,
    Clock,
    FileText,
    FlaskConical,
    Phone,
    TestTube,
    Timer,
    User,
    X,
} from 'lucide-react';
import { useState } from 'react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    phone_number?: string;
    date_of_birth: string;
    gender: string;
}

interface PatientCheckin {
    id: number;
    patient: Patient;
}

interface Consultation {
    id: number;
    patient_checkin: PatientCheckin;
    chief_complaint: string;
    subjective_notes?: string;
    created_at: string;
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

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    description?: string;
    preparation_instructions?: string;
    price: string;
    sample_type: string;
    turnaround_time: string;
    normal_range?: string;
    clinical_significance?: string;
    test_parameters?: {
        parameters: Parameter[];
    };
}

interface User {
    id: number;
    name: string;
}

interface LabOrder {
    id: number;
    consultation_id: number;
    consultation: Consultation;
    lab_service_id: number;
    lab_service: LabService;
    ordered_by: User;
    ordered_at: string;
    status:
        | 'ordered'
        | 'sample_collected'
        | 'in_progress'
        | 'completed'
        | 'cancelled';
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
    sample_collected_at?: string;
    result_entered_at?: string;
    result_values?: any;
    result_notes?: string;
}

interface Charge {
    id: number;
    description: string;
    amount: number;
    service_type: string;
}

interface ServiceAccessOverride {
    id: number;
    service_type: string;
    reason: string;
    authorized_by: {
        id: number;
        name: string;
    };
    expires_at: string;
    remaining_duration: string;
}

interface Props {
    labOrder: LabOrder;
    serviceBlocked?: boolean;
    blockReason?: string;
    pendingCharges?: Charge[];
    activeOverride?: ServiceAccessOverride | null;
}

const statusConfig = {
    ordered: {
        label: 'Ordered',
        icon: FileText,
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    },
    sample_collected: {
        label: 'Sample Collected',
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

export default function LabShow({
    labOrder,
    serviceBlocked = false,
    blockReason,
    pendingCharges = [],
    activeOverride,
}: Props) {
    const [showResultsDialog, setShowResultsDialog] = useState(false);
    const [showCancelDialog, setShowCancelDialog] = useState(false);
    const [resultValues, setResultValues] = useState<Record<string, any>>(
        labOrder.result_values || {},
    );
    const [resultNotes, setResultNotes] = useState(labOrder.result_notes || '');
    const [cancelReason, setCancelReason] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString();
    };

    const calculateAge = (dateOfBirth: string) => {
        const today = new Date();
        const birth = new Date(dateOfBirth);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();

        if (
            monthDiff < 0 ||
            (monthDiff === 0 && today.getDate() < birth.getDate())
        ) {
            age--;
        }

        return age;
    };

    const getStatusBadge = (status: LabOrder['status']) => {
        const config = statusConfig[status];
        const Icon = config.icon;

        return (
            <Badge className={config.className}>
                <Icon className="mr-1 h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    const getPriorityBadge = (priority: LabOrder['priority']) => {
        const config = priorityConfig[priority];

        if (!config) {
            return null;
        }

        return (
            <Badge variant="outline" className={config.className}>
                {config.label}
            </Badge>
        );
    };

    const handleCollectSample = () => {
        setIsProcessing(true);
        router.patch(
            lab.orders.collectSample.url(labOrder.id),
            {},
            {
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const handleStartProcessing = () => {
        setIsProcessing(true);
        router.patch(
            lab.orders.startProcessing.url(labOrder.id),
            {},
            {
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const handleCompleteTest = () => {
        setIsProcessing(true);

        // Transform result values to include unit, range, and flag from test parameters
        const parameters = labOrder.lab_service.test_parameters?.parameters;
        let transformedResults: Record<string, any> = {};

        if (parameters && parameters.length > 0) {
            parameters.forEach((param) => {
                const rawValue = resultValues[param.name];
                if (rawValue !== undefined && rawValue !== '') {
                    const numericValue =
                        param.type === 'numeric'
                            ? parseFloat(rawValue)
                            : rawValue;

                    // Determine flag based on normal range
                    let flag = 'normal';
                    if (param.type === 'numeric' && param.normal_range) {
                        const value = parseFloat(rawValue);
                        if (
                            param.normal_range.min !== undefined &&
                            value < param.normal_range.min
                        ) {
                            flag = 'low';
                        } else if (
                            param.normal_range.max !== undefined &&
                            value > param.normal_range.max
                        ) {
                            flag = 'high';
                        }
                    }

                    // Build range string
                    let rangeStr = '';
                    if (param.normal_range) {
                        if (
                            param.normal_range.min !== undefined &&
                            param.normal_range.max !== undefined
                        ) {
                            rangeStr = `${param.normal_range.min}-${param.normal_range.max}`;
                        } else if (param.normal_range.min !== undefined) {
                            rangeStr = `>${param.normal_range.min}`;
                        } else if (param.normal_range.max !== undefined) {
                            rangeStr = `<${param.normal_range.max}`;
                        }
                    }

                    transformedResults[param.name] = {
                        value: numericValue,
                        unit: param.unit || '',
                        range: rangeStr,
                        flag: flag,
                    };
                }
            });
        } else {
            // No parameters configured, use raw values
            transformedResults = resultValues;
        }

        router.patch(
            lab.orders.complete.url(labOrder.id),
            {
                result_values: transformedResults,
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
            lab.orders.cancel.url(labOrder.id),
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

    const canCollectSample = labOrder.status === 'ordered';
    const canStartProcessing = ['ordered', 'sample_collected'].includes(
        labOrder.status,
    );
    const canComplete = labOrder.status === 'in_progress';
    const canCancel = !['completed', 'cancelled'].includes(labOrder.status);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Laboratory', href: lab.index.url() },
                {
                    title: `Order #${labOrder.id}`,
                    href: lab.orders.show.url({ labOrder: labOrder.id }),
                },
            ]}
        >
            <Head title={`Lab Order #${labOrder.id}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.visit(lab.index.url())}
                        >
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to Lab Dashboard
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">
                                Lab Order #{labOrder.id}
                            </h1>
                            <p className="text-muted-foreground">
                                Ordered {formatDateTime(labOrder.ordered_at)}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {getPriorityBadge(labOrder.priority)}
                        {getStatusBadge(labOrder.status)}
                    </div>
                </div>

                {/* Service Block Alert */}
                <ServiceBlockAlert
                    isBlocked={serviceBlocked}
                    blockReason={blockReason}
                    pendingCharges={pendingCharges}
                    activeOverride={activeOverride}
                    checkinId={labOrder.consultation.patient_checkin.id}
                />

                {/* Action Buttons */}
                <div className="flex items-center gap-2">
                    {canCollectSample && (
                        <Button
                            onClick={handleCollectSample}
                            disabled={isProcessing}
                        >
                            <TestTube className="mr-2 h-4 w-4" />
                            Collect Sample
                        </Button>
                    )}
                    {canStartProcessing && (
                        <Button
                            onClick={handleStartProcessing}
                            disabled={isProcessing}
                        >
                            <FlaskConical className="mr-2 h-4 w-4" />
                            Start Processing
                        </Button>
                    )}
                    {canComplete && (
                        <Button onClick={() => setShowResultsDialog(true)}>
                            <CheckCircle className="mr-2 h-4 w-4" />
                            Enter Results
                        </Button>
                    )}
                    {canCancel && (
                        <Button
                            variant="destructive"
                            onClick={() => setShowCancelDialog(true)}
                            disabled={isProcessing}
                        >
                            <X className="mr-2 h-4 w-4" />
                            Cancel Order
                        </Button>
                    )}
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Patient Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                Patient Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div>
                                <Label className="text-sm font-medium">
                                    Name
                                </Label>
                                <p className="text-sm">
                                    {
                                        labOrder.consultation.patient_checkin
                                            .patient.first_name
                                    }{' '}
                                    {
                                        labOrder.consultation.patient_checkin
                                            .patient.last_name
                                    }
                                </p>
                            </div>

                            <div>
                                <Label className="text-sm font-medium">
                                    Age & Gender
                                </Label>
                                <p className="text-sm">
                                    {calculateAge(
                                        labOrder.consultation.patient_checkin
                                            .patient.date_of_birth,
                                    )}{' '}
                                    years old,{' '}
                                    {
                                        labOrder.consultation.patient_checkin
                                            .patient.gender
                                    }
                                </p>
                            </div>

                            <div>
                                <Label className="text-sm font-medium">
                                    Date of Birth
                                </Label>
                                <p className="text-sm">
                                    {formatDate(
                                        labOrder.consultation.patient_checkin
                                            .patient.date_of_birth,
                                    )}
                                </p>
                            </div>

                            {labOrder.consultation.patient_checkin.patient
                                .phone_number && (
                                <div className="flex items-center gap-1">
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm">
                                        {
                                            labOrder.consultation
                                                .patient_checkin.patient
                                                .phone_number
                                        }
                                    </span>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Test Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Activity className="h-5 w-5" />
                                Test Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div>
                                <Label className="text-sm font-medium">
                                    Test Name
                                </Label>
                                <p className="text-sm font-medium">
                                    {labOrder.lab_service.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Code: {labOrder.lab_service.code}
                                </p>
                            </div>

                            <div>
                                <Label className="text-sm font-medium">
                                    Category
                                </Label>
                                <p className="text-sm">
                                    {labOrder.lab_service.category}
                                </p>
                            </div>

                            <div>
                                <Label className="text-sm font-medium">
                                    Sample Type
                                </Label>
                                <p className="text-sm">
                                    {labOrder.lab_service.sample_type}
                                </p>
                            </div>

                            <div className="flex items-center gap-1">
                                <Clock className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm">
                                    Turnaround:{' '}
                                    {labOrder.lab_service.turnaround_time}
                                </span>
                            </div>

                            <div>
                                <Label className="text-sm font-medium">
                                    Price
                                </Label>
                                <p className="text-sm">
                                    ${labOrder.lab_service.price}
                                </p>
                            </div>

                            {labOrder.lab_service.normal_range && (
                                <div>
                                    <Label className="text-sm font-medium">
                                        Normal Range
                                    </Label>
                                    <p className="text-sm">
                                        {labOrder.lab_service.normal_range}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Consultation Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Consultation Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div>
                                <Label className="text-sm font-medium">
                                    Chief Complaint
                                </Label>
                                <p className="text-sm">
                                    {labOrder.consultation.chief_complaint}
                                </p>
                            </div>

                            {labOrder.consultation.subjective_notes && (
                                <div>
                                    <Label className="text-sm font-medium">
                                        Subjective Notes
                                    </Label>
                                    <p className="text-sm">
                                        {labOrder.consultation.subjective_notes}
                                    </p>
                                </div>
                            )}

                            <div>
                                <Label className="text-sm font-medium">
                                    Ordered By
                                </Label>
                                <p className="text-sm">
                                    {labOrder.ordered_by?.name}
                                </p>
                            </div>

                            <div className="flex items-center gap-1">
                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm">
                                    Consultation:{' '}
                                    {formatDateTime(
                                        labOrder.consultation.created_at,
                                    )}
                                </span>
                            </div>

                            {labOrder.special_instructions && (
                                <div>
                                    <Label className="text-sm font-medium">
                                        Special Instructions
                                    </Label>
                                    <p className="rounded border bg-yellow-50 p-2 text-sm dark:bg-yellow-900/20">
                                        {labOrder.special_instructions}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Processing Timeline */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Timer className="h-5 w-5" />
                                Processing Timeline
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center gap-2">
                                <CheckCircle className="h-4 w-4 text-green-600" />
                                <span className="text-sm">
                                    Ordered:{' '}
                                    {formatDateTime(labOrder.ordered_at)}
                                </span>
                            </div>

                            <div className="flex items-center gap-2">
                                {labOrder.sample_collected_at ? (
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                ) : (
                                    <div className="h-4 w-4 rounded-full border-2 border-gray-300" />
                                )}
                                <span className="text-sm">
                                    Sample Collection:{' '}
                                    {labOrder.sample_collected_at
                                        ? formatDateTime(
                                              labOrder.sample_collected_at,
                                          )
                                        : 'Pending'}
                                </span>
                            </div>

                            <div className="flex items-center gap-2">
                                {labOrder.status === 'in_progress' ||
                                labOrder.status === 'completed' ? (
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                ) : (
                                    <div className="h-4 w-4 rounded-full border-2 border-gray-300" />
                                )}
                                <span className="text-sm">
                                    Processing Started:{' '}
                                    {labOrder.status === 'in_progress' ||
                                    labOrder.status === 'completed'
                                        ? 'In Progress'
                                        : 'Pending'}
                                </span>
                            </div>

                            <div className="flex items-center gap-2">
                                {labOrder.result_entered_at ? (
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                ) : (
                                    <div className="h-4 w-4 rounded-full border-2 border-gray-300" />
                                )}
                                <span className="text-sm">
                                    Results Entered:{' '}
                                    {labOrder.result_entered_at
                                        ? formatDateTime(
                                              labOrder.result_entered_at,
                                          )
                                        : 'Pending'}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Test Results (if completed) */}
                {labOrder.status === 'completed' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Test Results</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {labOrder.result_values &&
                                typeof labOrder.result_values === 'object' && (
                                    <div>
                                        <Label className="text-sm font-medium">
                                            Result Values
                                        </Label>
                                        <div className="mt-1 rounded-md bg-muted p-3">
                                            <pre className="text-sm whitespace-pre-wrap">
                                                {JSON.stringify(
                                                    labOrder.result_values,
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        </div>
                                    </div>
                                )}

                            {labOrder.result_notes && (
                                <div>
                                    <Label className="text-sm font-medium">
                                        Result Notes
                                    </Label>
                                    <p className="mt-1 rounded-md bg-muted p-3 text-sm">
                                        {labOrder.result_notes}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Additional Information */}
                {(labOrder.lab_service.description ||
                    labOrder.lab_service.preparation_instructions ||
                    labOrder.lab_service.clinical_significance) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Additional Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {labOrder.lab_service.description && (
                                <div>
                                    <Label className="text-sm font-medium">
                                        Test Description
                                    </Label>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {labOrder.lab_service.description}
                                    </p>
                                </div>
                            )}

                            {labOrder.lab_service.preparation_instructions && (
                                <div>
                                    <Label className="text-sm font-medium">
                                        Preparation Instructions
                                    </Label>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {
                                            labOrder.lab_service
                                                .preparation_instructions
                                        }
                                    </p>
                                </div>
                            )}

                            {labOrder.lab_service.clinical_significance && (
                                <div>
                                    <Label className="text-sm font-medium">
                                        Clinical Significance
                                    </Label>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {
                                            labOrder.lab_service
                                                .clinical_significance
                                        }
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Results Entry Dialog */}
            <Dialog
                open={showResultsDialog}
                onOpenChange={setShowResultsDialog}
            >
                <DialogContent className="max-h-[80vh] max-w-3xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Enter Test Results</DialogTitle>
                        <DialogDescription>
                            Enter the results for {labOrder.lab_service.name} (
                            {labOrder.lab_service.code})
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {labOrder.lab_service.test_parameters?.parameters &&
                        labOrder.lab_service.test_parameters.parameters.length >
                            0 ? (
                            <>
                                {/* Dynamic Form Fields */}
                                <div className="space-y-4">
                                    {labOrder.lab_service.test_parameters.parameters.map(
                                        (param, index) => {
                                            const value =
                                                resultValues[param.name] || '';
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
                                                                htmlFor={`param-${index}-${param.name}`}
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
                                                                id={`param-${index}-${param.name}`}
                                                                name={`param-${index}-${param.name}`}
                                                                type="number"
                                                                inputMode="decimal"
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
                                                                onWheel={(e) =>
                                                                    (
                                                                        e.target as HTMLInputElement
                                                                    ).blur()
                                                                }
                                                                className={cn(
                                                                    '[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                                                                    isOutOfRange
                                                                        ? 'border-red-500 bg-red-50'
                                                                        : '',
                                                                )}
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
                                                                htmlFor={`param-${index}-${param.name}`}
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
                                                                id={`param-${index}-${param.name}`}
                                                                name={`param-${index}-${param.name}`}
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
                                                                id={`param-${index}-${param.name}`}
                                                                checked={
                                                                    value ===
                                                                        'true' ||
                                                                    value ===
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
                                                            />
                                                            <Label
                                                                htmlFor={`param-${index}-${param.name}`}
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
                            /* Fallback to JSON entry if no parameters configured */
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
                                />
                                <p className="mt-1 text-xs text-muted-foreground">
                                    No test parameters configured. Consider
                                    configuring parameters for this test to
                                    enable structured data entry.
                                </p>
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
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowResultsDialog(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleCompleteTest}
                            disabled={isProcessing}
                        >
                            {isProcessing ? 'Saving...' : 'Save Results'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Cancel Order Dialog */}
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
        </AppLayout>
    );
}
