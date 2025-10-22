'use client';

import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDown,
    Calendar,
    ChevronDown,
    ChevronRight,
    Eye,
    User,
} from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    AlertCircle,
    CheckCircle,
    FileText,
    TestTube,
    Timer,
} from 'lucide-react';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    phone_number?: string;
}

interface Test {
    id: number;
    name: string;
    code: string;
    category: string;
    status:
        | 'ordered'
        | 'sample_collected'
        | 'in_progress'
        | 'completed'
        | 'cancelled';
    priority: 'routine' | 'urgent' | 'stat';
}

export interface GroupedConsultation {
    orderable_type: 'consultation' | 'ward_round';
    orderable_id: number;
    consultation_id?: number; // For backward compatibility
    patient: Patient;
    patient_number: string;
    context: string; // Presenting complaint or ward round context
    chief_complaint?: string; // For backward compatibility
    ordered_at: string;
    test_count: number;
    tests: Test[];
    status_summary: Record<string, number>;
    priority: 'routine' | 'urgent' | 'stat';
    ordered_by: string;
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

const ExpandableTestRow = ({ tests }: { tests: Test[] }) => {
    const [isExpanded, setIsExpanded] = useState(false);

    return (
        <div className="space-y-2">
            <button
                onClick={() => setIsExpanded(!isExpanded)}
                className="flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                {isExpanded ? (
                    <ChevronDown className="h-4 w-4" />
                ) : (
                    <ChevronRight className="h-4 w-4" />
                )}
                <span>
                    {tests.length} test{tests.length !== 1 ? 's' : ''}
                </span>
            </button>

            {isExpanded && (
                <div className="ml-6 space-y-1 border-l-2 border-muted pl-3">
                    {tests.map((test) => {
                        const config = statusConfig[test.status];
                        const Icon = config.icon;
                        return (
                            <div
                                key={test.id}
                                className="flex items-center justify-between gap-2 py-1"
                            >
                                <div className="flex flex-1 items-center gap-2">
                                    <code className="rounded bg-muted px-1 text-xs">
                                        {test.code}
                                    </code>
                                    <span className="text-sm">{test.name}</span>
                                </div>
                                <Badge
                                    className={config.className}
                                    variant="outline"
                                >
                                    <Icon className="mr-1 h-3 w-3" />
                                    {config.label}
                                </Badge>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
};

export const groupedConsultationColumns: ColumnDef<GroupedConsultation>[] = [
    {
        id: 'patient',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Patient
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const patient = row.original.patient;
            return (
                <div className="space-y-1">
                    <div className="font-medium">
                        {patient.first_name} {patient.last_name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        #{patient.patient_number}
                    </div>
                    {patient.phone_number && (
                        <div className="flex items-center gap-1 text-sm text-muted-foreground">
                            <User className="h-3 w-3" />
                            {patient.phone_number}
                        </div>
                    )}
                </div>
            );
        },
        sortingFn: (rowA, rowB) => {
            const patientA = rowA.original.patient;
            const patientB = rowB.original.patient;
            const nameA = `${patientA.first_name} ${patientA.last_name}`;
            const nameB = `${patientB.first_name} ${patientB.last_name}`;
            return nameA.localeCompare(nameB);
        },
    },
    {
        id: 'tests',
        header: 'Tests',
        cell: ({ row }) => {
            return <ExpandableTestRow tests={row.original.tests} />;
        },
    },
    {
        id: 'status_summary',
        header: 'Status',
        cell: ({ row }) => {
            const summary = row.original.status_summary;
            const entries = Object.entries(summary).filter(
                ([_, count]) => count > 0,
            );

            return (
                <div className="flex flex-wrap gap-1">
                    {entries.map(([status, count]) => {
                        const config =
                            statusConfig[status as keyof typeof statusConfig];
                        if (!config) return null;
                        const Icon = config.icon;

                        return (
                            <Badge
                                key={status}
                                className={config.className}
                                variant="outline"
                            >
                                <Icon className="mr-1 h-3 w-3" />
                                {count} {config.label}
                            </Badge>
                        );
                    })}
                </div>
            );
        },
    },
    {
        accessorKey: 'priority',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Priority
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const priority = row.getValue(
                'priority',
            ) as GroupedConsultation['priority'];
            const config = priorityConfig[priority];

            return (
                <Badge variant="outline" className={config.className}>
                    {config.label}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
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
        id: 'ordered_by',
        header: 'Ordered By',
        cell: ({ row }) => {
            return <div className="text-sm">{row.original.ordered_by}</div>;
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const order = row.original;
            // Link to the orderable-specific lab orders view
            const href =
                order.orderable_type === 'consultation'
                    ? `/lab/consultations/${order.orderable_id}`
                    : `/lab/ward-rounds/${order.orderable_id}`;

            return (
                <Button size="sm" asChild>
                    <Link href={href}>
                        <Eye className="mr-1 h-3 w-3" />
                        View All Tests
                    </Link>
                </Button>
            );
        },
    },
];
