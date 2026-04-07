'use client';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { router } from '@inertiajs/react';
import { differenceInHours, format, isToday } from 'date-fns';
import {
    ChevronDown,
    ChevronRight,
    Pill,
    Search,
    Trash2,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

interface Drug {
    id: number;
    name: string;
    strength?: string;
}

interface Prescription {
    id: number;
    drug?: Drug;
    medication_name: string;
    dose_quantity?: string;
    frequency?: string;
    instructions?: string;
}

interface User {
    id: number;
    name: string;
}

export interface MedicationAdministration {
    id: number;
    prescription_id: number;
    administered_at: string;
    status: 'given' | 'held' | 'refused' | 'omitted';
    administered_by?: User;
    notes?: string;
    dosage_given?: string;
    route?: string;
}

interface MARTableProps {
    administrations: MedicationAdministration[];
    prescriptions: Prescription[];
    admissionId: number;
    canDelete?: boolean;
    canDeleteOld?: boolean;
}

interface MedicationGroup {
    prescriptionId: number;
    drugName: string;
    details: string;
    frequency?: string;
    administrations: MedicationAdministration[];
}

const statusConfig: Record<
    string,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
        className?: string;
    }
> = {
    given: {
        label: 'Given',
        variant: 'default',
        className: 'bg-green-600 hover:bg-green-700',
    },
    held: { label: 'Held', variant: 'secondary' },
    refused: { label: 'Refused', variant: 'destructive' },
    omitted: { label: 'Omitted', variant: 'outline' },
};

