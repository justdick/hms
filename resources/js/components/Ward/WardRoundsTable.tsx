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
import { WardRound, wardRoundsColumns } from '@/pages/Ward/ward-rounds-columns';
import { Stethoscope } from 'lucide-react';

interface WardRoundsTableProps {
    admissionId: number;
    wardRounds: WardRound[];
    onViewWardRound?: (wardRound: WardRound) => void;
    canUpdateWardRound?: boolean;
}

export function WardRoundsTable({
    admissionId,
    wardRounds,
    onViewWardRound,
    canUpdateWardRound = false,
}: WardRoundsTableProps) {
    const [sorting, setSorting] = React.useState<SortingState>([
        { id: 'round_datetime', desc: true },
    ]);

    const table = useReactTable({
        data: wardRounds,
        columns: wardRoundsColumns(admissionId, onViewWardRound, canUpdateWardRound),
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
                                    className="hover:bg-muted/50 dark:hover:bg-gray-800/50"
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
                                        wardRoundsColumns(
                                            admissionId,
                                            onViewWardRound,
                                            canUpdateWardRound,
                                        ).length
                                    }
                                    className="h-24 text-center"
                                >
                                    <div className="flex flex-col items-center gap-2">
                                        <Stethoscope className="h-8 w-8 text-muted-foreground" />
                                        <div className="text-gray-500 dark:text-gray-400">
                                            No ward rounds found.
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
