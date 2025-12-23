import { Link } from '@inertiajs/react';
import { ArrowRight, FlaskConical } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export interface PendingLabResultItem {
    id: number;
    patient_name: string;
    patient_number: string;
    test_name: string;
    ordered_at: string | null;
    result_entered_at: string | null;
    priority: string;
}

export interface PendingLabResultsProps {
    results: PendingLabResultItem[];
    viewAllHref?: string;
    className?: string;
}

const priorityVariants: Record<
    string,
    {
        label: string;
        variant: 'default' | 'secondary' | 'outline' | 'destructive';
    }
> = {
    stat: { label: 'STAT', variant: 'destructive' },
    urgent: { label: 'Urgent', variant: 'destructive' },
    routine: { label: 'Routine', variant: 'outline' },
};

function getPriorityBadge(priority: string) {
    const config = priorityVariants[priority] || {
        label: priority,
        variant: 'outline' as const,
    };
    return (
        <Badge variant={config.variant} className="whitespace-nowrap">
            {config.label}
        </Badge>
    );
}

export function PendingLabResults({
    results,
    viewAllHref,
    className,
}: PendingLabResultsProps) {
    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div className="flex items-center gap-2">
                    <FlaskConical className="h-5 w-5 text-muted-foreground" />
                    <div>
                        <CardTitle className="text-base font-semibold">
                            Pending Lab Results
                        </CardTitle>
                        <CardDescription>
                            Results awaiting review
                        </CardDescription>
                    </div>
                </div>
                {viewAllHref && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={viewAllHref}>
                            View All
                            <ArrowRight className="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {results.length === 0 ? (
                    <div className="flex items-center justify-center py-8 text-muted-foreground">
                        No pending lab results
                    </div>
                ) : (
                    <div className="-mx-6 overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Patient</TableHead>
                                    <TableHead>Test</TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Priority
                                    </TableHead>
                                    <TableHead className="hidden text-right md:table-cell">
                                        Result Date
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {results.map((result) => (
                                    <TableRow key={result.id}>
                                        <TableCell>
                                            <div className="flex flex-col">
                                                <span className="font-medium">
                                                    {result.patient_name}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {result.patient_number}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <span className="font-medium">
                                                {result.test_name}
                                            </span>
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            {getPriorityBadge(result.priority)}
                                        </TableCell>
                                        <TableCell className="hidden text-right text-muted-foreground md:table-cell">
                                            {result.result_entered_at || '-'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