export function MARTable({
    administrations,
    prescriptions,
    admissionId,
    canDelete = false,
    canDeleteOld = false,
}: MARTableProps) {
    const [globalFilter, setGlobalFilter] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<string>('all');
    const [collapsedGroups, setCollapsedGroups] = React.useState<Set<number>>(
        new Set(),
    );
    const [deleteConfirmOpen, setDeleteConfirmOpen] = React.useState(false);
    const [deletingRecord, setDeletingRecord] =
        React.useState<MedicationAdministration | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    const getPrescription = (prescriptionId: number) => {
        return prescriptions.find((p) => p.id === prescriptionId);
    };

    const canDeleteRecord = (record: MedicationAdministration) => {
        if (!canDelete && !canDeleteOld) return false;
        const administeredAt = new Date(record.administered_at);
        const hoursSince = differenceInHours(new Date(), administeredAt);
        const isOld = hoursSince >= 72;
        if (isOld) return canDeleteOld;
        return canDelete;
    };

    const handleDeleteClick = (record: MedicationAdministration) => {
        setDeletingRecord(record);
        setDeleteConfirmOpen(true);
    };

    const handleConfirmDelete = () => {
        if (!deletingRecord) return;

        setIsDeleting(true);
        router.delete(
            `/admissions/${admissionId}/medications/${deletingRecord.id}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Medication administration record deleted');
                    setDeleteConfirmOpen(false);
                    setDeletingRecord(null);
                },
                onError: (errors: any) => {
                    const errorMessage =
                        errors.medication || 'Failed to delete record';
                    toast.error(errorMessage);
                },
                onFinish: () => {
                    setIsDeleting(false);
                },
            },
        );
    };

    const toggleGroup = (prescriptionId: number) => {
        setCollapsedGroups((prev) => {
            const next = new Set(prev);
            if (next.has(prescriptionId)) {
                next.delete(prescriptionId);
            } else {
                next.add(prescriptionId);
            }
            return next;
        });
    };

    // Group administrations by prescription (drug), apply filters
    const medicationGroups = React.useMemo(() => {
        // Apply status filter
        let filtered = administrations;
        if (statusFilter !== 'all') {
            filtered = filtered.filter((a) => a.status === statusFilter);
        }

        // Group by prescription_id
        const groupMap = new Map<number, MedicationAdministration[]>();
        for (const admin of filtered) {
            const existing = groupMap.get(admin.prescription_id) || [];
            existing.push(admin);
            groupMap.set(admin.prescription_id, existing);
        }

        // Build groups with prescription details
        const groups: MedicationGroup[] = [];
        for (const [prescriptionId, admins] of groupMap) {
            const prescription = getPrescription(prescriptionId);
            const drugName =
                prescription?.drug?.name ||
                prescription?.medication_name ||
                'Unknown Medication';

            // Build details string like reference: "Drug Name, Strength [DOSE FREQUENCY]"
            const parts: string[] = [];
            if (prescription?.drug?.strength) {
                parts.push(prescription.drug.strength);
            }
            const bracketParts: string[] = [];
            if (prescription?.dose_quantity) {
                bracketParts.push(prescription.dose_quantity);
            }
            if (prescription?.frequency) {
                bracketParts.push(prescription.frequency);
            }
            let details = drugName;
            if (parts.length > 0) {
                details += ', ' + parts.join(' ');
            }
            if (bracketParts.length > 0) {
                details += ' [' + bracketParts.join(' ') + ']';
            }

            // Sort administrations within group by date descending
            admins.sort(
                (a, b) =>
                    new Date(b.administered_at).getTime() -
                    new Date(a.administered_at).getTime(),
            );

            groups.push({
                prescriptionId,
                drugName,
                details,
                frequency: prescription?.frequency,
                administrations: admins,
            });
        }

        // Apply search filter on drug name
        const searchLower = globalFilter.toLowerCase();
        const filteredGroups = searchLower
            ? groups.filter(
                  (g) =>
                      g.drugName.toLowerCase().includes(searchLower) ||
                      g.details.toLowerCase().includes(searchLower),
              )
            : groups;

        // Sort groups alphabetically by drug name
        filteredGroups.sort((a, b) => a.drugName.localeCompare(b.drugName));

        return filteredGroups;
    }, [administrations, prescriptions, statusFilter, globalFilter]);

    const totalRecords = medicationGroups.reduce(
        (sum, g) => sum + g.administrations.length,
        0,
    );

    return (
        <div className="space-y-4">
            {/* Filters */}
            <div className="flex items-center gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search medications..."
                        value={globalFilter}
                        onChange={(e) => setGlobalFilter(e.target.value)}
                        className="pl-10"
                    />
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            Status:{' '}
                            {statusFilter === 'all'
                                ? 'All'
                                : statusConfig[statusFilter]?.label}
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>Filter by Status</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            checked={statusFilter === 'all'}
                            onCheckedChange={() => setStatusFilter('all')}
                        >
                            All
                        </DropdownMenuCheckboxItem>
                        {Object.entries(statusConfig).map(([key, config]) => (
                            <DropdownMenuCheckboxItem
                                key={key}
                                checked={statusFilter === key}
                                onCheckedChange={() => setStatusFilter(key)}
                            >
                                {config.label}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                <div className="text-sm text-muted-foreground">
                    {medicationGroups.length} medication(s), {totalRecords}{' '}
                    record(s)
                </div>
            </div>

            {/* Grouped by Medication */}
            {medicationGroups.length > 0 ? (
                <div className="space-y-4">
                    {medicationGroups.map((group) => {
                        const isCollapsed = collapsedGroups.has(
                            group.prescriptionId,
                        );
                        return (
                            <div
                                key={group.prescriptionId}
                                className="overflow-hidden rounded-lg border"
                            >
                                {/* Drug Header */}
                                <button
                                    type="button"
                                    onClick={() =>
                                        toggleGroup(group.prescriptionId)
                                    }
                                    className="flex w-full items-center gap-2 bg-muted/50 px-4 py-3 text-left transition-colors hover:bg-muted"
                                >
                                    {isCollapsed ? (
                                        <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    ) : (
                                        <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    )}
                                    <Pill className="h-4 w-4 shrink-0 text-primary" />
                                    <span className="font-semibold text-foreground">
                                        {group.details}
                                    </span>
                                    <Badge
                                        variant="secondary"
                                        className="ml-auto"
                                    >
                                        {group.administrations.length} record
                                        {group.administrations.length !== 1
                                            ? 's'
                                            : ''}
                                    </Badge>
                                </button>

                                {/* Administration Records Table */}
                                {!isCollapsed && (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-[160px]">
                                                    Date
                                                </TableHead>
                                                <TableHead className="w-[100px]">
                                                    Time
                                                </TableHead>
                                                <TableHead className="w-[120px]">
                                                    Dose
                                                </TableHead>
                                                <TableHead className="w-[100px]">
                                                    Route
                                                </TableHead>
                                                <TableHead className="w-[100px]">
                                                    Status
                                                </TableHead>
                                                <TableHead>Given By</TableHead>
                                                <TableHead>Notes</TableHead>
                                                {(canDelete ||
                                                    canDeleteOld) && (
                                                    <TableHead className="w-[50px]" />
                                                )}
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {group.administrations.map(
                                                (admin) => {
                                                    const date = new Date(
                                                        admin.administered_at,
                                                    );
                                                    const dateIsToday =
                                                        isToday(date);
                                                    const status =
                                                        admin.status;
                                                    const config =
                                                        statusConfig[status] ||
                                                        statusConfig.given;
                                                    const deletable =
                                                        canDeleteRecord(admin);

                                                    return (
                                                        <TableRow
                                                            key={admin.id}
                                                        >
                                                            <TableCell className="font-medium">
                                                                {dateIsToday
                                                                    ? 'Today'
                                                                    : format(
                                                                          date,
                                                                          'MMM d, yyyy',
                                                                      )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {format(
                                                                    date,
                                                                    'h:mm a',
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {admin.dosage_given || (
                                                                    <span className="text-muted-foreground">
                                                                        -
                                                                    </span>
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="capitalize">
                                                                {admin.route || (
                                                                    <span className="text-muted-foreground">
                                                                        -
                                                                    </span>
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge
                                                                    variant={
                                                                        config.variant
                                                                    }
                                                                    className={
                                                                        config.className
                                                                    }
                                                                >
                                                                    {
                                                                        config.label
                                                                    }
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="text-sm text-muted-foreground">
                                                                {admin
                                                                    .administered_by
                                                                    ?.name ||
                                                                    'Unknown'}
                                                            </TableCell>
                                                            <TableCell>
                                                                {admin.notes ? (
                                                                    <span
                                                                        className="max-w-[200px] truncate text-sm"
                                                                        title={
                                                                            admin.notes
                                                                        }
                                                                    >
                                                                        {
                                                                            admin.notes
                                                                        }
                                                                    </span>
                                                                ) : (
                                                                    <span className="text-muted-foreground">
                                                                        -
                                                                    </span>
                                                                )}
                                                            </TableCell>
                                                            {(canDelete ||
                                                                canDeleteOld) && (
                                                                <TableCell>
                                                                    {deletable && (
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            className="h-8 w-8 p-0 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950"
                                                                            onClick={() =>
                                                                                handleDeleteClick(
                                                                                    admin,
                                                                                )
                                                                            }
                                                                            title="Delete record"
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                        </Button>
                                                                    )}
                                                                </TableCell>
                                                            )}
                                                        </TableRow>
                                                    );
                                                },
                                            )}
                                        </TableBody>
                                    </Table>
                                )}
                            </div>
                        );
                    })}
                </div>
            ) : (
                <div className="rounded-md border py-12 text-center">
                    <div className="flex flex-col items-center gap-2">
                        <Pill className="h-8 w-8 text-muted-foreground" />
                        <div>No medication administrations found</div>
                        <div className="text-sm text-muted-foreground">
                            Click "Record Medication" to log administrations
                        </div>
                    </div>
                </div>
            )}

            {/* Delete Confirmation Dialog */}
            <AlertDialog
                open={deleteConfirmOpen}
                onOpenChange={setDeleteConfirmOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Delete Medication Record
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete this medication
                            administration record?
                            {deletingRecord && (
                                <span className="mt-2 block font-medium text-foreground">
                                    {getPrescription(
                                        deletingRecord.prescription_id,
                                    )?.drug?.name ||
                                        getPrescription(
                                            deletingRecord.prescription_id,
                                        )?.medication_name ||
                                        'Unknown medication'}{' '}
                                    -{' '}
                                    {statusConfig[deletingRecord.status]?.label}
                                </span>
                            )}
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isDeleting}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmDelete}
                            disabled={isDeleting}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {isDeleting ? 'Deleting...' : 'Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
