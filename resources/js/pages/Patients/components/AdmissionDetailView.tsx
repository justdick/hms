import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Beaker,
    ChevronDown,
    ChevronRight,
    ClipboardList,
    Eye,
    FileText,
    Loader2,
    Pill,
    Scissors,
    Stethoscope,
    Thermometer,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

// Types
interface WardRoundDetail {
    id: number;
    date: string | null;
    doctor: string | null;
    round_type: string | null;
    presenting_complaint: string | null;
    history_presenting_complaint: string | null;
    on_direct_questioning: string | null;
    examination_findings: string | null;
    assessment_notes: string | null;
    plan_notes: string | null;
    patient_status: string | null;
    prescriptions: PrescriptionDetail[];
    lab_orders: LabOrderDetail[];
    procedures: ProcedureDetail[];
}

interface PrescriptionDetail {
    id?: number;
    drug_name: string | null;
    generic_name: string | null;
    form: string | null;
    strength: string | null;
    dose_quantity: string | null;
    frequency: string | null;
    duration: string | null;
    quantity: number | null;
    instructions: string | null;
    status: string;
}

interface LabOrderDetail {
    id: number;
    service_name: string | null;
    code: string | null;
    is_imaging: boolean;
    test_parameters?: {
        parameters: Array<{
            name: string;
            label: string;
            type: string;
            unit?: string;
            normal_range?: { min?: number; max?: number };
        }>;
    } | null;
    status: string;
    result_values: Record<string, unknown> | null;
    result_notes: string | null;
    ordered_at: string | null;
    result_entered_at: string | null;
}

interface ProcedureDetail {
    name: string | null;
    code: string | null;
    notes: string | null;
}

interface VitalSignDetail {
    id: number;
    recorded_at: string | null;
    recorded_by: string | null;
    blood_pressure: string | null;
    temperature: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    oxygen_saturation: number | null;
    blood_sugar: number | null;
    weight: number | null;
    height: number | null;
    bmi: number | null;
    notes: string | null;
}

interface MedicationAdministrationDetail {
    id: number;
    prescription_id: number;
    drug_name: string;
    drug_strength: string | null;
    administered_at: string | null;
    status: 'given' | 'held' | 'refused' | 'omitted';
    dosage_given: string | null;
    route: string | null;
    administered_by: string | null;
    notes: string | null;
}

interface NursingNoteDetail {
    id: number;
    type: string;
    note: string;
    noted_at: string | null;
    nurse: string | null;
}

interface AdmissionDetailData {
    ward_rounds: WardRoundDetail[];
    vitals: VitalSignDetail[];
    medication_administrations: MedicationAdministrationDetail[];
    nursing_notes: NursingNoteDetail[];
    diagnoses: { id: number; type: string; code: string | null; description: string | null; is_active: boolean }[];
    prescriptions: PrescriptionDetail[];
    lab_orders: LabOrderDetail[];
}

interface Props {
    patientId: number;
    admissionId: number;
}

