import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { Eye, Plus, Stethoscope, Trash2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import AsyncProcedureSearch, { Procedure } from './AsyncProcedureSearch';
import TemplateRenderer, { TemplateVariable } from './TemplateRenderer';

interface ProcedureType {
    id: number;
    name: string;
    code: string;
    type: 'minor' | 'major';
    category: string;
    price: number;
}

interface ConsultationProcedure {
    id: number;
    procedure_type: ProcedureType;
    indication: string | null;
    assistant: string | null;
    anaesthetist: string | null;
    anaesthesia_type: string | null;
    estimated_gestational_age: string | null;
    parity: string | null;
    procedure_subtype: string | null;
    procedure_steps: string | null;
    template_selections: Record<string, string> | null;
    findings: string | null;
    plan: string | null;
    comments: string | null;
    performed_at: string;
    doctor: {
        id: number;
        name: string;
    };
}

interface ProcedureTemplate {
    id: number;
    name: string;
    template_text: string;
    variables: TemplateVariable[];
    extra_fields: string[];
}

interface Props {
    consultationId: number;
    procedures: ConsultationProcedure[];
    availableProcedures: ProcedureType[];
}

const ANAESTHESIA_TYPES = [
    { value: 'spinal', label: 'Spinal Anaesthesia' },
    { value: 'local', label: 'Local Anaesthesia' },
    { value: 'general', label: 'General Anaesthesia' },
    { value: 'regional', label: 'Regional Anaesthesia' },
    { value: 'sedation', label: 'Sedation' },
];

const CSECTION_SUBTYPES = [
    'Elective C/S',
    'Elective C/S + Sterilization',
    'Elective C/S + Hysterectomy',
    'Emergency C/S',
    'Emergency C/S + Sterilization',
    'Emergency C/S + Hysterectomy',
];

export default function TheatreProceduresTab({
    consultationId,
    procedures,
}: Props) {
    // Form state
    const [selectedProcedure, setSelectedProcedure] =
        useState<Procedure | null>(null);
    const [indication, setIndication] = useState('');
    const [assistant, setAssistant] = useState('');
    const [anaesthetist, setAnaesthetist] = useState('');
    const [anaesthesiaType, setAnaesthesiaType] = useState<string | null>(null);
    const [procedureSteps, setProcedureSteps] = useState('');
    const [findings, setFindings] = useState('');
    const [plan, setPlan] = useState('');
    const [comments, setComments] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [viewProcedure, setViewProcedure] =
        useState<ConsultationProcedure | null>(null);

    // Obstetric fields (for C-Section)
    const [estimatedGestationalAge, setEstimatedGestationalAge] = useState('');
    const [parity, setParity] = useState('');
    const [procedureSubtype, setProcedureSubtype] = useState<string | null>(
        null,
    );

    // Template state
    const [template, setTemplate] = useState<ProcedureTemplate | null>(null);
    const [templateSelections, setTemplateSelections] = useState<
        Record<string, string>
    >({});
    const [isLoadingTemplate, setIsLoadingTemplate] = useState(false);
    const [showObstetricFields, setShowObstetricFields] = useState(false);

    const resetForm = () => {
        setSelectedProcedure(null);
        setIndication('');
        setAssistant('');
        setAnaesthetist('');
        setAnaesthesiaType(null);
        setProcedureSteps('');
        setFindings('');
        setPlan('');
        setComments('');
        setEstimatedGestationalAge('');
        setParity('');
        setProcedureSubtype(null);
        setTemplate(null);
        setTemplateSelections({});
        setShowObstetricFields(false);
    };

    // Fetch template when procedure is selected
    const fetchTemplate = useCallback(async (procedureId: number) => {
        setIsLoadingTemplate(true);
        try {
            const response = await fetch(`/procedures/${procedureId}/template`);
            const data = await response.json();

            if (data.template) {
                setTemplate(data.template);
                setTemplateSelections({});
                setProcedureSteps(''); // Will be set by template
                setShowObstetricFields(
                    data.template.extra_fields?.includes(
                        'estimated_gestational_age',
                    ) ?? false,
                );
            } else {
                setTemplate(null);
                setShowObstetricFields(false);
            }
        } catch (error) {
            console.error('Failed to fetch template:', error);
            setTemplate(null);
            setShowObstetricFields(false);
        } finally {
            setIsLoadingTemplate(false);
        }
    }, []);

    // Handle procedure selection
    const handleProcedureSelect = useCallback(
        (procedure: Procedure) => {
            setSelectedProcedure(procedure);
            if (procedure.has_template) {
                fetchTemplate(procedure.id);
            } else {
                setTemplate(null);
                setShowObstetricFields(false);
            }
        },
        [fetchTemplate],
    );

    // Handle template changes
    const handleTemplateChange = useCallback(
        (composedText: string, selections: Record<string, string>) => {
            setProcedureSteps(composedText);
            setTemplateSelections(selections);
        },
        [],
    );

    const handleAddProcedure = () => {
        if (!selectedProcedure) {
            toast.error('Please select a procedure');
            return;
        }

        setIsSubmitting(true);

        router.post(
            `/consultation/${consultationId}/procedures`,
            {
                minor_procedure_type_id: selectedProcedure.id,
                indication: indication || null,
                assistant: assistant || null,
                anaesthetist: anaesthetist || null,
                anaesthesia_type: anaesthesiaType || null,
                estimated_gestational_age: showObstetricFields
                    ? estimatedGestationalAge || null
                    : null,
                parity: showObstetricFields ? parity || null : null,
                procedure_subtype: showObstetricFields
                    ? procedureSubtype || null
                    : null,
                procedure_steps: procedureSteps || null,
                template_selections:
                    Object.keys(templateSelections).length > 0
                        ? templateSelections
                        : null,
                findings: findings || null,
                plan: plan || null,
                comments: comments || null,
                performed_at: new Date().toISOString(),
            },
            {
                onSuccess: () => {
                    toast.success('Procedure documented successfully');
                    resetForm();
                },
                onError: (errors) => {
                    const errorMessage =
                        typeof errors === 'object' && errors !== null
                            ? Object.values(errors)[0]
                            : 'Failed to add procedure';
                    toast.error(errorMessage as string);
                },
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    const handleDeleteProcedure = (procedureId: number) => {
        if (!confirm('Are you sure you want to remove this procedure?')) {
            return;
        }

        router.delete(
            `/consultation/${consultationId}/procedures/${procedureId}`,
            {
                onSuccess: () => toast.success('Procedure removed'),
                onError: (errors) => {
                    const errorMessage =
                        typeof errors === 'object' && errors !== null
                            ? Object.values(errors)[0]
                            : 'Failed to remove';
                    toast.error(errorMessage as string);
                },
            },
        );
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-GH', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    };

    const getAnaesthesiaLabel = (value: string | null) => {
        if (!value) return null;
        return ANAESTHESIA_TYPES.find((t) => t.value === value)?.label || value;
    };

    // Highlight template selections in procedure steps text
    const renderProcedureStepsWithHighlights = (
        text: string,
        selections: Record<string, string> | null,
    ) => {
        if (!selections || Object.keys(selections).length === 0) {
            return <span>{text}</span>;
        }

        // Get all selected values
        const selectedValues = Object.values(selections).filter(Boolean);
        if (selectedValues.length === 0) {
            return <span>{text}</span>;
        }

        // Create a regex pattern to match all selected values
        // Sort by length descending to match longer strings first
        const sortedValues = [...selectedValues].sort(
            (a, b) => b.length - a.length,
        );
        const escapedValues = sortedValues.map((v) =>
            v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
        );
        const pattern = new RegExp(`(${escapedValues.join('|')})`, 'g');

        // Split text by the pattern and render with highlights
        const parts = text.split(pattern);

        return (
            <>
                {parts.map((part, index) => {
                    if (selectedValues.includes(part)) {
                        return (
                            <span
                                key={index}
                                className="rounded bg-primary/20 px-1 font-semibold text-primary"
                            >
                                {part}
                            </span>
                        );
                    }
                    return <span key={index}>{part}</span>;
                })}
            </>
        );
    };

    return (
        <div className="space-y-6">
            {/* Add Procedure Form */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Plus className="h-5 w-5" />
                        Document Procedure
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    {/* Row 1: Procedure Selection (AsyncProcedureSearch) */}
                    <div className="space-y-2">
                        <Label htmlFor="procedure">Select Procedure *</Label>
                        <AsyncProcedureSearch
                            onSelect={handleProcedureSelect}
                            selectedProcedure={selectedProcedure}
                            placeholder="Search procedures by name or code..."
                        />
                    </div>

                    {/* Row 2: Indication */}
                    <div className="space-y-2">
                        <Label htmlFor="indication">Indication</Label>
                        <Textarea
                            id="indication"
                            placeholder="Reason for the procedure..."
                            value={indication}
                            onChange={(e) => setIndication(e.target.value)}
                            rows={2}
                        />
                    </div>

                    {/* Obstetric Fields (conditional for C-Section) */}
                    {showObstetricFields && (
                        <div className="rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-800 dark:bg-purple-900/20">
                            <h4 className="mb-3 font-medium text-purple-900 dark:text-purple-100">
                                Obstetric Information
                            </h4>
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="gestational_age">
                                        Estimated Gestational Age
                                    </Label>
                                    <Input
                                        id="gestational_age"
                                        placeholder="e.g., 38 weeks"
                                        value={estimatedGestationalAge}
                                        onChange={(e) =>
                                            setEstimatedGestationalAge(
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="parity">Parity</Label>
                                    <Input
                                        id="parity"
                                        placeholder="e.g., G2P1"
                                        value={parity}
                                        onChange={(e) =>
                                            setParity(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="procedure_subtype">
                                        Procedure Subtype
                                    </Label>
                                    <Select
                                        value={procedureSubtype || ''}
                                        onValueChange={setProcedureSubtype}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select subtype..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {CSECTION_SUBTYPES.map(
                                                (subtype) => (
                                                    <SelectItem
                                                        key={subtype}
                                                        value={subtype}
                                                    >
                                                        {subtype}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Row 3: Team */}
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="assistant">Assistant</Label>
                            <Input
                                id="assistant"
                                placeholder="Assistant surgeon name"
                                value={assistant}
                                onChange={(e) => setAssistant(e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="anaesthetist">Anaesthetist</Label>
                            <Input
                                id="anaesthetist"
                                placeholder="Anaesthetist name"
                                value={anaesthetist}
                                onChange={(e) =>
                                    setAnaesthetist(e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="anaesthesia_type">
                                Anaesthesia Type
                            </Label>
                            <Select
                                value={anaesthesiaType || ''}
                                onValueChange={setAnaesthesiaType}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select type..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {ANAESTHESIA_TYPES.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Row 4: Procedure Steps (Template or Textarea) */}
                    <div className="space-y-2">
                        <Label htmlFor="procedure_steps">Procedure Steps</Label>
                        {isLoadingTemplate ? (
                            <div className="flex h-32 items-center justify-center rounded-md border bg-muted/30">
                                <span className="text-sm text-muted-foreground">
                                    Loading template...
                                </span>
                            </div>
                        ) : template ? (
                            <TemplateRenderer
                                templateText={template.template_text}
                                variables={template.variables}
                                initialSelections={templateSelections}
                                onChange={handleTemplateChange}
                            />
                        ) : (
                            <Textarea
                                id="procedure_steps"
                                placeholder="Describe the procedure performed..."
                                value={procedureSteps}
                                onChange={(e) =>
                                    setProcedureSteps(e.target.value)
                                }
                                rows={4}
                            />
                        )}
                    </div>

                    {/* Row 5: Findings & Plan */}
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="findings">Findings</Label>
                            <Textarea
                                id="findings"
                                placeholder="Intraoperative findings..."
                                value={findings}
                                onChange={(e) => setFindings(e.target.value)}
                                rows={3}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="plan">Plan</Label>
                            <Textarea
                                id="plan"
                                placeholder="Post-operative orders and plan..."
                                value={plan}
                                onChange={(e) => setPlan(e.target.value)}
                                rows={3}
                            />
                        </div>
                    </div>

                    {/* Row 6: Additional Notes */}
                    <div className="space-y-2">
                        <Label htmlFor="comments">Additional Notes</Label>
                        <Textarea
                            id="comments"
                            placeholder="Any additional notes..."
                            value={comments}
                            onChange={(e) => setComments(e.target.value)}
                            rows={2}
                        />
                    </div>

                    <Button
                        onClick={handleAddProcedure}
                        disabled={!selectedProcedure || isSubmitting}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        {isSubmitting ? 'Saving...' : 'Save Procedure'}
                    </Button>
                </CardContent>
            </Card>

            {/* Procedures List */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Stethoscope className="h-5 w-5" />
                        Documented Procedures ({procedures.length})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {procedures.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Stethoscope className="mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-semibold">
                                No Procedures Documented
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Add procedures performed during this
                                consultation
                            </p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Procedure</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Anaesthesia</TableHead>
                                    <TableHead>Surgeon</TableHead>
                                    <TableHead>Date/Time</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {procedures.map((procedure) => (
                                    <TableRow key={procedure.id}>
                                        <TableCell className="font-medium">
                                            {procedure.procedure_type.name}
                                            <div className="text-xs text-muted-foreground">
                                                {procedure.procedure_type.code}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    procedure.procedure_type
                                                        .type === 'major'
                                                        ? 'border-purple-200 bg-purple-100 text-purple-700'
                                                        : 'border-blue-200 bg-blue-100 text-blue-700'
                                                }
                                            >
                                                {procedure.procedure_type
                                                    .type === 'major'
                                                    ? 'Major'
                                                    : 'Minor'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {getAnaesthesiaLabel(
                                                procedure.anaesthesia_type,
                                            ) || (
                                                <span className="text-muted-foreground">
                                                    -
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {procedure.doctor.name}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {formatDateTime(
                                                procedure.performed_at,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setViewProcedure(
                                                            procedure,
                                                        )
                                                    }
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleDeleteProcedure(
                                                            procedure.id,
                                                        )
                                                    }
                                                    className="text-destructive hover:text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* View Procedure Dialog */}
            <Dialog
                open={!!viewProcedure}
                onOpenChange={() => setViewProcedure(null)}
            >
                <DialogContent className="max-h-[80vh] w-full max-w-4xl overflow-y-auto sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Procedure Details</DialogTitle>
                    </DialogHeader>
                    {viewProcedure && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label className="text-muted-foreground">
                                        Procedure
                                    </Label>
                                    <p className="font-medium">
                                        {viewProcedure.procedure_type.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {viewProcedure.procedure_type.code}
                                    </p>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground">
                                        Date/Time
                                    </Label>
                                    <p>
                                        {formatDateTime(
                                            viewProcedure.performed_at,
                                        )}
                                    </p>
                                </div>
                            </div>

                            {viewProcedure.indication && (
                                <div>
                                    <Label className="text-muted-foreground">
                                        Indication
                                    </Label>
                                    <p className="whitespace-pre-wrap">
                                        {viewProcedure.indication}
                                    </p>
                                </div>
                            )}

                            {/* Obstetric fields if present */}
                            {(viewProcedure.estimated_gestational_age ||
                                viewProcedure.parity ||
                                viewProcedure.procedure_subtype) && (
                                <div className="rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-800 dark:bg-purple-900/20">
                                    <h4 className="mb-3 font-medium text-purple-900 dark:text-purple-100">
                                        Obstetric Information
                                    </h4>
                                    <div className="grid grid-cols-3 gap-4">
                                        <div>
                                            <Label className="text-muted-foreground">
                                                Gestational Age
                                            </Label>
                                            <p>
                                                {viewProcedure.estimated_gestational_age ||
                                                    '-'}
                                            </p>
                                        </div>
                                        <div>
                                            <Label className="text-muted-foreground">
                                                Parity
                                            </Label>
                                            <p>{viewProcedure.parity || '-'}</p>
                                        </div>
                                        <div>
                                            <Label className="text-muted-foreground">
                                                Subtype
                                            </Label>
                                            <p>
                                                {viewProcedure.procedure_subtype ||
                                                    '-'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <Label className="text-muted-foreground">
                                        Surgeon
                                    </Label>
                                    <p>{viewProcedure.doctor.name}</p>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground">
                                        Assistant
                                    </Label>
                                    <p>{viewProcedure.assistant || '-'}</p>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground">
                                        Anaesthetist
                                    </Label>
                                    <p>{viewProcedure.anaesthetist || '-'}</p>
                                </div>
                            </div>

                            <div>
                                <Label className="text-muted-foreground">
                                    Anaesthesia Type
                                </Label>
                                <p>
                                    {getAnaesthesiaLabel(
                                        viewProcedure.anaesthesia_type,
                                    ) || '-'}
                                </p>
                            </div>

                            {viewProcedure.procedure_steps && (
                                <div>
                                    <Label className="text-muted-foreground">
                                        Procedure Steps
                                    </Label>
                                    <p className="whitespace-pre-wrap">
                                        {renderProcedureStepsWithHighlights(
                                            viewProcedure.procedure_steps,
                                            viewProcedure.template_selections,
                                        )}
                                    </p>
                                </div>
                            )}

                            {viewProcedure.findings && (
                                <div>
                                    <Label className="text-muted-foreground">
                                        Findings
                                    </Label>
                                    <p className="whitespace-pre-wrap">
                                        {viewProcedure.findings}
                                    </p>
                                </div>
                            )}

                            {viewProcedure.plan && (
                                <div>
                                    <Label className="text-muted-foreground">
                                        Plan
                                    </Label>
                                    <p className="whitespace-pre-wrap">
                                        {viewProcedure.plan}
                                    </p>
                                </div>
                            )}

                            {viewProcedure.comments && (
                                <div>
                                    <Label className="text-muted-foreground">
                                        Additional Notes
                                    </Label>
                                    <p className="whitespace-pre-wrap">
                                        {viewProcedure.comments}
                                    </p>
                                </div>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
