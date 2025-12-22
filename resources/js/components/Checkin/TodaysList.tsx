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
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
    age: number;
    gender: string;
    phone_number: string | null;
}

interface Department {
    id: number;
    name: string;
    code: string;
    description: string;
}

interface VitalSigns {
    id: number;
    blood_pressure_systolic: number | null;
    blood_pressure_diastolic: number | null;
    temperature: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    oxygen_saturation: number | null;
    weight: number | null;
    height: number | null;
    notes: string | null;
    recorded_at: string;
}

interface Checkin {
    id: number;
    patient: Patient;
    department: Department;
    status: string;
    checked_in_at: string;
    vitals_taken_at: string | null;
    vital_signs?: VitalSigns[];
}

interface TodaysListProps {
    checkins: Checkin[];
    departments?: Department[];
    onRecordVitals: (checkin: Checkin) => void;
    onEditVitals?: (checkin: Checkin) => void;
    onCancel?: (checkin: Checkin) => void;
    emptyMessage?: string;
    canUpdateDate?: boolean;
    canCancelCheckin?: boolean;
    canEditVitals?: boolean;
    isSearchResults?: boolean;
    onDateUpdated?: (checkinId: number, newDate: string) => void;
    onDepartmentUpdated?: (checkinId: number, departmentId: number) => void;
}

