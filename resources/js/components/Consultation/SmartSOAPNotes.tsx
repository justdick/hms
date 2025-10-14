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
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import {
    CheckCircle,
    Clock,
    FileText,
    Lightbulb,
    Sparkles,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface SOAPTemplate {
    id: string;
    name: string;
    category: string;
    chiefComplaint?: string;
    subjective?: string;
    objective?: string;
    assessment?: string;
    plan?: string;
}

interface QuickPhrase {
    abbreviation: string;
    expansion: string;
    category: string;
}

interface SmartSOAPNotesProps {
    initialData: {
        chief_complaint: string;
        subjective_notes: string;
        objective_notes: string;
        assessment_notes: string;
        plan_notes: string;
        follow_up_date: string;
    };
    onDataChange: (data: any) => void;
    onSubmit: (e: React.FormEvent) => void;
    processing: boolean;
    status: string;
    previousNotes?: Array<{
        date: string;
        chief_complaint: string;
        assessment_notes: string;
    }>;
}

const commonTemplates: SOAPTemplate[] = [
    {
        id: 'hypertension-followup',
        name: 'Hypertension Follow-up',
        category: 'Cardiology',
        chiefComplaint: 'Follow-up for hypertension management',
        subjective:
            'Patient reports compliance with antihypertensive medications. No chest pain, shortness of breath, or dizziness. Reports regular home BP monitoring.',
        objective:
            'Vital signs stable. BP improved from last visit. Cardiovascular exam unremarkable.',
        assessment: 'Hypertension, well-controlled on current regimen',
        plan: 'Continue current medications. Recheck BP in 3 months. Lifestyle counseling reinforced.',
    },
    {
        id: 'diabetes-followup',
        name: 'Diabetes Follow-up',
        category: 'Endocrinology',
        chiefComplaint: 'Routine diabetes management',
        subjective:
            'Patient monitoring blood glucose regularly. Diet adherence good. No polyuria, polydipsia, or visual changes.',
        objective:
            'Weight stable. Foot exam normal. No signs of diabetic complications.',
        assessment: 'Type 2 diabetes mellitus, stable',
        plan: 'Continue current diabetic regimen. HbA1c in 3 months. Annual ophthalmology referral.',
    },
    {
        id: 'annual-physical',
        name: 'Annual Physical',
        category: 'Preventive',
        chiefComplaint: 'Annual wellness examination',
        subjective:
            'Patient feels well. No acute complaints. Reviews systems negative.',
        objective: 'Vital signs normal. Physical examination unremarkable.',
        assessment: 'Healthy adult for preventive care',
        plan: 'Age-appropriate screening tests ordered. Immunizations up to date.',
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
];

export default function SmartSOAPNotes({
    initialData,
    onDataChange,
    onSubmit,
    processing,
    status,
    previousNotes = [],
}: SmartSOAPNotesProps) {
    const [data, setData] = useState(initialData);
    const [selectedTemplate, setSelectedTemplate] = useState<string>('');
    const [showTemplates, setShowTemplates] = useState(false);
    const [showPhrases, setShowPhrases] = useState(false);
    const [completionStats, setCompletionStats] = useState({
        chief_complaint: 0,
        subjective_notes: 0,
        objective_notes: 0,
        assessment_notes: 0,
        plan_notes: 0,
    });

    const textareaRefs = {
        chief_complaint: useRef<HTMLTextAreaElement>(null),
        subjective_notes: useRef<HTMLTextAreaElement>(null),
        objective_notes: useRef<HTMLTextAreaElement>(null),
        assessment_notes: useRef<HTMLTextAreaElement>(null),
        plan_notes: useRef<HTMLTextAreaElement>(null),
    };

    useEffect(() => {
        // Update completion stats
        const stats = Object.keys(data).reduce(
            (acc, key) => {
                if (key.includes('notes') || key === 'chief_complaint') {
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

    const applyTemplate = (template: SOAPTemplate) => {
        const newData = {
            ...data,
            chief_complaint: template.chiefComplaint || data.chief_complaint,
            subjective_notes: template.subjective || data.subjective_notes,
            objective_notes: template.objective || data.objective_notes,
            assessment_notes: template.assessment || data.assessment_notes,
            plan_notes: template.plan || data.plan_notes,
        };
        setData(newData);
        onDataChange(newData);
        setSelectedTemplate('');
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

        // Replace the abbreviation if it's at the cursor
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

        // Set cursor position
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

            // Check if last word matches a quick phrase
            const matchingPhrase = quickPhrases.find(
                (phrase) => phrase.abbreviation === lastWord,
            );
            if (matchingPhrase) {
                insertQuickPhrase(matchingPhrase, fieldKey);
            }
        }
    };

    const getCompletionColor = (count: number) => {
        if (count === 0) return 'text-gray-400';
        if (count < 10) return 'text-yellow-600';
        if (count < 25) return 'text-blue-600';
        return 'text-green-600';
    };

    const autoResize = (textarea: HTMLTextAreaElement) => {
        textarea.style.height = 'auto';
        textarea.style.height = Math.max(textarea.scrollHeight, 100) + 'px';
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        SOAP Notes
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
                                            className="cursor-pointer rounded border p-2 transition-colors hover:bg-gray-50"
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
                                                className="rounded border p-2 transition-colors hover:bg-gray-50"
                                                title={phrase.expansion}
                                            >
                                                <code className="font-mono font-bold">
                                                    {phrase.abbreviation}
                                                </code>
                                                <p className="truncate text-gray-600">
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
                <form onSubmit={onSubmit} className="space-y-6">
                    {/* Chief Complaint */}
                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <Label htmlFor="chief_complaint">
                                Chief Complaint
                            </Label>
                            <div className="flex items-center gap-2 text-xs">
                                <span
                                    className={getCompletionColor(
                                        completionStats.chief_complaint,
                                    )}
                                >
                                    {completionStats.chief_complaint} words
                                </span>
                                {completionStats.chief_complaint > 0 && (
                                    <CheckCircle className="h-3 w-3 text-green-500" />
                                )}
                            </div>
                        </div>
                        <Textarea
                            ref={textareaRefs.chief_complaint}
                            id="chief_complaint"
                            placeholder="Primary reason for the patient's visit..."
                            value={data.chief_complaint}
                            onChange={(e) => {
                                handleDataChange(
                                    'chief_complaint',
                                    e.target.value,
                                );
                                autoResize(e.target);
                            }}
                            onKeyDown={(e) =>
                                handleTextareaKeyDown(e, 'chief_complaint')
                            }
                            className="min-h-[60px] resize-none"
                            style={{ height: 'auto' }}
                        />
                    </div>

                    <Separator />

                    {/* Subjective */}
                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <Label htmlFor="subjective_notes">
                                Subjective (S)
                            </Label>
                            <div className="flex items-center gap-2 text-xs">
                                <span
                                    className={getCompletionColor(
                                        completionStats.subjective_notes,
                                    )}
                                >
                                    {completionStats.subjective_notes} words
                                </span>
                                {completionStats.subjective_notes > 0 && (
                                    <CheckCircle className="h-3 w-3 text-green-500" />
                                )}
                            </div>
                        </div>
                        <Textarea
                            ref={textareaRefs.subjective_notes}
                            id="subjective_notes"
                            placeholder="Patient's description of symptoms, history, review of systems..."
                            value={data.subjective_notes}
                            onChange={(e) => {
                                handleDataChange(
                                    'subjective_notes',
                                    e.target.value,
                                );
                                autoResize(e.target);
                            }}
                            onKeyDown={(e) =>
                                handleTextareaKeyDown(e, 'subjective_notes')
                            }
                            className="min-h-[100px] resize-none"
                        />
                    </div>

                    {/* Objective */}
                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <Label htmlFor="objective_notes">
                                Objective (O)
                            </Label>
                            <div className="flex items-center gap-2 text-xs">
                                <span
                                    className={getCompletionColor(
                                        completionStats.objective_notes,
                                    )}
                                >
                                    {completionStats.objective_notes} words
                                </span>
                                {completionStats.objective_notes > 0 && (
                                    <CheckCircle className="h-3 w-3 text-green-500" />
                                )}
                            </div>
                        </div>
                        <Textarea
                            ref={textareaRefs.objective_notes}
                            id="objective_notes"
                            placeholder="Physical examination findings, vital signs, diagnostic results..."
                            value={data.objective_notes}
                            onChange={(e) => {
                                handleDataChange(
                                    'objective_notes',
                                    e.target.value,
                                );
                                autoResize(e.target);
                            }}
                            onKeyDown={(e) =>
                                handleTextareaKeyDown(e, 'objective_notes')
                            }
                            className="min-h-[100px] resize-none"
                        />
                    </div>

                    {/* Assessment */}
                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <Label htmlFor="assessment_notes">
                                Assessment (A)
                            </Label>
                            <div className="flex items-center gap-2 text-xs">
                                <span
                                    className={getCompletionColor(
                                        completionStats.assessment_notes,
                                    )}
                                >
                                    {completionStats.assessment_notes} words
                                </span>
                                {completionStats.assessment_notes > 0 && (
                                    <CheckCircle className="h-3 w-3 text-green-500" />
                                )}
                            </div>
                        </div>
                        <Textarea
                            ref={textareaRefs.assessment_notes}
                            id="assessment_notes"
                            placeholder="Clinical judgment, differential diagnosis, impression..."
                            value={data.assessment_notes}
                            onChange={(e) => {
                                handleDataChange(
                                    'assessment_notes',
                                    e.target.value,
                                );
                                autoResize(e.target);
                            }}
                            onKeyDown={(e) =>
                                handleTextareaKeyDown(e, 'assessment_notes')
                            }
                            className="min-h-[80px] resize-none"
                        />
                    </div>

                    {/* Plan */}
                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <Label htmlFor="plan_notes">Plan (P)</Label>
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
                            placeholder="Treatment plan, medications, follow-up instructions, patient education..."
                            value={data.plan_notes}
                            onChange={(e) => {
                                handleDataChange('plan_notes', e.target.value);
                                autoResize(e.target);
                            }}
                            onKeyDown={(e) =>
                                handleTextareaKeyDown(e, 'plan_notes')
                            }
                            className="min-h-[100px] resize-none"
                        />
                    </div>

                    {/* Follow-up Date */}
                    <div>
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

                    {/* Previous Notes Reference */}
                    {previousNotes.length > 0 && (
                        <div className="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <h4 className="mb-3 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <Clock className="h-4 w-4" />
                                Previous Notes Reference
                            </h4>
                            <div className="max-h-32 space-y-2 overflow-y-auto">
                                {previousNotes
                                    .slice(0, 3)
                                    .map((note, index) => (
                                        <div
                                            key={index}
                                            className="rounded border bg-white p-2 text-xs dark:bg-gray-700"
                                        >
                                            <div className="mb-1 font-medium text-gray-600 dark:text-gray-400">
                                                {note.date}
                                            </div>
                                            <div className="text-gray-700 dark:text-gray-300">
                                                CC: {note.chief_complaint}
                                            </div>
                                            {note.assessment_notes && (
                                                <div className="mt-1 text-gray-600 dark:text-gray-400">
                                                    A:{' '}
                                                    {note.assessment_notes.substring(
                                                        0,
                                                        100,
                                                    )}
                                                    ...
                                                </div>
                                            )}
                                        </div>
                                    ))}
                            </div>
                        </div>
                    )}

                    {status === 'in_progress' && (
                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full"
                        >
                            {processing ? 'Saving...' : 'Save Notes'}
                        </Button>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}
