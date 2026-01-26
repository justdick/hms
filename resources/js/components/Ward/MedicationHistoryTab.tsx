import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Filter, LayoutGrid, Table } from 'lucide-react';
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
    created_at?: string;
    discontinued_at?: string;
    discontinued_by?: User;
    discontinuation_reason?: string;
    completed_at?: string;
    completed_by?: User;
    completion_reason?: string;
}

interface MedicationHistoryTabProps {
    patientAdmissionId: number;
    prescriptions: Prescription[];
    onDiscontinue: (prescriptionId: number) => void;
    onComplete: (prescriptionId: number) => void;
    onResume?: (prescriptionId: number) => void;
    onUncomplete?: (prescriptionId: number) => void;
}

type FilterType = 'all' | 'active' | 'discontinued' | 'completed';
type ViewType = 'table' | 'cards';

export function MedicationHistoryTab({
    patientAdmissionId,
    prescriptions,
    onDiscontinue,
    onComplete,
    onResume,
    onUncomplete,
}: MedicationHistoryTabProps) {
    const [filter, setFilter] = useState<FilterType>('all');
    const [viewType, setViewType] = useState<ViewType>('table');

    // Filter prescriptions based on selected filter and sort by newest first
    const filteredPrescriptions = prescriptions
        .filter((prescription) => {
            if (filter === 'all') return true;
            if (filter === 'active')
                return (
                    (prescription.status === 'active' ||
                        !prescription.status) &&
                    !prescription.discontinued_at &&
                    !prescription.completed_at
                );
            if (filter === 'discontinued')
                return !!prescription.discontinued_at;
            if (filter === 'completed')
                return !!prescription.completed_at;
            return true;
        })
        .sort((a, b) => b.id - a.id);

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
                            <SelectItem value="completed">
                                Completed
                            </SelectItem>
                            <SelectItem value="discontinued">
                                Discontinued
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

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

            {/* Prescriptions Display */}
            {filteredPrescriptions.length === 0 ? (
                <div className="rounded-lg border-2 border-dashed border-muted-foreground/25 p-12 text-center">
                    <p className="text-sm text-muted-foreground">
                        {filter === 'all'
                            ? 'No prescriptions found'
                            : filter === 'active'
                              ? 'No active prescriptions'
                              : filter === 'completed'
                                ? 'No completed prescriptions'
                                : 'No discontinued prescriptions'}
                    </p>
                </div>
            ) : viewType === 'table' ? (
                <MedicationHistoryTable
                    prescriptions={filteredPrescriptions}
                    onDiscontinue={onDiscontinue}
                    onComplete={onComplete}
                    onResume={onResume}
                    onUncomplete={onUncomplete}
                />
            ) : (
                <div className="space-y-3">
                    {filteredPrescriptions.map((prescription) => (
                        <MedicationHistoryCard
                            key={prescription.id}
                            prescription={prescription}
                            onDiscontinue={onDiscontinue}
                            onComplete={onComplete}
                            onResume={onResume}
                            onUncomplete={onUncomplete}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
