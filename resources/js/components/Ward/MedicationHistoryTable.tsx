import {
    flexRender,
    getCoreRowModel,
    getSortedRowModel,
    SortingState,
    useReactTable,
} from '@tanstack/react-table';
import * as React from 'react';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Pill } from 'lucide-react';
import {
    medicationHistoryColumns,
    MedicationHistoryRow,
} from './medication-history-columns';

interface MedicationHistoryTableProps {
    prescriptions: MedicationHistoryRow[];
    onDiscontinue: (prescriptionId: number) => void;
    onComplete: (prescriptionId: number) => void;
    onResume?: (prescriptionId: number) => void;
}

export function MedicationHistoryTable({
    prescriptions,
    onDiscontinue,
    onComplete,
    onResume,
}: MedicationHistoryTableProps) {
    const [sorting, setSorting] = React.useState<SortingState>([
        { id: 'created_at', desc: true },
    ]);

    const table = useReactTable({
        data: prescriptions,
        columns: medicationHistoryColumns({ onDiscontinue, onComplete, onResume }),
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        onSortingChange: setSorting,
        state: {
            sorting,
        },
    });

    return (
        <div className="w-full">
            <div className="rounded-md border dark:border-gray-700">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .header,
                                                      header.getContext(),
                                                  )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    className={
                                        row.original.discontinued_at || row.original.completed_at
                                            ? 'opacity-60 hover:bg-muted/50 dark:opacity-50 dark:hover:bg-gray-800/50'
                                            : 'hover:bg-muted/50 dark:hover:bg-gray-800/50'
                                    }
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={
                                        medicationHistoryColumns({
                                            onDiscontinue,
                                            onComplete,
                                            onResume,
                                        }).length
                                    }
                                    className="h-24 text-center"
                                >
                                    <div className="flex flex-col items-center gap-2">
                                        <Pill className="h-8 w-8 text-muted-foreground" />
                                        <div className="text-gray-500 dark:text-gray-400">
                                            No prescriptions found.
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
