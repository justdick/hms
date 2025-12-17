import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { CheckCircle, FileText } from 'lucide-react';
import { useEffect, useState } from 'react';

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

    const [completionStats, setCompletionStats] = useState({
        presenting_complaint: 0,
        history_presenting_complaint: 0,
        on_direct_questioning: 0,
        examination_findings: 0,
        assessment_notes: 0,
        plan_notes: 0,
    });

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
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Consultation Notes
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={onSubmit}>
                        <Tabs
                            defaultValue="presenting_complaint"
                            className="w-full"
                        >
                            <TabsList className="grid h-auto w-full grid-cols-5 gap-1 rounded-none border-b border-gray-200 bg-transparent p-1 lg:grid-cols-9 dark:border-gray-700">
                                <TabsTrigger
                                    value="presenting_complaint"
                                    className="rounded-md border-b-2 border-transparent bg-blue-50 text-xs text-blue-700 shadow-none transition-all hover:bg-blue-100 data-[state=active]:border-blue-600 data-[state=active]:bg-blue-100 data-[state=active]:text-blue-700 data-[state=active]:shadow-none dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900 dark:data-[state=active]:border-blue-400 dark:data-[state=active]:bg-blue-900 dark:data-[state=active]:text-blue-300"
                                >
                                    PC
                                </TabsTrigger>
                                <TabsTrigger
                                    value="history_presenting_complaint"
                                    className="rounded-md border-b-2 border-transparent bg-teal-50 text-xs text-teal-700 shadow-none transition-all hover:bg-teal-100 data-[state=active]:border-teal-600 data-[state=active]:bg-teal-100 data-[state=active]:text-teal-700 data-[state=active]:shadow-none dark:bg-teal-950 dark:text-teal-300 dark:hover:bg-teal-900 dark:data-[state=active]:border-teal-400 dark:data-[state=active]:bg-teal-900 dark:data-[state=active]:text-teal-300"
                                >
                                    HPC
                                </TabsTrigger>
                                <TabsTrigger
                                    value="on_direct_questioning"
                                    className="rounded-md border-b-2 border-transparent bg-cyan-50 text-xs text-cyan-700 shadow-none transition-all hover:bg-cyan-100 data-[state=active]:border-cyan-600 data-[state=active]:bg-cyan-100 data-[state=active]:text-cyan-700 data-[state=active]:shadow-none dark:bg-cyan-950 dark:text-cyan-300 dark:hover:bg-cyan-900 dark:data-[state=active]:border-cyan-400 dark:data-[state=active]:bg-cyan-900 dark:data-[state=active]:text-cyan-300"
                                >
                                    ODQ
                                </TabsTrigger>
                                <TabsTrigger
                                    value="past_history"
                                    className="rounded-md border-b-2 border-transparent bg-violet-50 text-xs text-violet-700 shadow-none transition-all hover:bg-violet-100 data-[state=active]:border-violet-600 data-[state=active]:bg-violet-100 data-[state=active]:text-violet-700 data-[state=active]:shadow-none dark:bg-violet-950 dark:text-violet-300 dark:hover:bg-violet-900 dark:data-[state=active]:border-violet-400 dark:data-[state=active]:bg-violet-900 dark:data-[state=active]:text-violet-300"
                                >
                                    PMH
                                </TabsTrigger>
                                <TabsTrigger
                                    value="drug_history"
                                    className="rounded-md border-b-2 border-transparent bg-amber-50 text-xs text-amber-700 shadow-none transition-all hover:bg-amber-100 data-[state=active]:border-amber-600 data-[state=active]:bg-amber-100 data-[state=active]:text-amber-700 data-[state=active]:shadow-none dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900 dark:data-[state=active]:border-amber-400 dark:data-[state=active]:bg-amber-900 dark:data-[state=active]:text-amber-300"
                                >
                                    DH
                                </TabsTrigger>
                                <TabsTrigger
                                    value="family_history"
                                    className="rounded-md border-b-2 border-transparent bg-rose-50 text-xs text-rose-700 shadow-none transition-all hover:bg-rose-100 data-[state=active]:border-rose-600 data-[state=active]:bg-rose-100 data-[state=active]:text-rose-700 data-[state=active]:shadow-none dark:bg-rose-950 dark:text-rose-300 dark:hover:bg-rose-900 dark:data-[state=active]:border-rose-400 dark:data-[state=active]:bg-rose-900 dark:data-[state=active]:text-rose-300"
                                >
                                    FH
                                </TabsTrigger>
                                <TabsTrigger
                                    value="social_history"
                                    className="rounded-md border-b-2 border-transparent bg-orange-50 text-xs text-orange-700 shadow-none transition-all hover:bg-orange-100 data-[state=active]:border-orange-600 data-[state=active]:bg-orange-100 data-[state=active]:text-orange-700 data-[state=active]:shadow-none dark:bg-orange-950 dark:text-orange-300 dark:hover:bg-orange-900 dark:data-[state=active]:border-orange-400 dark:data-[state=active]:bg-orange-900 dark:data-[state=active]:text-orange-300"
                                >
                                    SH
                                </TabsTrigger>
                                <TabsTrigger
                                    value="examination_findings"
                                    className="rounded-md border-b-2 border-transparent bg-green-50 text-xs text-green-700 shadow-none transition-all hover:bg-green-100 data-[state=active]:border-green-600 data-[state=active]:bg-green-100 data-[state=active]:text-green-700 data-[state=active]:shadow-none dark:bg-green-950 dark:text-green-300 dark:hover:bg-green-900 dark:data-[state=active]:border-green-400 dark:data-[state=active]:bg-green-900 dark:data-[state=active]:text-green-300"
                                >
                                    Exam
                                </TabsTrigger>
                                <TabsTrigger
                                    value="plan_notes"
                                    className="rounded-md border-b-2 border-transparent bg-indigo-50 text-xs text-indigo-700 shadow-none transition-all hover:bg-indigo-100 data-[state=active]:border-indigo-600 data-[state=active]:bg-indigo-100 data-[state=active]:text-indigo-700 data-[state=active]:shadow-none dark:bg-indigo-950 dark:text-indigo-300 dark:hover:bg-indigo-900 dark:data-[state=active]:border-indigo-400 dark:data-[state=active]:bg-indigo-900 dark:data-[state=active]:text-indigo-300"
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
                                    className="min-h-[120px] resize-none"
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
                                    className="min-h-[120px] resize-none"
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
                                    className="min-h-[120px] resize-none"
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
                                    className="min-h-[120px] resize-none"
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
                                    className="min-h-[120px] resize-none"
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
                                    className="min-h-[120px] resize-none"
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
                                    className="min-h-[120px] resize-none"
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
                                    className="min-h-[120px] resize-none"
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
                                    className="min-h-[120px] resize-none"
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