export default function AdmissionDetailView({ patientId, admissionId }: Props) {
    const [data, setData] = useState<AdmissionDetailData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [selectedLabResult, setSelectedLabResult] = useState<LabOrderDetail | null>(null);

    const fetchDetail = useCallback(async () => {
        if (data) return;
        setLoading(true);
        setError(null);
        try {
            const response = await fetch(
                `/patients/${patientId}/admissions/${admissionId}/detail`,
            );
            if (!response.ok) throw new Error('Failed to load admission details');
            const json = await response.json();
            setData(json);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'An error occurred');
        } finally {
            setLoading(false);
        }
    }, [patientId, admissionId, data]);

    useEffect(() => {
        fetchDetail();
    }, [fetchDetail]);

    const formatDateTime = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const formatTime = (dateString: string | null) => {
        if (!dateString) return '';
        return new Date(dateString).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusBadge = (status: string) => {
        const config: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; className?: string }> = {
            given: { variant: 'default', className: 'bg-green-600 hover:bg-green-700' },
            held: { variant: 'secondary' },
            refused: { variant: 'destructive' },
            omitted: { variant: 'outline' },
        };
        const c = config[status] || { variant: 'outline' as const };
        return <Badge variant={c.variant} className={c.className}>{status}</Badge>;
    };

    const getNoteTypeBadge = (type: string) => {
        const config: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; className?: string }> = {
            admission: { variant: 'default', className: 'bg-blue-600 hover:bg-blue-700' },
            assessment: { variant: 'secondary' },
            care: { variant: 'default', className: 'bg-green-600 hover:bg-green-700' },
            observation: { variant: 'outline' },
            incident: { variant: 'destructive' },
            handover: { variant: 'secondary' },
        };
        const c = config[type] || { variant: 'outline' as const };
        return <Badge variant={c.variant} className={c.className}>{type}</Badge>;
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-8">
                <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                <span className="ml-2 text-sm text-muted-foreground">Loading admission details...</span>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col items-center justify-center py-8">
                <p className="text-sm text-destructive">{error}</p>
                <Button variant="outline" size="sm" className="mt-3" onClick={() => { setData(null); fetchDetail(); }}>
                    Retry
                </Button>
            </div>
        );
    }

    if (!data) return null;

    return (
        <>
            <Tabs defaultValue="ward-rounds" className="space-y-3">
                <TabsList className="grid w-full grid-cols-5 gap-1 rounded-none border-b border-gray-200 bg-transparent p-1 dark:border-gray-700">
                    <TabsTrigger
                        value="ward-rounds"
                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-violet-50 text-xs text-violet-700 shadow-none transition-all hover:bg-violet-100 data-[state=active]:border-violet-600 data-[state=active]:bg-violet-100 data-[state=active]:text-violet-700 data-[state=active]:shadow-none dark:bg-violet-950 dark:text-violet-300 dark:hover:bg-violet-900 dark:data-[state=active]:border-violet-400 dark:data-[state=active]:bg-violet-900 dark:data-[state=active]:text-violet-300"
                    >
                        <Stethoscope className="h-3.5 w-3.5" />
                        <span className="hidden sm:inline">Ward Rounds</span>
                        <span className="sm:hidden">Rounds</span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="mar"
                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-green-50 text-xs text-green-700 shadow-none transition-all hover:bg-green-100 data-[state=active]:border-green-600 data-[state=active]:bg-green-100 data-[state=active]:text-green-700 data-[state=active]:shadow-none dark:bg-green-950 dark:text-green-300 dark:hover:bg-green-900 dark:data-[state=active]:border-green-400 dark:data-[state=active]:bg-green-900 dark:data-[state=active]:text-green-300"
                    >
                        <Pill className="h-3.5 w-3.5" />
                        MAR
                    </TabsTrigger>
                    <TabsTrigger
                        value="vitals"
                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-rose-50 text-xs text-rose-700 shadow-none transition-all hover:bg-rose-100 data-[state=active]:border-rose-600 data-[state=active]:bg-rose-100 data-[state=active]:text-rose-700 data-[state=active]:shadow-none dark:bg-rose-950 dark:text-rose-300 dark:hover:bg-rose-900 dark:data-[state=active]:border-rose-400 dark:data-[state=active]:bg-rose-900 dark:data-[state=active]:text-rose-300"
                    >
                        <Thermometer className="h-3.5 w-3.5" />
                        <span className="hidden sm:inline">Vitals</span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="nursing-notes"
                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-blue-50 text-xs text-blue-700 shadow-none transition-all hover:bg-blue-100 data-[state=active]:border-blue-600 data-[state=active]:bg-blue-100 data-[state=active]:text-blue-700 data-[state=active]:shadow-none dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900 dark:data-[state=active]:border-blue-400 dark:data-[state=active]:bg-blue-900 dark:data-[state=active]:text-blue-300"
                    >
                        <ClipboardList className="h-3.5 w-3.5" />
                        <span className="hidden sm:inline">Nursing Notes</span>
                        <span className="sm:hidden">Notes</span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="labs"
                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-teal-50 text-xs text-teal-700 shadow-none transition-all hover:bg-teal-100 data-[state=active]:border-teal-600 data-[state=active]:bg-teal-100 data-[state=active]:text-teal-700 data-[state=active]:shadow-none dark:bg-teal-950 dark:text-teal-300 dark:hover:bg-teal-900 dark:data-[state=active]:border-teal-400 dark:data-[state=active]:bg-teal-900 dark:data-[state=active]:text-teal-300"
                    >
                        <Beaker className="h-3.5 w-3.5" />
                        Labs
                    </TabsTrigger>
                </TabsList>

                {/* Ward Rounds Tab */}
                <TabsContent value="ward-rounds" className="space-y-3">
                    <WardRoundsSection
                        wardRounds={data.ward_rounds}
                        formatDateTime={formatDateTime}
                        onViewLabResult={setSelectedLabResult}
                    />
                </TabsContent>

                {/* MAR Tab */}
                <TabsContent value="mar">
                    <MARSection
                        administrations={data.medication_administrations}
                        formatDate={formatDate}
                        formatTime={formatTime}
                        getStatusBadge={getStatusBadge}
                    />
                </TabsContent>

                {/* Vitals Tab */}
                <TabsContent value="vitals">
                    <VitalsSection
                        vitals={data.vitals}
                        formatDateTime={formatDateTime}
                    />
                </TabsContent>

                {/* Nursing Notes Tab */}
                <TabsContent value="nursing-notes" className="space-y-3">
                    <NursingNotesSection
                        notes={data.nursing_notes}
                        formatDateTime={formatDateTime}
                        getNoteTypeBadge={getNoteTypeBadge}
                    />
                </TabsContent>

                {/* Labs Tab */}
                <TabsContent value="labs" className="space-y-3">
                    <LabsSection
                        labOrders={data.lab_orders}
                        formatDateTime={formatDateTime}
                        onViewResult={setSelectedLabResult}
                    />
                </TabsContent>
            </Tabs>

            {/* Lab Result Detail Modal */}
            <LabResultModal
                labResult={selectedLabResult}
                onClose={() => setSelectedLabResult(null)}
                formatDateTime={formatDateTime}
            />
        </>
    );
}


