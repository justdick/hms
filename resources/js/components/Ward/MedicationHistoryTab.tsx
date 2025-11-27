import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { AlertTriangle, Filter, LayoutGrid, Table } from 'lucide-react';
import { useState } from 'react';
import { MedicationHistoryCard } from './MedicationHistoryCard';
import { MedicationHistoryTable } from './MedicationHistoryTable';

interface Drug {
    id: number;
    name: string;
    strength?: string;
    form?: string;
}

interface User {
    id: number;
    name: string;
}

interface Prescription {
    id: number;
    drug?: Drug;
    medication_name: string;
    dosage?: string;
    dose_quantity?: string;
    frequency?: string;
    duration?: string;
    route?: string;
    instructions?: string;
    status?: string;
    start_date?: string;
    discontinued_at?: string;
    discontinued_by?: User;
    discontinuation_reason?: string;
    schedule_pattern?: {
        day_1?: string[];
        day_2?: string[];
        subsequent?: string[];
        [key: string]: string[] | undefined;
    };
}

interface MedicationHistoryTabProps {
    patientAdmissionId: number;
    prescriptions: Prescription[];
    onConfigureTimes: (prescriptionId: number) => void;
    onReconfigureTimes: (prescriptionId: number) => void;
    onViewSchedule: (prescriptionId: number) => void;
    onDiscontinue: (prescriptionId: number, reason: string) => void;
}

type FilterType = 'all' | 'active' | 'discontinued' | 'pending_schedule';
type ViewType = 'table' | 'cards';

export function MedicationHistoryTab({
    patientAdmissionId,
    prescriptions,
    onConfigureTimes,
    onReconfigureTimes,
    onViewSchedule,
    onDiscontinue,
}: MedicationHistoryTabProps) {
    const [filter, setFilter] = useState<FilterType>('all');
    const [viewType, setViewType] = useState<ViewType>('table');

    // Filter prescriptions based on selected filter
    const filteredPrescriptions = prescriptions.filter((prescription) => {
        if (filter === 'all') return true;
        if (filter === 'active')
            return (
                (prescription.status === 'active' || !prescription.status) &&
                !prescription.discontinued_at
            );
        if (filter === 'discontinued') return !!prescription.discontinued_at;
        if (filter === 'pending_schedule')
            return (
                !prescription.schedule_pattern && !prescription.discontinued_at
            );
        return true;
    });

    // Count prescriptions needing schedule configuration
    const pendingScheduleCount = prescriptions.filter(
        (p) => !p.schedule_pattern && !p.discontinued_at,
    ).length;

    return (
        <div className="space-y-4">
            {/* Filter and View Controls */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Filter className="h-4 w-4 text-muted-foreground" />
                    <Select
                        value={filter}
                        onValueChange={(value) =>
                            setFilter(value as FilterType)
                        }
                    >
                        <SelectTrigger className="w-[200px]">
                            <SelectValue placeholder="Filter prescriptions" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="discontinued">
                                Discontinued
                            </SelectItem>
                            <SelectItem value="pending_schedule">
                                Pending Schedule
                                {pendingScheduleCount > 0 && (
                                    <span className="ml-2 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                        {pendingScheduleCount}
                                    </span>
                                )}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center gap-2">
                    {pendingScheduleCount > 0 &&
                        filter !== 'pending_schedule' && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setFilter('pending_schedule')}
                                className="border-orange-500 text-orange-700 hover:bg-orange-50 dark:border-orange-600 dark:text-orange-400 dark:hover:bg-orange-950/20"
                            >
                                <AlertTriangle className="mr-2 h-4 w-4" />
                                {pendingScheduleCount} Pending Schedule
                            </Button>
                        )}

                    {/* View Toggle */}
                    <div className="flex items-center gap-1 rounded-md border p-1">
                        <Button
                            variant={viewType === 'table' ? 'default' : 'ghost'}
                            size="sm"
                            onClick={() => setViewType('table')}
                            className="h-8 px-3"
                        >
                            <Table className="h-4 w-4" />
                        </Button>
                        <Button
                            variant={viewType === 'cards' ? 'default' : 'ghost'}
                            size="sm"
                            onClick={() => setViewType('cards')}
                            className="h-8 px-3"
                        >
                            <LayoutGrid className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            {/* Prescriptions Display */}
            {filteredPrescriptions.length === 0 ? (
                <div className="rounded-lg border-2 border-dashed border-muted-foreground/25 p-12 text-center">
                    <p className="text-sm text-muted-foreground">
                        {filter === 'all'
                            ? 'No prescriptions found'
                            : filter === 'active'
                              ? 'No active prescriptions'
                              : filter === 'discontinued'
                                ? 'No discontinued prescriptions'
                                : 'No prescriptions pending schedule configuration'}
                    </p>
                </div>
            ) : viewType === 'table' ? (
                <MedicationHistoryTable
                    prescriptions={filteredPrescriptions}
                    onConfigureTimes={onConfigureTimes}
                    onReconfigureTimes={onReconfigureTimes}
                    onViewSchedule={onViewSchedule}
                    onDiscontinue={onDiscontinue}
                />
            ) : (
                <div className="space-y-3">
                    {filteredPrescriptions.map((prescription) => (
                        <MedicationHistoryCard
                            key={prescription.id}
                            prescription={prescription}
                            onConfigureTimes={onConfigureTimes}
                            onReconfigureTimes={onReconfigureTimes}
                            onViewSchedule={onViewSchedule}
                            onDiscontinue={onDiscontinue}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