export default function TodaysList({
    checkins,
    departments = [],
    onRecordVitals,
    onEditVitals,
    onCancel,
    emptyMessage = 'No active check-ins.',
    canUpdateDate = false,
    canCancelCheckin = false,
    canEditVitals = false,
    isSearchResults = false,
    onDateUpdated,
    onDepartmentUpdated,
}: TodaysListProps) {
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [selectedCheckin, setSelectedCheckin] = useState<Checkin | null>(
        null,
    );
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>(
        isSearchResults ? 'all' : 'checked_in',
    );
    const [departmentFilter, setDepartmentFilter] = useState<string>('all');
    const [editingDepartment, setEditingDepartment] = useState<number | null>(
        null,
    );
    const [selectedDepartmentId, setSelectedDepartmentId] = useState<
        number | null
    >(null);
    const [editingDate, setEditingDate] = useState<number | null>(null);
    const [selectedDate, setSelectedDate] = useState<string>('');

    const handleCancelClick = (checkin: Checkin) => {
        setSelectedCheckin(checkin);
        setCancelDialogOpen(true);
    };

    const handleCancelConfirm = () => {
        if (selectedCheckin) {
            router.post(
                `/checkin/checkins/${selectedCheckin.id}/cancel`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setCancelDialogOpen(false);
                        setSelectedCheckin(null);
                    },
                },
            );
        }
    };

    const handleEditDepartment = (checkin: Checkin) => {
        setEditingDepartment(checkin.id);
        setSelectedDepartmentId(checkin.department?.id ?? null);
    };

    const handleDepartmentChange = (checkinId: number) => {
        if (selectedDepartmentId) {
            router.patch(
                `/checkin/checkins/${checkinId}/department`,
                {
                    department_id: selectedDepartmentId,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setEditingDepartment(null);
                        setSelectedDepartmentId(null);
                        // If this is search results, update locally. Otherwise reload the page data
                        if (onDepartmentUpdated) {
                            onDepartmentUpdated(
                                checkinId,
                                selectedDepartmentId,
                            );
                        } else {
                            router.reload({ only: ['todayCheckins'] });
                        }
                    },
                },
            );
        }
    };

    const handleCancelEdit = () => {
        setEditingDepartment(null);
        setSelectedDepartmentId(null);
    };

    const handleEditDate = (checkin: Checkin) => {
        setEditingDate(checkin.id);
        // Format the date to YYYY-MM-DD for the date input
        const date = new Date(checkin.checked_in_at);
        const formattedDate = date.toISOString().split('T')[0];
        setSelectedDate(formattedDate);
    };

    const handleDateChange = (checkinId: number) => {
        if (selectedDate) {
            // Find the original check-in to preserve the time
            const originalCheckin = checkins.find((c) => c.id === checkinId);
            if (!originalCheckin) return;

            // Combine the new date with the original time
            const originalDate = new Date(originalCheckin.checked_in_at);
            const newDateTime = new Date(selectedDate);
            newDateTime.setHours(
                originalDate.getHours(),
                originalDate.getMinutes(),
                originalDate.getSeconds(),
                originalDate.getMilliseconds(),
            );

            router.patch(
                `/checkin/checkins/${checkinId}/date`,
                {
                    checked_in_at: newDateTime.toISOString(),
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setEditingDate(null);
                        setSelectedDate('');
                        // If this is search results, update locally. Otherwise reload the page data
                        if (onDateUpdated) {
                            onDateUpdated(checkinId, newDateTime.toISOString());
                        } else {
                            router.reload({ only: ['todayCheckins'] });
                        }
                    },
                },
            );
        }
    };

    const handleCancelDateEdit = () => {
        setEditingDate(null);
        setSelectedDate('');
    };

    const getStatusBadge = (status: string) => {
        const statusConfig: Record<string, { label: string; className: string }> = {
            checked_in: { 
                label: 'Checked In', 
                className: 'bg-blue-100 text-blue-700 border-blue-200' 
            },
            vitals_taken: {
                label: 'Vitals Taken',
                className: 'bg-green-100 text-green-700 border-green-200',
            },
            awaiting_consultation: {
                label: 'Awaiting Doctor',
                className: 'bg-amber-100 text-amber-700 border-amber-200',
            },
            in_consultation: {
                label: 'In Consultation',
                className: 'bg-purple-100 text-purple-700 border-purple-200',
            },
            completed: { 
                label: 'Completed', 
                className: 'bg-emerald-100 text-emerald-700 border-emerald-200' 
            },
            cancelled: { 
                label: 'Cancelled', 
                className: 'bg-gray-100 text-gray-500 border-gray-200' 
            },
        };

        const config = statusConfig[status] || statusConfig.checked_in;
        return (
            <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${config.className}`}>
                {config.label}
            </span>
        );
    };

    const canCancel = (status: string) => {
        return ['checked_in', 'vitals_taken', 'awaiting_consultation'].includes(
            status,
        );
    };

    // Get unique departments for filter
    const uniqueDepartments = Array.from(
        new Set(checkins.map((c) => c.department?.name).filter(Boolean)),
    ).sort();

    // Filter check-ins based on search term, status, and department
    const filteredCheckins = checkins.filter((checkin) => {
        // Search filter
        if (searchTerm) {
            const search = searchTerm.toLowerCase();
            const matchesSearch =
                checkin.patient.full_name.toLowerCase().includes(search) ||
                checkin.patient.patient_number.toLowerCase().includes(search) ||
                checkin.department?.name?.toLowerCase().includes(search) ||
                checkin.patient.phone_number?.toLowerCase().includes(search);
            if (!matchesSearch) return false;
        }

        // Status filter
        if (statusFilter !== 'all' && checkin.status !== statusFilter) {
            return false;
        }

        // Department filter
        if (
            departmentFilter !== 'all' &&
            checkin.department?.name !== departmentFilter
        ) {
            return false;
        }

        return true;
    });

    if (checkins.length === 0) {
        return (
            <div className="py-8 text-center text-muted-foreground">
                {emptyMessage}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Search and Filters */}
            <div className="space-y-3">
                {/* Search Input */}
                <div className="relative">
                    <input
                        type="text"
                        placeholder="Search by name, patient #, department, or phone..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full rounded-md border px-3 py-2"
                    />
                    {searchTerm && (
                        <button
                            onClick={() => setSearchTerm('')}
                            className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        >
                            ✕
                        </button>
                    )}
                </div>

                {/* Filters Row */}
                {!isSearchResults && (
                    <div className="flex gap-3">
                        {/* Status Filter */}
                        <div className="flex-1">
                            <select
                                value={statusFilter}
                                onChange={(e) =>
                                    setStatusFilter(e.target.value)
                                }
                                className="w-full rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="all">All Statuses</option>
                                <option value="checked_in">Checked In</option>
                                <option value="vitals_taken">
                                    Vitals Taken
                                </option>
                                <option value="awaiting_consultation">
                                    Awaiting Doctor
                                </option>
                                <option value="in_consultation">
                                    In Consultation
                                </option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        {/* Department Filter */}
                        <div className="flex-1">
                            <select
                                value={departmentFilter}
                                onChange={(e) =>
                                    setDepartmentFilter(e.target.value)
                                }
                                className="w-full rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="all">All Departments</option>
                                {uniqueDepartments.map((dept) => (
                                    <option key={dept} value={dept}>
                                        {dept}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Clear Filters Button */}
                        {(statusFilter !== 'checked_in' ||
                            departmentFilter !== 'all' ||
                            searchTerm) && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setStatusFilter('checked_in');
                                    setDepartmentFilter('all');
                                    setSearchTerm('');
                                }}
                                className="whitespace-nowrap"
                            >
                                Clear Filters
                            </Button>
                        )}
                    </div>
                )}
            </div>

            {/* Results count */}
            {(searchTerm ||
                statusFilter !== 'all' ||
                departmentFilter !== 'all') && (
                <p className="text-sm text-muted-foreground">
                    Showing {filteredCheckins.length} of {checkins.length}{' '}
                    check-ins
                </p>
            )}

            {/* Check-ins list */}
            <div className="max-h-96 space-y-4 overflow-y-auto">
                {filteredCheckins.length === 0 ? (
                    <div className="py-8 text-center text-muted-foreground">
                        {searchTerm ||
                        statusFilter !== 'all' ||
                        departmentFilter !== 'all'
                            ? 'No check-ins match your filters.'
                            : emptyMessage}
                    </div>
                ) : (
                    filteredCheckins.map((checkin) => (
                        <div
                            key={checkin.id}
                            className="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div className="flex-1">
                                <div className="space-y-1">
                                    <h3 className="font-medium">
                                        {checkin.patient.full_name}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {checkin.patient.patient_number} •{' '}
                                        {checkin.patient.age} years •{' '}
                                        {checkin.patient.gender}
                                    </p>
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        {editingDepartment === checkin.id ? (
                                            <>
                                                <select
                                                    value={
                                                        selectedDepartmentId ||
                                                        ''
                                                    }
                                                    onChange={(e) =>
                                                        setSelectedDepartmentId(
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="rounded border px-2 py-1 text-sm"
                                                >
                                                    {departments.map((dept) => (
                                                        <option
                                                            key={dept.id}
                                                            value={dept.id}
                                                        >
                                                            {dept.name}
                                                        </option>
                                                    ))}
                                                </select>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        handleDepartmentChange(
                                                            checkin.id,
                                                        )
                                                    }
                                                >
                                                    Save
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={handleCancelEdit}
                                                >
                                                    Cancel
                                                </Button>
                                            </>
                                        ) : (
                                            <>
                                                <span>
                                                    {checkin.department?.name ?? 'N/A'}
                                                </span>
                                                {checkin.status ===
                                                    'checked_in' &&
                                                    departments.length > 0 && (
                                                        <button
                                                            onClick={() =>
                                                                handleEditDepartment(
                                                                    checkin,
                                                                )
                                                            }
                                                            className="text-xs text-blue-600 underline hover:text-blue-800"
                                                        >
                                                            Change
                                                        </button>
                                                    )}
                                            </>
                                        )}
                                        {editingDate !== checkin.id && (
                                            <>
                                                <span>
                                                    •{' '}
                                                    {new Date(
                                                        checkin.checked_in_at,
                                                    ).toLocaleDateString()}{' '}
                                                    {new Date(
                                                        checkin.checked_in_at,
                                                    ).toLocaleTimeString()}
                                                </span>
                                                {canUpdateDate && (
                                                    <button
                                                        onClick={() =>
                                                            handleEditDate(
                                                                checkin,
                                                            )
                                                        }
                                                        className="text-xs text-blue-600 underline hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                                    >
                                                        Edit Date
                                                    </button>
                                                )}
                                            </>
                                        )}
                                        {editingDate === checkin.id && (
                                            <>
                                                <input
                                                    type="date"
                                                    value={selectedDate}
                                                    onChange={(e) =>
                                                        setSelectedDate(
                                                            e.target.value,
                                                        )
                                                    }
                                                    max={
                                                        new Date()
                                                            .toISOString()
                                                            .split('T')[0]
                                                    }
                                                    className="rounded border px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                                                />
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        handleDateChange(
                                                            checkin.id,
                                                        )
                                                    }
                                                >
                                                    Save
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={
                                                        handleCancelDateEdit
                                                    }
                                                >
                                                    Cancel
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div className="flex flex-col items-end gap-2">
                                {getStatusBadge(checkin.status)}
                                <div className="flex gap-2">
                                    {checkin.status === 'checked_in' && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                onRecordVitals(checkin)
                                            }
                                        >
                                            Record Vitals
                                        </Button>
                                    )}
                                    {canEditVitals && checkin.vital_signs && checkin.vital_signs.length > 0 && onEditVitals && (
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() =>
                                                onEditVitals(checkin)
                                            }
                                        >
                                            Edit Vitals
                                        </Button>
                                    )}
                                    {canCancelCheckin && canCancel(checkin.status) && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                handleCancelClick(checkin)
                                            }
                                            className="text-destructive hover:bg-destructive/10"
                                        >
                                            Cancel
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </div>

            <AlertDialog
                open={cancelDialogOpen}
                onOpenChange={setCancelDialogOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Cancel Check-in</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to cancel this check-in for{' '}
                            <strong>
                                {selectedCheckin?.patient.full_name}
                            </strong>
                            ?
                            <br />
                            <br />
                            <strong>Note:</strong> Any unpaid charges will be
                            voided. Paid charges will not be refunded.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>No, Keep It</AlertDialogCancel>
                        <AlertDialogAction onClick={handleCancelConfirm}>
                            Yes, Cancel Check-in
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
