import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ClipboardList } from 'lucide-react';

interface Doctor {
    id: number;
    name: string;
}

interface ConsultationNote {
    id: number;
    date: string;
    doctor: Doctor;
    presenting_complaint?: string;
    history_presenting_complaint?: string;
    on_direct_questioning?: string;
    examination_findings?: string;
    assessment_notes?: string;
    plan_notes?: string;
}

interface Props {
    notes: ConsultationNote[];
}

export function HistoricalNotesAccordion({ notes }: Props) {
    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const hasContent = (note: ConsultationNote) => {
        return !!(
            note.presenting_complaint ||
            note.history_presenting_complaint ||
            note.on_direct_questioning ||
            note.examination_findings ||
            note.assessment_notes ||
            note.plan_notes
        );
    };

    if (notes.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <ClipboardList className="h-5 w-5" />
                        Consultation Notes History During Admission
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                        <ClipboardList className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                        <p>
                            No consultation notes recorded yet for this
                            admission
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ClipboardList className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                    Consultation Notes History During Admission
                </CardTitle>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {notes.length} consultation(s) with notes during this
                    admission
                </p>
            </CardHeader>
            <CardContent>
                <Accordion type="single" collapsible className="w-full">
                    {notes.map((note, index) => (
                        <AccordionItem key={note.id} value={`item-${note.id}`}>
                            <AccordionTrigger className="hover:no-underline">
                                <div className="flex w-full items-center justify-between pr-4">
                                    <div className="flex items-center gap-3">
                                        <Badge variant="outline">
                                            {index === 0
                                                ? 'Initial'
                                                : `Ward Round #${index}`}
                                        </Badge>
                                        <span className="font-medium">
                                            {formatDateTime(note.date)}
                                        </span>
                                        <span className="text-sm text-gray-500 dark:text-gray-400">
                                            by {note.doctor.name}
                                        </span>
                                    </div>
                                </div>
                            </AccordionTrigger>
                            <AccordionContent>
                                <div className="space-y-4 pt-4">
                                    {!hasContent(note) ? (
                                        <p className="text-sm text-gray-500 italic dark:text-gray-400">
                                            No detailed notes recorded for this
                                            consultation
                                        </p>
                                    ) : (
                                        <>
                                            {note.presenting_complaint && (
                                                <div>
                                                    <h4 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                        Presenting Complaint
                                                    </h4>
                                                    <p className="rounded-lg bg-gray-50 p-3 text-sm text-gray-900 dark:bg-gray-800 dark:text-gray-100">
                                                        {
                                                            note.presenting_complaint
                                                        }
                                                    </p>
                                                </div>
                                            )}

                                            {note.history_presenting_complaint && (
                                                <div>
                                                    <h4 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                        History of Presenting
                                                        Complaint
                                                    </h4>
                                                    <p className="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-900 dark:bg-gray-800 dark:text-gray-100">
                                                        {
                                                            note.history_presenting_complaint
                                                        }
                                                    </p>
                                                </div>
                                            )}

                                            {note.on_direct_questioning && (
                                                <div>
                                                    <h4 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                        On Direct Questioning
                                                        (ODQ)
                                                    </h4>
                                                    <p className="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-900 dark:bg-gray-800 dark:text-gray-100">
                                                        {
                                                            note.on_direct_questioning
                                                        }
                                                    </p>
                                                </div>
                                            )}

                                            {note.examination_findings && (
                                                <div>
                                                    <h4 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                        Examination Findings
                                                    </h4>
                                                    <p className="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-900 dark:bg-gray-800 dark:text-gray-100">
                                                        {
                                                            note.examination_findings
                                                        }
                                                    </p>
                                                </div>
                                            )}

                                            {note.assessment_notes && (
                                                <div>
                                                    <h4 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                        Assessment
                                                    </h4>
                                                    <p className="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-900 dark:bg-gray-800 dark:text-gray-100">
                                                        {note.assessment_notes}
                                                    </p>
                                                </div>
                                            )}

                                            {note.plan_notes && (
                                                <div>
                                                    <h4 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                        Plan
                                                    </h4>
                                                    <p className="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-900 dark:bg-gray-800 dark:text-gray-100">
                                                        {note.plan_notes}
                                                    </p>
                                                </div>
                                            )}
                                        </>
                                    )}
                                </div>
                            </AccordionContent>
                        </AccordionItem>
                    ))}
                </Accordion>
            </CardContent>
        </Card>
    );
}