// ─── Ward Rounds Section ─────────────────────────────────────────────────────

function WardRoundsSection({
    wardRounds,
    formatDateTime,
    onViewLabResult,
}: {
    wardRounds: WardRoundDetail[];
    formatDateTime: (d: string | null) => string;
    onViewLabResult: (l: LabOrderDetail) => void;
}) {
    const [expandedRounds, setExpandedRounds] = useState<Set<number>>(
        new Set(wardRounds.length > 0 ? [wardRounds[0].id] : []),
    );

    const toggleRound = (id: number) => {
        setExpandedRounds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    if (wardRounds.length === 0) {
        return <EmptyState icon={Stethoscope} message="No ward rounds recorded" />;
    }

    return (
        <div className="space-y-2">
            {wardRounds.map((wr) => {
                const isExpanded = expandedRounds.has(wr.id);
                return (
                    <div key={wr.id} className="rounded-lg border overflow-hidden">
                        <button
                            type="button"
                            onClick={() => toggleRound(wr.id)}
                            className="flex w-full items-center justify-between bg-violet-50 px-3 py-2.5 text-left transition-colors hover:bg-violet-100 dark:bg-violet-950 dark:hover:bg-violet-900"
                        >
                            <div className="flex items-center gap-2">
                                {isExpanded ? (
                                    <ChevronDown className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                                ) : (
                                    <ChevronRight className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                                )}
                                <Stethoscope className="h-3.5 w-3.5 text-violet-600 dark:text-violet-400" />
                                <span className="text-sm font-medium text-violet-700 dark:text-violet-300">
                                    {wr.round_type || 'Ward Round'}
                                </span>
                                {wr.patient_status && (
                                    <Badge variant="outline" className="text-xs">
                                        {wr.patient_status}
                                    </Badge>
                                )}
                            </div>
                            <span className="text-xs text-violet-600 dark:text-violet-400">
                                {formatDateTime(wr.date)}
                                {wr.doctor && ` • Dr. ${wr.doctor}`}
                            </span>
                        </button>

                        {isExpanded && (
                            <div className="p-3 space-y-3">
                                {/* Clinical notes */}
                                <div className="grid gap-2 text-sm">
                                    {wr.presenting_complaint && (
                                        <div>
                                            <span className="font-medium text-blue-700 dark:text-blue-400">PC:</span>{' '}
                                            {wr.presenting_complaint}
                                        </div>
                                    )}
                                    {wr.history_presenting_complaint && (
                                        <div>
                                            <span className="font-medium text-teal-700 dark:text-teal-400">HPC:</span>{' '}
                                            {wr.history_presenting_complaint}
                                        </div>
                                    )}
                                    {wr.on_direct_questioning && (
                                        <div>
                                            <span className="font-medium text-cyan-700 dark:text-cyan-400">ODQ:</span>{' '}
                                            {wr.on_direct_questioning}
                                        </div>
                                    )}
                                    {wr.examination_findings && (
                                        <div>
                                            <span className="font-medium text-amber-700 dark:text-amber-400">Exam:</span>{' '}
                                            {wr.examination_findings}
                                        </div>
                                    )}
                                    {wr.assessment_notes && (
                                        <div>
                                            <span className="font-medium text-orange-700 dark:text-orange-400">Assessment:</span>{' '}
                                            {wr.assessment_notes}
                                        </div>
                                    )}
                                    {wr.plan_notes && (
                                        <div>
                                            <span className="font-medium text-emerald-700 dark:text-emerald-400">Plan:</span>{' '}
                                            {wr.plan_notes}
                                        </div>
                                    )}
                                </div>

                                {/* Prescriptions */}
                                {wr.prescriptions.length > 0 && (
                                    <div>
                                        <h5 className="mb-1 text-xs font-medium text-gray-500">Prescriptions</h5>
                                        <div className="space-y-0.5">
                                            {wr.prescriptions.map((p, idx) => (
                                                <div key={idx} className="flex items-center gap-2 text-sm">
                                                    <Pill className="h-3 w-3 text-muted-foreground" />
                                                    <span>
                                                        {p.drug_name}
                                                        {p.strength && ` ${p.strength}`}
                                                        {p.dose_quantity && ` - ${p.dose_quantity}`}
                                                        {p.frequency && ` ${p.frequency}`}
                                                        {p.duration && ` x ${p.duration}`}
                                                    </span>
                                                    <Badge variant="outline" className="text-xs">{p.status}</Badge>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Lab Orders */}
                                {wr.lab_orders.length > 0 && (
                                    <div>
                                        <h5 className="mb-1 text-xs font-medium text-gray-500">Lab / Imaging</h5>
                                        <div className="space-y-0.5">
                                            {wr.lab_orders.map((l) => (
                                                <div key={l.id} className="flex items-center gap-2 text-sm">
                                                    <Beaker className="h-3 w-3 text-muted-foreground" />
                                                    <span>{l.service_name}</span>
                                                    <Badge variant={l.status === 'completed' ? 'default' : 'secondary'} className="text-xs">
                                                        {l.status}
                                                    </Badge>
                                                    {l.status === 'completed' && l.result_values && (
                                                        <Button variant="ghost" size="sm" className="h-6 px-2 text-xs" onClick={() => onViewLabResult(l)}>
                                                            <Eye className="mr-1 h-3 w-3" />
                                                            View
                                                        </Button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Procedures */}
                                {wr.procedures.length > 0 && (
                                    <div>
                                        <h5 className="mb-1 text-xs font-medium text-gray-500">Procedures</h5>
                                        <div className="space-y-0.5">
                                            {wr.procedures.map((p, idx) => (
                                                <div key={idx} className="flex items-center gap-2 text-sm">
                                                    <Scissors className="h-3 w-3 text-muted-foreground" />
                                                    <span>{p.name}</span>
                                                    {p.notes && <span className="text-muted-foreground">— {p.notes}</span>}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

// ─── MAR Section ─────────────────────────────────────────────────────────────

function MARSection({
    administrations,
    formatDate,
    formatTime,
    getStatusBadge,
}: {
    administrations: MedicationAdministrationDetail[];
    formatDate: (d: string | null) => string;
    formatTime: (d: string | null) => string;
    getStatusBadge: (status: string) => React.ReactNode;
}) {
    const [collapsedGroups, setCollapsedGroups] = useState<Set<string>>(new Set());

    if (administrations.length === 0) {
        return <EmptyState icon={Pill} message="No medication administration records" />;
    }

    // Group by drug name
    const grouped = administrations.reduce<Record<string, MedicationAdministrationDetail[]>>((acc, ma) => {
        const key = `${ma.prescription_id}-${ma.drug_name}`;
        if (!acc[key]) acc[key] = [];
        acc[key].push(ma);
        return acc;
    }, {});

    const toggleGroup = (key: string) => {
        setCollapsedGroups((prev) => {
            const next = new Set(prev);
            if (next.has(key)) next.delete(key);
            else next.add(key);
            return next;
        });
    };

    return (
        <div className="space-y-3">
            {Object.entries(grouped).map(([key, records]) => {
                const isCollapsed = collapsedGroups.has(key);
                const first = records[0];
                const drugLabel = `${first.drug_name}${first.drug_strength ? ` ${first.drug_strength}` : ''}`;

                return (
                    <div key={key} className="overflow-hidden rounded-lg border">
                        <button
                            type="button"
                            onClick={() => toggleGroup(key)}
                            className="flex w-full items-center gap-2 bg-muted/50 px-4 py-2.5 text-left transition-colors hover:bg-muted"
                        >
                            {isCollapsed ? (
                                <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                            ) : (
                                <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
                            )}
                            <Pill className="h-4 w-4 shrink-0 text-primary" />
                            <span className="font-semibold text-foreground text-sm">{drugLabel}</span>
                            <Badge variant="secondary" className="ml-auto text-xs">
                                {records.length} record{records.length !== 1 ? 's' : ''}
                            </Badge>
                        </button>

                        {!isCollapsed && (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[130px]">Date</TableHead>
                                        <TableHead className="w-[80px]">Time</TableHead>
                                        <TableHead className="w-[100px]">Dose</TableHead>
                                        <TableHead className="w-[80px]">Route</TableHead>
                                        <TableHead className="w-[90px]">Status</TableHead>
                                        <TableHead>Given By</TableHead>
                                        <TableHead>Notes</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {records.map((admin) => (
                                        <TableRow key={admin.id}>
                                            <TableCell className="text-sm">{formatDate(admin.administered_at)}</TableCell>
                                            <TableCell className="text-sm">{formatTime(admin.administered_at)}</TableCell>
                                            <TableCell className="text-sm">{admin.dosage_given || <span className="text-muted-foreground">-</span>}</TableCell>
                                            <TableCell className="text-sm capitalize">{admin.route || <span className="text-muted-foreground">-</span>}</TableCell>
                                            <TableCell>{getStatusBadge(admin.status)}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{admin.administered_by || 'Unknown'}</TableCell>
                                            <TableCell className="text-sm max-w-[150px] truncate" title={admin.notes || ''}>
                                                {admin.notes || <span className="text-muted-foreground">-</span>}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

// ─── Vitals Section ──────────────────────────────────────────────────────────

function VitalsSection({
    vitals,
    formatDateTime,
}: {
    vitals: VitalSignDetail[];
    formatDateTime: (d: string | null) => string;
}) {
    if (vitals.length === 0) {
        return <EmptyState icon={Thermometer} message="No vitals recorded during this admission" />;
    }

    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-[150px]">Date/Time</TableHead>
                        <TableHead>BP</TableHead>
                        <TableHead>Temp</TableHead>
                        <TableHead>Pulse</TableHead>
                        <TableHead>RR</TableHead>
                        <TableHead>SpO2</TableHead>
                        <TableHead>Sugar</TableHead>
                        <TableHead>Wt (kg)</TableHead>
                        <TableHead>Recorded By</TableHead>
                        <TableHead>Notes</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {vitals.map((v) => (
                        <TableRow key={v.id}>
                            <TableCell className="text-sm font-medium">{formatDateTime(v.recorded_at)}</TableCell>
                            <TableCell className="text-sm">{v.blood_pressure || '-'}</TableCell>
                            <TableCell className="text-sm">{v.temperature ? `${v.temperature}°C` : '-'}</TableCell>
                            <TableCell className="text-sm">{v.pulse_rate || '-'}</TableCell>
                            <TableCell className="text-sm">{v.respiratory_rate || '-'}</TableCell>
                            <TableCell className="text-sm">{v.oxygen_saturation ? `${v.oxygen_saturation}%` : '-'}</TableCell>
                            <TableCell className="text-sm">{v.blood_sugar || '-'}</TableCell>
                            <TableCell className="text-sm">{v.weight ? `${v.weight}kg` : '-'}</TableCell>
                            <TableCell className="text-sm text-muted-foreground">{v.recorded_by || '-'}</TableCell>
                            <TableCell className="text-sm max-w-[120px] truncate" title={v.notes || ''}>
                                {v.notes || '-'}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

// ─── Nursing Notes Section ───────────────────────────────────────────────────

function NursingNotesSection({
    notes,
    formatDateTime,
    getNoteTypeBadge,
}: {
    notes: NursingNoteDetail[];
    formatDateTime: (d: string | null) => string;
    getNoteTypeBadge: (type: string) => React.ReactNode;
}) {
    if (notes.length === 0) {
        return <EmptyState icon={ClipboardList} message="No nursing notes recorded" />;
    }

    return (
        <div className="space-y-3">
            {notes.map((note) => (
                <div key={note.id} className="rounded-lg border p-3">
                    <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center gap-2">
                            {getNoteTypeBadge(note.type)}
                            {note.nurse && (
                                <span className="text-sm text-muted-foreground">{note.nurse}</span>
                            )}
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {formatDateTime(note.noted_at)}
                        </span>
                    </div>
                    <p className="text-sm whitespace-pre-wrap">{note.note}</p>
                </div>
            ))}
        </div>
    );
}

// ─── Labs Section ────────────────────────────────────────────────────────────

function LabsSection({
    labOrders,
    formatDateTime,
    onViewResult,
}: {
    labOrders: LabOrderDetail[];
    formatDateTime: (d: string | null) => string;
    onViewResult: (l: LabOrderDetail) => void;
}) {
    if (labOrders.length === 0) {
        return <EmptyState icon={Beaker} message="No lab orders for this admission" />;
    }

    return (
        <div className="space-y-2">
            {labOrders.map((l) => (
                <div key={l.id} className="flex items-center justify-between rounded-lg border px-3 py-2">
                    <div className="flex items-center gap-2">
                        {l.is_imaging ? (
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        ) : (
                            <Beaker className="h-4 w-4 text-muted-foreground" />
                        )}
                        <div>
                            <span className="text-sm font-medium">{l.service_name}</span>
                            {l.code && <span className="ml-1 text-xs text-muted-foreground">({l.code})</span>}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground">{formatDateTime(l.ordered_at)}</span>
                        <Badge variant={l.status === 'completed' ? 'default' : 'secondary'} className="text-xs">
                            {l.status}
                        </Badge>
                        {l.status === 'completed' && l.result_values && (
                            <Button variant="ghost" size="sm" className="h-6 px-2 text-xs" onClick={() => onViewResult(l)}>
                                <Eye className="mr-1 h-3 w-3" />
                                View Results
                            </Button>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}

// ─── Lab Result Modal ────────────────────────────────────────────────────────

function LabResultModal({
    labResult,
    onClose,
    formatDateTime,
}: {
    labResult: LabOrderDetail | null;
    onClose: () => void;
    formatDateTime: (d: string | null) => string;
}) {
    if (!labResult) return null;

    const getFlagColor = (flag: string) => {
        switch (flag) {
            case 'high':
            case 'critical':
                return 'text-red-600 dark:text-red-400';
            case 'low':
                return 'text-orange-600 dark:text-orange-400';
            default:
                return '';
        }
    };

    return (
        <Dialog open={!!labResult} onOpenChange={() => onClose()}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {labResult.is_imaging ? <FileText className="h-5 w-5" /> : <Beaker className="h-5 w-5" />}
                        {labResult.service_name}
                        {labResult.code && (
                            <span className="text-sm font-normal text-muted-foreground">({labResult.code})</span>
                        )}
                    </DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    {labResult.result_entered_at && (
                        <div className="text-sm text-muted-foreground">
                            Results entered: {formatDateTime(labResult.result_entered_at)}
                        </div>
                    )}

                    {labResult.result_values && Object.keys(labResult.result_values).length > 0 && (
                        <div className="space-y-2">
                            <h4 className="text-sm font-medium">Results</h4>
                            <div className="max-h-[50vh] overflow-y-auto pr-1">
                                <div className="grid gap-2 grid-cols-2 sm:grid-cols-3">
                                    {Object.entries(labResult.result_values).map(([key, result]) => {
                                        const isObject = typeof result === 'object' && result !== null;
                                        const value = isObject ? (result as Record<string, unknown>).value : result;
                                        const unit = isObject ? String((result as Record<string, unknown>).unit || '') : '';
                                        let range: string = isObject ? String((result as Record<string, unknown>).range || '') : '';
                                        let flag = isObject ? ((result as Record<string, unknown>).flag as string) : 'normal';

                                        if (!range && labResult.test_parameters?.parameters) {
                                            const param = labResult.test_parameters.parameters.find(
                                                (p) => p.name === key || p.name.toLowerCase() === key.toLowerCase(),
                                            );
                                            if (param?.normal_range) {
                                                const { min, max } = param.normal_range;
                                                if (min !== undefined && max !== undefined) range = `${min}-${max}`;
                                                else if (min !== undefined) range = `>${min}`;
                                                else if (max !== undefined) range = `<${max}`;

                                                if (flag === 'normal' && param.type === 'numeric') {
                                                    const numValue = parseFloat(String(value));
                                                    if (!isNaN(numValue)) {
                                                        if (min !== undefined && numValue < min) flag = 'low';
                                                        else if (max !== undefined && numValue > max) flag = 'high';
                                                    }
                                                }
                                            }
                                        }

                                        return (
                                            <div key={key} className="rounded-lg border px-2.5 py-2">
                                                <span className="text-xs text-muted-foreground capitalize">
                                                    {key.replace(/_/g, ' ')}
                                                </span>
                                                <p className={`text-sm font-semibold leading-tight ${getFlagColor(flag)}`}>
                                                    {String(value)}
                                                    {unit && <span className="ml-1 text-xs font-normal text-muted-foreground">{unit}</span>}
                                                </p>
                                                <div className="flex items-center gap-1 mt-0.5">
                                                    {range && <span className="text-xs text-muted-foreground">Ref: {range}</span>}
                                                    {flag && flag !== 'normal' && (
                                                        <span className={`text-xs font-medium ${getFlagColor(flag)}`}>
                                                            ({flag.toUpperCase()})
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    )}

                    {labResult.result_notes && (
                        <div>
                            <h4 className="mb-1 text-sm font-medium">Notes</h4>
                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">{labResult.result_notes}</p>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

// ─── Empty State ─────────────────────────────────────────────────────────────

function EmptyState({
    icon: Icon,
    message,
}: {
    icon: React.ComponentType<{ className?: string }>;
    message: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center py-8">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                <Icon className="h-6 w-6 text-muted-foreground/50" />
            </div>
            <p className="mt-3 text-sm text-muted-foreground">{message}</p>
        </div>
    );
}
