import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { CheckCircle, FileText, Lightbulb, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface MedicalHistoryTemplate {
    id: string;
    name: string;
    category: string;
    presentingComplaint?: string;
    historyPresentingComplaint?: string;
    onDirectQuestioning?: string;
    examinationFindings?: string;
    assessment?: string;
    plan?: string;
}

interface QuickPhrase {
    abbreviation: string;
    expansion: string;
    category: string;
}

interface PatientHistories {
    past_medical_surgical_history: string;
    drug_history: string;
    family_history: string;
    social_history: string;
}

interface MedicalHistoryNotesProps {
    initialData: {
        presenting_complaint: string;
        history_presenting_complaint: string;
        on_direct_questioning: string;
        examination_findings: string;
        assessment_notes: string;
        plan_notes: string;
        follow_up_date: string;
    };
    patientHistories: PatientHistories;
    onDataChange: (data: any) => void;
    onPatientHistoryUpdate: (field: string, value: string) => void;
    onSubmit: (e: React.FormEvent) => void;
    processing: boolean;
    status: string;
}

const commonTemplates: MedicalHistoryTemplate[] = [
    {
        id: 'hypertension-followup',
        name: 'Hypertension Follow-up',
        category: 'Cardiology',
        presentingComplaint: 'Follow-up for hypertension management',
        historyPresentingComplaint:
            'Patient reports compliance with antihypertensive medications for the past 3 months. Currently on Amlodipine 5mg OD. No chest pain or palpitations.',
        onDirectQuestioning:
            'No headaches, dizziness, visual disturbances. No shortness of breath or pedal edema. No epistaxis.',
        examinationFindings:
            'BP: 130/80 mmHg, HR: 72/min regular. CVS: S1 S2 normal, no murmurs. Chest: clear. Abdomen: soft, non-tender. No pedal edema.',
        assessment: 'Hypertension, well-controlled on current regimen',
        plan: 'Continue Amlodipine 5mg OD. Lifestyle modifications reinforced. Recheck BP in 3 months.',
    },
    {
        id: 'diabetes-followup',
        name: 'Diabetes Follow-up',
        category: 'Endocrinology',
        presentingComplaint: 'Routine diabetes management review',
        historyPresentingComplaint:
            'Patient monitoring blood glucose regularly, fasting sugars 110-130 mg/dL. Currently on Metformin 500mg BD. Diet compliance good.',
        onDirectQuestioning:
            'No polyuria, polydipsia, or polyphagia. No visual changes. No numbness or tingling in extremities.',
        examinationFindings:
            'Weight stable at 70kg. Foot exam: pulses present, no ulcers. Monofilament test normal. Fundoscopy: no diabetic retinopathy.',
        assessment: 'Type 2 Diabetes Mellitus, stable on current regimen',
        plan: 'Continue Metformin 500mg BD. HbA1c in 3 months. Annual ophthalmology referral scheduled.',
    },
    {
        id: 'urti',
        name: 'Upper Respiratory Tract Infection',
        category: 'General',
        presentingComplaint: 'Cough and sore throat for 3 days',
        historyPresentingComplaint:
            'Dry cough started 3 days ago, now productive with white sputum. Sore throat, worse on swallowing. Low-grade fever noted at home.',
        onDirectQuestioning:
            'No shortness of breath or chest pain. No ear pain or discharge. No rash.',
        examinationFindings:
            'Temp: 37.8°C. Throat: erythematous, no exudate. Tonsils not enlarged. Chest: clear to auscultation bilaterally. No cervical lymphadenopathy.',
        assessment: 'Acute viral upper respiratory tract infection',
        plan: 'Symptomatic treatment. Paracetamol 1g QDS PRN. Throat lozenges. Adequate hydration. Review if worsening or fever persists >3 days.',
    },
];

const quickPhrases: QuickPhrase[] = [
    {
        abbreviation: 'wnl',
        expansion: 'within normal limits',
        category: 'general',
    },
    {
        abbreviation: 'sob',
        expansion: 'shortness of breath',
        category: 'symptoms',
    },
    { abbreviation: 'cp', expansion: 'chest pain', category: 'symptoms' },
    {
        abbreviation: 'nkda',
        expansion: 'no known drug allergies',
        category: 'general',
    },
    {
        abbreviation: 'rrr',
        expansion: 'regular rate and rhythm',
        category: 'cardiac',
    },
    {
        abbreviation: 'ctab',
        expansion: 'clear to auscultation bilaterally',
        category: 'pulmonary',
    },
    {
        abbreviation: 'nab',
        expansion: 'no acute distress',
        category: 'general',
    },
    {
        abbreviation: 'heent',
        expansion: 'head, eyes, ears, nose, throat',
        category: 'examination',
    },
    {
        abbreviation: 'rom',
        expansion: 'range of motion',
        category: 'musculoskeletal',
    },
    { abbreviation: 'rto', expansion: 'return to office', category: 'plan' },
    {
        abbreviation: 'pmsh',
        expansion: 'past medical/surgical history',
        category: 'history',
    },
    { abbreviation: 'nill', expansion: 'nil significant', category: 'general' },
];

export default function MedicalHistoryNotes({
    initialData,
    patientHistories,
    onDataChange,
    onPatientHistoryUpdate,
    onSubmit,
    processing,
    status,
}: MedicalHistoryNotesProps) {
    const [data, setData] = useState(initialData);
    const [histories, setHistories] = useState(patientHistories);
    const [showTemplates, setShowTemplates] = useState(false);
    const [showPhrases, setShowPhrases] = useState(false);
    const [completionStats, setCompletionStats] = useState({
        presenting_complaint: 0,
        history_presenting_complaint: 0,
        on_direct_questioning: 0,
        examination_findings: 0,
        assessment_notes: 0,
        plan_notes: 0,
    });

    const textareaRefs = {
        presenting_complaint: useRef<HTMLTextAreaElement>(null),
        history_presenting_complaint: useRef<HTMLTextAreaElement>(null),
        on_direct_questioning: useRef<HTMLTextAreaElement>(null),
        examination_findings: useRef<HTMLTextAreaElement>(null),
        assessment_notes: useRef<HTMLTextAreaElement>(null),
        plan_notes: useRef<HTMLTextAreaElement>(null),
    };

    useEffect(() => {
        const stats = Object.keys(data).reduce(
            (acc, key) => {
                if (
                    key.includes('notes') ||
                    key.includes('complaint') ||
                    key.includes('questioning') ||
                    key.includes('findings')
                ) {
                    const wordCount = data[key as keyof typeof data]
                        .split(' ')
                        .filter((word) => word.length > 0).length;
                    acc[key as keyof typeof completionStats] = wordCount;
                }
                return acc;
            },
            {} as typeof completionStats,
        );

        setCompletionStats(stats);
    }, [data]);

    const handleDataChange = (key: string, value: string) => {
        const newData = { ...data, [key]: value };
        setData(newData);
        onDataChange(newData);
    };

    const handleHistoryChange = (key: string, value: string) => {
        const newHistories = { ...histories, [key]: value };
        setHistories(newHistories);
        onPatientHistoryUpdate(key, value);
    };

    const applyTemplate = (template: MedicalHistoryTemplate) => {
        const newData = {
            ...data,
            presenting_complaint:
                template.presentingComplaint || data.presenting_complaint,
            history_presenting_complaint:
                template.historyPresentingComplaint ||
                data.history_presenting_complaint,
            on_direct_questioning:
                template.onDirectQuestioning || data.on_direct_questioning,
            examination_findings:
                template.examinationFindings || data.examination_findings,
            assessment_notes: template.assessment || data.assessment_notes,
            plan_notes: template.plan || data.plan_notes,
        };
        setData(newData);
        onDataChange(newData);
        setShowTemplates(false);
    };

    const insertQuickPhrase = (phrase: QuickPhrase, fieldKey: string) => {
        const textarea =
            textareaRefs[fieldKey as keyof typeof textareaRefs].current;
        if (!textarea) return;

        const { selectionStart, selectionEnd } = textarea;
        const currentValue = data[fieldKey as keyof typeof data];
        const beforeCursor = currentValue.substring(0, selectionStart);
        const afterCursor = currentValue.substring(selectionEnd);

        const words = beforeCursor.split(' ');
        const lastWord = words[words.length - 1];

        let newValue;
        if (lastWord === phrase.abbreviation) {
            words[words.length - 1] = phrase.expansion;
            newValue = words.join(' ') + afterCursor;
        } else {
            newValue = beforeCursor + phrase.expansion + afterCursor;
        }

        handleDataChange(fieldKey, newValue);

        setTimeout(() => {
            const newPosition = beforeCursor.length + phrase.expansion.length;
            textarea.setSelectionRange(newPosition, newPosition);
            textarea.focus();
        }, 0);
    };

    const handleTextareaKeyDown = (
        e: React.KeyboardEvent,
        fieldKey: string,
    ) => {
        if (e.key === 'Tab') {
            e.preventDefault();
            const textarea = e.currentTarget as HTMLTextAreaElement;
            const { selectionStart } = textarea;
            const currentValue = data[fieldKey as keyof typeof data];
            const beforeCursor = currentValue.substring(0, selectionStart);
            const words = beforeCursor.split(' ');
            const lastWord = words[words.length - 1];

            const matchingPhrase = quickPhrases.find(
                (phrase) => phrase.abbreviation === lastWord,
            );
            if (matchingPhrase) {
                insertQuickPhrase(matchingPhrase, fieldKey);
            }
        }
    };

    const getCompletionColor = (count: number) => {
        if (count === 0) return 'text-gray-400 dark:text-gray-500';
        if (count < 10) return 'text-yellow-600 dark:text-yellow-500';
        if (count < 25) return 'text-blue-600 dark:text-blue-400';
        return 'text-green-600 dark:text-green-500';
    };

    const autoResize = (textarea: HTMLTextAreaElement) => {
        textarea.style.height = 'auto';
        textarea.style.height = Math.max(textarea.scrollHeight, 100) + 'px';
    };

    return (
        <div className="space-y-6">
            {/* Consultation Notes Card with Tabs */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            Consultation Notes
                            <Badge variant="outline" className="ml-2">
                                Smart Assistant
                            </Badge>
                        </CardTitle>
                        <div className="flex items-center gap-2">
                            <Popover
                                open={showTemplates}
                                onOpenChange={setShowTemplates}
                            >
                                <PopoverTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <Lightbulb className="mr-1 h-4 w-4" />
                                        Templates
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-80">
                                    <div className="space-y-3">
                                        <h4 className="text-sm font-medium">
                                            Choose Template
                                        </h4>
                                        {commonTemplates.map((template) => (
                                            <div
                                                key={template.id}
                                                className="cursor-pointer rounded border p-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                                                onClick={() =>
                                                    applyTemplate(template)
                                                }
                                            >
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-medium">
                                                        {template.name}
                                                    </span>
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        {template.category}
                                                    </Badge>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </PopoverContent>
                            </Popover>

                            <Popover
                                open={showPhrases}
                                onOpenChange={setShowPhrases}
                            >
                                <PopoverTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <Sparkles className="mr-1 h-4 w-4" />
                                        Quick Phrases
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-80">
                                    <div className="space-y-3">
                                        <h4 className="text-sm font-medium">
                                            Quick Phrases (Press Tab to expand)
                                        </h4>
                                        <div className="grid grid-cols-2 gap-2 text-xs">
                                            {quickPhrases.map((phrase) => (
                                                <div
                                                    key={phrase.abbreviation}
                                                    className="rounded border p-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                                                    title={phrase.expansion}
                                                >
                                                    <code className="font-mono font-bold">
                                                        {phrase.abbreviation}
                                                    </code>
                                                    <p className="truncate text-gray-600 dark:text-gray-400">
                                                        {phrase.expansion}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <form onSubmit={onSubmit}>
                        <Tabs
                            defaultValue="presenting_complaint"
                            className="w-full"
                        >
                            <TabsList className="grid h-auto w-full grid-cols-5 gap-1 p-1 lg:grid-cols-9">
                                <TabsTrigger
                                    value="presenting_complaint"
                                    className="text-xs"
                                >
                                    PC
                                </TabsTrigger>
                                <TabsTrigger
                                    value="history_presenting_complaint"
                                    className="text-xs"
                                >
                                    HPC
                                </TabsTrigger>
                                <TabsTrigger
                                    value="on_direct_questioning"
                                    className="text-xs"
                                >
                                    ODQ
                                </TabsTrigger>
                                <TabsTrigger
                                    value="past_history"
                                    className="text-xs"
                                >
                                    PMH
                                </TabsTrigger>
                                <TabsTrigger
                                    value="drug_history"
                                    className="text-xs"
                                >
                                    DH
                                </TabsTrigger>
                                <TabsTrigger
                                    value="family_history"
                                    className="text-xs"
                                >
                                    FH
                                </TabsTrigger>
                                <TabsTrigger
                                    value="social_history"
                                    className="text-xs"
                                >
                                    SH
                                </TabsTrigger>
                                <TabsTrigger
                                    value="examination_findings"
                                    className="text-xs"
                                >
                                    Exam
                                </TabsTrigger>
                                <TabsTrigger
                                    value="plan_notes"
                                    className="text-xs"
                                >
                                    Plan
                                </TabsTrigger>
                            </TabsList>

                            {/* Presenting Complaint Tab */}
                            <TabsContent
                                value="presenting_complaint"
                                className="mt-4 space-y-4"
                            >
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-semibold">
                                        Presenting Complaint
                                    </h3>
                                    <div className="flex items-center gap-2 text-xs">
                                        <span
                                            className={getCompletionColor(
                                                completionStats.presenting_complaint,
                                            )}
                                        >
                                            {
                                                completionStats.presenting_complaint
                                            }{' '}
                                            words
                                        </span>
                                        {completionStats.presenting_complaint >
                                            0 && (
                                            <CheckCircle className="h-3 w-3 text-green-500" />
                                        )}
                                    </div>
                                </div>
                                <Textarea
                                    ref={textareaRefs.presenting_complaint}
                                    id="presenting_complaint"
                                    placeholder="Primary reason for today's visit..."
                                    value={data.presenting_complaint}
                                    onChange={(e) => {
                                        handleDataChange(
                                            'presenting_complaint',
                                            e.target.value,
                                        );
                                        autoResize(e.target);
                                    }}
                                    onKeyDown={(e) =>
                                        handleTextareaKeyDown(
                                            e,
                                            'presenting_complaint',
                                        )
                                    }
                                    className="min-h-[200px] resize-none"
                                />
                            </TabsContent>

                            {/* History of Presenting Complaint Tab */}
                            <TabsContent
                                value="history_presenting_complaint"
                                className="mt-4 space-y-4"
                            >
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-semibold">
                                        History of Presenting Complaint
                                    </h3>
                                    <div className="flex items-center gap-2 text-xs">
                                        <span
                                            className={getCompletionColor(
                                                completionStats.history_presenting_complaint,
                                            )}
                                        >
                                            {
                                                completionStats.history_presenting_complaint
                                            }{' '}
                                            words
                                        </span>
                                        {completionStats.history_presenting_complaint >
                                            0 && (
                                            <CheckCircle className="h-3 w-3 text-green-500" />
                                        )}
                                    </div>
                                </div>
                                <Textarea
                                    ref={
                                        textareaRefs.history_presenting_complaint
                                    }
                                    id="history_presenting_complaint"
                                    placeholder="Detailed history of current complaint: onset, duration, character, progression, relieving/aggravating factors..."
                                    value={data.history_presenting_complaint}
                                    onChange={(e) => {
                                        handleDataChange(
                                            'history_presenting_complaint',
                                            e.target.value,
                                        );
                                        autoResize(e.target);
                                    }}
                                    onKeyDown={(e) =>
                                        handleTextareaKeyDown(
                                            e,
                                            'history_presenting_complaint',
                                        )
                                    }
                                    className="min-h-[200px] resize-none"
                                />
                            </TabsContent>

                            {/* On Direct Questioning Tab */}
                            <TabsContent
                                value="on_direct_questioning"
                                className="mt-4 space-y-4"
                            >
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-semibold">
                                        On Direct Questioning
                                    </h3>
                                    <div className="flex items-center gap-2 text-xs">
                                        <span
                                            className={getCompletionColor(
                                                completionStats.on_direct_questioning,
                                            )}
                                        >
                                            {
                                                completionStats.on_direct_questioning
                                            }{' '}
                                            words
                                        </span>
                                        {completionStats.on_direct_questioning >
                                            0 && (
                                            <CheckCircle className="h-3 w-3 text-green-500" />
                                        )}
                                    </div>
                                </div>
                                <Textarea
                                    ref={textareaRefs.on_direct_questioning}
                                    id="on_direct_questioning"
                                    placeholder="Systematic review: associated symptoms, other system reviews..."
                                    value={data.on_direct_questioning}
                                    onChange={(e) => {
                                        handleDataChange(
                                            'on_direct_questioning',
                                            e.target.value,
                                        );
                                        autoResize(e.target);
                                    }}
                                    onKeyDown={(e) =>
                                        handleTextareaKeyDown(
                                            e,
                                            'on_direct_questioning',
                                        )
                                    }
                                    className="min-h-[200px] resize-none"
                                />
                            </TabsContent>

                            {/* Past Medical/Surgical History Tab */}
                            <TabsContent
                                value="past_history"
                                className="mt-4 space-y-4"
                            >
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-semibold">
                                        Past Medical & Surgical History
                                    </h3>
                                </div>
                                <Textarea
                                    id="pmsh"
                                    placeholder="Previous medical conditions, surgeries, hospitalizations..."
                                    value={
                                        histories.past_medical_surgical_history
                                    }
                                    onChange={(e) =>
                                        handleHistoryChange(
                                            'past_medical_surgical_history',
                                            e.target.value,
                                        )
                                    }
                                    className="min-h-[200px] resize-none"
                                />
                            </TabsContent>

                            {/* Drug History Tab */}
                            <TabsContent
                                value="drug_history"
                                className="mt-4 space-y-4"
                            >
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-semibold">
                                        Drug History
                                    </h3>
                                </div>
                                <Textarea
                                    id="drug_history"
                                    placeholder="Current and past medications, drug allergies..."
                                    value={histories.drug_history}
                                    onChange={(e) =>
                                        handleHistoryChange(
                                            'drug_history',
                                            e.target.value,
                                        )
                                    }
                                    className="min-h-[200px] resize-none"
                                />
                            </TabsContent>

                            {/* Family History Tab */}
                            <TabsContent
                                value="family_history"
                                className="mt-4 space-y-4"
                            >
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-semibold">
                                        Family History
                                    </h3>
                                </div>
                                <Textarea
                                    id="family_history"
                                    placeholder="Family medical conditions, hereditary diseases..."
                                    value={histories.family_history}
                                    onChange={(e) =>
                                        handleHistoryChange(
                                            'family_history',
                                            e.target.value,
                                        )
                                    }
                                    className="min-h-[200px] resize-none"
                                />
                            </TabsContent>

                            {/* Social History Tab */}
                            <TabsContent
                                value="social_history"
                                className="mt-4 space-y-4"
                            >
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-semibold">
                                        Social History
                                    </h3>
                                </div>
                                <Textarea
                                    id="social_history"
                                    placeholder="Occupation, smoking, alcohol, living situation..."
                                    value={histories.social_history}
                                    onChange={(e) =>
                                        handleHistoryChange(
                                            'social_history',
                                            e.target.value,
                                        )
                                    }
                                    className="min-h-[200px] resize-none"
                                />
                            </TabsContent>

                            {/* Examination Findings Tab */}
                            <TabsContent
                                value="examination_findings"
                                className="mt-4 space-y-4"
                            >
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-semibold">
                                        Examination Findings
                                    </h3>
                                    <div className="flex items-center gap-2 text-xs">
                                        <span
                                            className={getCompletionColor(
                                                completionStats.examination_findings,
                                            )}
                                        >
                                            {
                                                completionStats.examination_findings
                                            }{' '}
                                            words
                                        </span>
                                        {completionStats.examination_findings >
                                            0 && (
                                            <CheckCircle className="h-3 w-3 text-green-500" />
                                        )}
                                    </div>
                                </div>
                                <Textarea
                                    ref={textareaRefs.examination_findings}
                                    id="examination_findings"
                                    placeholder="Physical examination findings, vital signs, diagnostic results..."
                                    value={data.examination_findings}
                                    onChange={(e) => {
                                        handleDataChange(
                                            'examination_findings',
                                            e.target.value,
                                        );
                                        autoResize(e.target);
                                    }}
                                    onKeyDown={(e) =>
                                        handleTextareaKeyDown(
                                            e,
                                            'examination_findings',
                                        )
                                    }
                                    className="min-h-[200px] resize-none"
                                />
                            </TabsContent>

                            {/* Plan Tab */}
                            <TabsContent
                                value="plan_notes"
                                className="mt-4 space-y-4"
                            >
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-semibold">
                                        Plan
                                    </h3>
                                    <div className="flex items-center gap-2 text-xs">
                                        <span
                                            className={getCompletionColor(
                                                completionStats.plan_notes,
                                            )}
                                        >
                                            {completionStats.plan_notes} words
                                        </span>
                                        {completionStats.plan_notes > 0 && (
                                            <CheckCircle className="h-3 w-3 text-green-500" />
                                        )}
                                    </div>
                                </div>
                                <Textarea
                                    ref={textareaRefs.plan_notes}
                                    id="plan_notes"
                                    placeholder="Treatment plan, medications, investigations, follow-up instructions, patient education..."
                                    value={data.plan_notes}
                                    onChange={(e) => {
                                        handleDataChange(
                                            'plan_notes',
                                            e.target.value,
                                        );
                                        autoResize(e.target);
                                    }}
                                    onKeyDown={(e) =>
                                        handleTextareaKeyDown(e, 'plan_notes')
                                    }
                                    className="min-h-[200px] resize-none"
                                />

                                {/* Follow-up Date */}
                                <div className="border-t pt-4">
                                    <Label htmlFor="follow_up_date">
                                        Follow-up Date (Optional)
                                    </Label>
                                    <Input
                                        id="follow_up_date"
                                        type="date"
                                        value={data.follow_up_date}
                                        onChange={(e) =>
                                            handleDataChange(
                                                'follow_up_date',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 max-w-xs"
                                    />
                                </div>
                            </TabsContent>
                        </Tabs>

                        {status === 'in_progress' && (
                            <Button
                                type="submit"
                                disabled={processing}
                                className="mt-6 w-full"
                            >
                                {processing
                                    ? 'Saving...'
                                    : 'Save Consultation Notes'}
                            </Button>
                        )}
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
