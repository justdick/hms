import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Eye,
    FlaskConical,
    Plus,
    Settings,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    description?: string;
    normal_range?: string;
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

interface Props {
    labService: LabService;
}

export default function LabServiceConfigure({ labService }: Props) {
    const [parameters, setParameters] = useState<Parameter[]>(
        labService.test_parameters?.parameters || [],
    );
    const [showPreview, setShowPreview] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);

    const addParameter = () => {
        const newParameter: Parameter = {
            name: '',
            label: '',
            type: 'numeric',
            required: false,
        };
        setParameters([...parameters, newParameter]);
    };

    const removeParameter = (index: number) => {
        setParameters(parameters.filter((_, i) => i !== index));
    };

    const updateParameter = (index: number, updates: Partial<Parameter>) => {
        setParameters(
            parameters.map((param, i) =>
                i === index ? { ...param, ...updates } : param,
            ),
        );
    };

    const addOption = (paramIndex: number) => {
        const param = parameters[paramIndex];
        const options = param.options || [];
        updateParameter(paramIndex, { options: [...options, ''] });
    };

    const updateOption = (
        paramIndex: number,
        optionIndex: number,
        value: string,
    ) => {
        const param = parameters[paramIndex];
        const options = [...(param.options || [])];
        options[optionIndex] = value;
        updateParameter(paramIndex, { options });
    };

    const removeOption = (paramIndex: number, optionIndex: number) => {
        const param = parameters[paramIndex];
        const options =
            param.options?.filter((_, i) => i !== optionIndex) || [];
        updateParameter(paramIndex, { options });
    };

    const handleSubmit = () => {
        setIsProcessing(true);
        router.put(
            `/lab/services/configuration/${labService.id}`,
            {
                test_parameters: {
                    parameters: parameters.filter((p) => p.name && p.label),
                },
            },
            {
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const renderPreviewField = (param: Parameter, index: number) => {
        switch (param.type) {
            case 'numeric':
                return (
                    <div key={index} className="space-y-1">
                        <Label className="text-sm font-medium">
                            {param.label} {param.unit && `(${param.unit})`}
                            {param.required && (
                                <span className="text-red-500">*</span>
                            )}
                        </Label>
                        <Input
                            type="number"
                            placeholder="Enter value"
                            disabled
                        />
                        {param.normal_range && (
                            <p className="text-xs text-muted-foreground">
                                Normal range: {param.normal_range.min} -{' '}
                                {param.normal_range.max} {param.unit}
                            </p>
                        )}
                    </div>
                );
            case 'text':
                return (
                    <div key={index} className="space-y-1">
                        <Label className="text-sm font-medium">
                            {param.label}
                            {param.required && (
                                <span className="text-red-500">*</span>
                            )}
                        </Label>
                        <Textarea placeholder="Enter text" disabled rows={3} />
                    </div>
                );
            case 'select':
                return (
                    <div key={index} className="space-y-1">
                        <Label className="text-sm font-medium">
                            {param.label}
                            {param.required && (
                                <span className="text-red-500">*</span>
                            )}
                        </Label>
                        <Select disabled>
                            <SelectTrigger>
                                <SelectValue placeholder="Select option" />
                            </SelectTrigger>
                            <SelectContent>
                                {param.options?.map((option, i) => (
                                    <SelectItem key={i} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                );
            case 'boolean':
                return (
                    <div key={index} className="flex items-center space-x-2">
                        <Checkbox disabled />
                        <Label className="text-sm font-medium">
                            {param.label}
                            {param.required && (
                                <span className="text-red-500">*</span>
                            )}
                        </Label>
                    </div>
                );
            default:
                return null;
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Laboratory', href: '/lab' },
                { title: 'Configuration', href: '/lab/services/configuration' },
                {
                    title: labService.name,
                    href: `/lab/services/configuration/${labService.id}`,
                },
            ]}
        >
            <Head title={`Configure ${labService.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.visit('/lab/services/configuration')
                            }
                        >
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to Configuration
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Settings className="h-6 w-6" />
                                Configure Test Parameters
                            </h1>
                            <p className="text-muted-foreground">
                                {labService.name} ({labService.code})
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setShowPreview(!showPreview)}
                        >
                            <Eye className="mr-2 h-4 w-4" />
                            {showPreview ? 'Hide Preview' : 'Show Preview'}
                        </Button>
                        <Button onClick={handleSubmit} disabled={isProcessing}>
                            {isProcessing ? 'Saving...' : 'Save Configuration'}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Configuration Form */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FlaskConical className="h-5 w-5" />
                                    Test Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div>
                                    <Label className="text-sm font-medium">
                                        Test Name
                                    </Label>
                                    <p className="text-sm">{labService.name}</p>
                                </div>
                                <div>
                                    <Label className="text-sm font-medium">
                                        Code
                                    </Label>
                                    <p className="text-sm">{labService.code}</p>
                                </div>
                                <div>
                                    <Label className="text-sm font-medium">
                                        Category
                                    </Label>
                                    <Badge variant="outline">
                                        {labService.category}
                                    </Badge>
                                </div>
                                {labService.description && (
                                    <div>
                                        <Label className="text-sm font-medium">
                                            Description
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            {labService.description}
                                        </p>
                                    </div>
                                )}
                                {labService.normal_range && (
                                    <div>
                                        <Label className="text-sm font-medium">
                                            Current Normal Range
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            {labService.normal_range}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Test Parameters</CardTitle>
                                <Button size="sm" onClick={addParameter}>
                                    <Plus className="mr-1 h-4 w-4" />
                                    Add Parameter
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {parameters.length === 0 ? (
                                    <div className="py-8 text-center text-muted-foreground">
                                        <AlertCircle className="mx-auto mb-2 h-8 w-8" />
                                        <p>No parameters configured yet.</p>
                                        <p className="text-xs">
                                            Click "Add Parameter" to get
                                            started.
                                        </p>
                                    </div>
                                ) : (
                                    parameters.map((param, index) => (
                                        <Card
                                            key={index}
                                            className="border-dashed"
                                        >
                                            <CardContent className="pt-4">
                                                <div className="space-y-4">
                                                    <div className="flex items-center justify-between">
                                                        <h4 className="font-medium">
                                                            Parameter #
                                                            {index + 1}
                                                        </h4>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                removeParameter(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>

                                                    <div className="grid gap-4 md:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label>
                                                                Parameter Name
                                                            </Label>
                                                            <Input
                                                                placeholder="e.g., hemoglobin"
                                                                value={
                                                                    param.name
                                                                }
                                                                onChange={(e) =>
                                                                    updateParameter(
                                                                        index,
                                                                        {
                                                                            name: e
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label>
                                                                Display Label
                                                            </Label>
                                                            <Input
                                                                placeholder="e.g., Hemoglobin"
                                                                value={
                                                                    param.label
                                                                }
                                                                onChange={(e) =>
                                                                    updateParameter(
                                                                        index,
                                                                        {
                                                                            label: e
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="grid gap-4 md:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label>
                                                                Field Type
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    param.type
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    updateParameter(
                                                                        index,
                                                                        {
                                                                            type: value as Parameter['type'],
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="numeric">
                                                                        Numeric
                                                                    </SelectItem>
                                                                    <SelectItem value="text">
                                                                        Text
                                                                    </SelectItem>
                                                                    <SelectItem value="select">
                                                                        Dropdown
                                                                    </SelectItem>
                                                                    <SelectItem value="boolean">
                                                                        Checkbox
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        {param.type ===
                                                            'numeric' && (
                                                            <div className="space-y-2">
                                                                <Label>
                                                                    Unit
                                                                </Label>
                                                                <Input
                                                                    placeholder="e.g., g/dL, mg/dL, %"
                                                                    value={
                                                                        param.unit ||
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateParameter(
                                                                            index,
                                                                            {
                                                                                unit: e
                                                                                    .target
                                                                                    .value,
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        )}
                                                    </div>

                                                    {param.type ===
                                                        'numeric' && (
                                                        <div className="grid gap-4 md:grid-cols-2">
                                                            <div className="space-y-2">
                                                                <Label>
                                                                    Normal Range
                                                                    Min
                                                                </Label>
                                                                <Input
                                                                    type="number"
                                                                    placeholder="0"
                                                                    value={
                                                                        param
                                                                            .normal_range
                                                                            ?.min ||
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateParameter(
                                                                            index,
                                                                            {
                                                                                normal_range:
                                                                                    {
                                                                                        ...param.normal_range,
                                                                                        min:
                                                                                            parseFloat(
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            ) ||
                                                                                            undefined,
                                                                                    },
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label>
                                                                    Normal Range
                                                                    Max
                                                                </Label>
                                                                <Input
                                                                    type="number"
                                                                    placeholder="100"
                                                                    value={
                                                                        param
                                                                            .normal_range
                                                                            ?.max ||
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateParameter(
                                                                            index,
                                                                            {
                                                                                normal_range:
                                                                                    {
                                                                                        ...param.normal_range,
                                                                                        max:
                                                                                            parseFloat(
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            ) ||
                                                                                            undefined,
                                                                                    },
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    )}

                                                    {param.type ===
                                                        'select' && (
                                                        <div className="space-y-2">
                                                            <div className="flex items-center justify-between">
                                                                <Label>
                                                                    Options
                                                                </Label>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        addOption(
                                                                            index,
                                                                        )
                                                                    }
                                                                >
                                                                    <Plus className="mr-1 h-3 w-3" />
                                                                    Add Option
                                                                </Button>
                                                            </div>
                                                            <div className="space-y-2">
                                                                {(
                                                                    param.options ||
                                                                    []
                                                                ).map(
                                                                    (
                                                                        option,
                                                                        optionIndex,
                                                                    ) => (
                                                                        <div
                                                                            key={
                                                                                optionIndex
                                                                            }
                                                                            className="flex gap-2"
                                                                        >
                                                                            <Input
                                                                                placeholder={`Option ${optionIndex + 1}`}
                                                                                value={
                                                                                    option
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    updateOption(
                                                                                        index,
                                                                                        optionIndex,
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                            />
                                                                            <Button
                                                                                size="sm"
                                                                                variant="ghost"
                                                                                onClick={() =>
                                                                                    removeOption(
                                                                                        index,
                                                                                        optionIndex,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Trash2 className="h-4 w-4" />
                                                                            </Button>
                                                                        </div>
                                                                    ),
                                                                )}
                                                            </div>
                                                        </div>
                                                    )}

                                                    <div className="flex items-center space-x-2">
                                                        <Checkbox
                                                            checked={
                                                                param.required
                                                            }
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                updateParameter(
                                                                    index,
                                                                    {
                                                                        required:
                                                                            !!checked,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <Label>
                                                            Required field
                                                        </Label>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Preview */}
                    {showPreview && (
                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Eye className="h-5 w-5" />
                                        Form Preview
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {parameters.filter((p) => p.name && p.label)
                                        .length === 0 ? (
                                        <div className="py-8 text-center text-muted-foreground">
                                            <AlertCircle className="mx-auto mb-2 h-8 w-8" />
                                            <p>
                                                No valid parameters to preview
                                            </p>
                                            <p className="text-xs">
                                                Add parameters with name and
                                                label to see preview
                                            </p>
                                        </div>
                                    ) : (
                                        <>
                                            <h4 className="font-medium">
                                                Enter Results for{' '}
                                                {labService.name}
                                            </h4>
                                            <Separator />
                                            <div className="space-y-4">
                                                {parameters
                                                    .filter(
                                                        (p) =>
                                                            p.name && p.label,
                                                    )
                                                    .map((param, index) =>
                                                        renderPreviewField(
                                                            param,
                                                            index,
                                                        ),
                                                    )}
                                            </div>
                                            <Separator />
                                            <div className="space-y-2">
                                                <Label className="text-sm font-medium">
                                                    Result Notes
                                                </Label>
                                                <Textarea
                                                    placeholder="Additional notes..."
                                                    disabled
                                                    rows={3}
                                                />
                                            </div>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
