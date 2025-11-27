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
                    parameters: parameters.filter(
                        (p) => p.name && p.label,
                    ) as any,
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

            <div className="space-y-4">
                {/* Compact Header */}
                <div className="flex items-center justify-between">
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
                </div>

                {/* Test Info Banner */}
                <Card className="bg-muted/50">
                    <CardContent className="flex items-center justify-between py-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <FlaskConical className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <h1 className="text-lg font-semibold">
                                    {labService.name}
                                </h1>
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <span>{labService.code}</span>
                                    <span>•</span>
                                    <Badge
                                        variant="outline"
                                        className="text-xs"
                                    >
                                        {labService.category}
                                    </Badge>
                                    {labService.description && (
                                        <>
                                            <span>•</span>
                                            <span>
                                                {labService.description}
                                            </span>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 md:grid-cols-[2fr_1fr]">
                    {/* Configuration Form */}
                    <div className="flex flex-col">
                        <h2 className="mb-4 text-lg font-semibold">
                            Test Parameters
                        </h2>

                        <div className="flex-1 space-y-3">
                            {parameters.length === 0 ? (
                                <Card>
                                    <CardContent className="py-12 text-center text-muted-foreground">
                                        <AlertCircle className="mx-auto mb-2 h-8 w-8" />
                                        <p className="font-medium">
                                            No parameters configured yet.
                                        </p>
                                        <p className="text-xs">
                                            Click "Add Parameter" to get
                                            started.
                                        </p>
                                    </CardContent>
                                </Card>
                            ) : (
                                parameters.map((param, index) => (
                                    <Card key={index}>
                                        <CardContent className="p-4">
                                            <div className="space-y-4">
                                                <div className="flex items-center justify-between">
                                                    <h4 className="text-sm font-medium text-muted-foreground">
                                                        Parameter #{index + 1}
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
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>

                                                <div className="grid gap-3 md:grid-cols-2">
                                                    <div className="space-y-1.5">
                                                        <Label className="text-xs">
                                                            Parameter Name
                                                        </Label>
                                                        <Input
                                                            placeholder="e.g., hemoglobin"
                                                            value={param.name}
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
                                                    <div className="space-y-1.5">
                                                        <Label className="text-xs">
                                                            Display Label
                                                        </Label>
                                                        <Input
                                                            placeholder="e.g., Hemoglobin"
                                                            value={param.label}
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

                                                <div className="grid gap-3 md:grid-cols-2">
                                                    <div className="space-y-1.5">
                                                        <Label className="text-xs">
                                                            Field Type
                                                        </Label>
                                                        <Select
                                                            value={param.type}
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
                                                        <div className="space-y-1.5">
                                                            <Label className="text-xs">
                                                                Unit
                                                            </Label>
                                                            <Input
                                                                placeholder="e.g., g/dL, mg/dL, %"
                                                                value={
                                                                    param.unit ||
                                                                    ''
                                                                }
                                                                onChange={(e) =>
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

                                                {param.type === 'numeric' && (
                                                    <div className="grid gap-3 md:grid-cols-2">
                                                        <div className="space-y-1.5">
                                                            <Label className="text-xs">
                                                                Normal Range Min
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
                                                                onChange={(e) =>
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
                                                        <div className="space-y-1.5">
                                                            <Label className="text-xs">
                                                                Normal Range Max
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
                                                                onChange={(e) =>
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

                                                {param.type === 'select' && (
                                                    <div className="space-y-2">
                                                        <div className="flex items-center justify-between">
                                                            <Label className="text-xs">
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
                                                                Add
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

                                                <div className="flex items-center space-x-2 pt-2">
                                                    <Checkbox
                                                        checked={param.required}
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
                                                    <Label className="text-xs font-normal">
                                                        Required field
                                                    </Label>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))
                            )}
                        </div>

                        {/* Sticky Add Parameter Button */}
                        <div className="sticky bottom-0 mt-4 flex justify-center border-t bg-background/95 py-4 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                            <Button onClick={addParameter}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Parameter
                            </Button>
                        </div>
                    </div>

                    {/* Preview - Always Visible & Sticky */}
                    <div className="md:sticky md:top-6 md:h-fit md:self-start">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Eye className="h-4 w-4" />
                                    Form Preview
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {parameters.filter((p) => p.name && p.label)
                                    .length === 0 ? (
                                    <div className="py-12 text-center text-muted-foreground">
                                        <AlertCircle className="mx-auto mb-2 h-8 w-8 opacity-50" />
                                        <p className="text-sm font-medium">
                                            No parameters yet
                                        </p>
                                        <p className="text-xs">
                                            Add parameters to see preview
                                        </p>
                                    </div>
                                ) : (
                                    <>
                                        <div>
                                            <h4 className="text-sm font-medium">
                                                Enter Results for{' '}
                                                {labService.name}
                                            </h4>
                                            <p className="text-xs text-muted-foreground">
                                                This is how staff will enter
                                                results
                                            </p>
                                        </div>
                                        <Separator />
                                        <div className="space-y-4">
                                            {parameters
                                                .filter(
                                                    (p) => p.name && p.label,
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
                </div>

                {/* Save Button at Bottom */}
                <div className="flex justify-end border-t pt-4">
                    <Button
                        onClick={handleSubmit}
                        disabled={isProcessing}
                        size="lg"
                    >
                        {isProcessing ? 'Saving...' : 'Save Configuration'}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
