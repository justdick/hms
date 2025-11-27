import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { AlertTriangle, Beaker, Pill, Scissors } from 'lucide-react';
import type { ClaimItem } from './types';

interface ClaimItemsTabsProps {
    items: {
        investigations: ClaimItem[];
        prescriptions: ClaimItem[];
        procedures: ClaimItem[];
    };
    isNhis: boolean;
}

/**
 * ClaimItemsTabs - Tabbed display of claim items by category
 *
 * Features:
 * - Tabs for Investigations, Prescriptions, and Procedures
 * - Displays item name, NHIS code, quantity, and price
 * - Shows "Not Covered" indicator for unmapped NHIS items
 * - Shows subtotals for each category
 *
 * @example
 * ```tsx
 * <ClaimItemsTabs
 *   items={vettingData.items}
 *   isNhis={vettingData.is_nhis}
 * />
 * ```
 */
export function ClaimItemsTabs({ items, isNhis }: ClaimItemsTabsProps) {
    const formatCurrency = (amount: number | null) => {
        if (amount === null) return '-';
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const calculateSubtotal = (categoryItems: ClaimItem[]) => {
        if (isNhis) {
            // For NHIS, only sum covered items
            return categoryItems
                .filter((item) => item.is_covered)
                .reduce(
                    (sum, item) => sum + (item.nhis_price || 0) * item.quantity,
                    0,
                );
        }
        return categoryItems.reduce((sum, item) => sum + item.subtotal, 0);
    };

    const countUncovered = (categoryItems: ClaimItem[]) => {
        return categoryItems.filter((item) => !item.is_covered).length;
    };

    const renderItemsTable = (
        categoryItems: ClaimItem[],
        emptyMessage: string,
    ) => {
        if (categoryItems.length === 0) {
            return (
                <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                    {emptyMessage}
                </div>
            );
        }

        const subtotal = calculateSubtotal(categoryItems);
        const uncoveredCount = countUncovered(categoryItems);

        return (
            <div className="space-y-3">
                <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-[40%]">Item</TableHead>
                                {isNhis && <TableHead>NHIS Code</TableHead>}
                                <TableHead className="text-right">
                                    Qty
                                </TableHead>
                                <TableHead className="text-right">
                                    {isNhis ? 'NHIS Price' : 'Unit Price'}
                                </TableHead>
                                <TableHead className="text-right">
                                    Subtotal
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categoryItems.map((item) => (
                                <TableRow
                                    key={item.id}
                                    className={
                                        !item.is_covered
                                            ? 'bg-red-50 dark:bg-red-950/20'
                                            : ''
                                    }
                                >
                                    <TableCell>
                                        <div>
                                            <p className="font-medium">
                                                {item.name}
                                            </p>
                                            {item.code && (
                                                <p className="text-xs text-gray-500">
                                                    Code: {item.code}
                                                </p>
                                            )}
                                        </div>
                                    </TableCell>
                                    {isNhis && (
                                        <TableCell>
                                            {item.is_covered ? (
                                                <span className="font-mono text-sm">
                                                    {item.nhis_code || '-'}
                                                </span>
                                            ) : (
                                                <Badge
                                                    variant="destructive"
                                                    className="flex w-fit items-center gap-1"
                                                >
                                                    <AlertTriangle className="h-3 w-3" />
                                                    Not Covered
                                                </Badge>
                                            )}
                                        </TableCell>
                                    )}
                                    <TableCell className="text-right">
                                        {item.quantity}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {isNhis
                                            ? formatCurrency(item.nhis_price)
                                            : formatCurrency(item.unit_price)}
                                    </TableCell>
                                    <TableCell className="text-right font-medium">
                                        {item.is_covered
                                            ? formatCurrency(
                                                  (isNhis
                                                      ? item.nhis_price || 0
                                                      : item.unit_price) *
                                                      item.quantity,
                                              )
                                            : '-'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {/* Category Summary */}
                <div className="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2 dark:bg-gray-900">
                    <div className="flex items-center gap-4">
                        <span className="text-sm text-gray-600 dark:text-gray-400">
                            {categoryItems.length} item
                            {categoryItems.length !== 1 ? 's' : ''}
                        </span>
                        {isNhis && uncoveredCount > 0 && (
                            <span className="flex items-center gap-1 text-sm text-red-600 dark:text-red-400">
                                <AlertTriangle className="h-4 w-4" />
                                {uncoveredCount} not covered
                            </span>
                        )}
                    </div>
                    <div className="text-right">
                        <span className="text-sm text-gray-600 dark:text-gray-400">
                            Subtotal:{' '}
                        </span>
                        <span className="font-semibold">
                            {formatCurrency(subtotal)}
                        </span>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <section aria-labelledby="claim-items-heading">
            <h3
                id="claim-items-heading"
                className="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100"
            >
                Claim Items
            </h3>

            <Tabs defaultValue="investigations" className="w-full">
                <TabsList className="grid w-full grid-cols-3">
                    <TabsTrigger
                        value="investigations"
                        className="flex items-center gap-2"
                    >
                        <Beaker className="h-4 w-4" />
                        Investigations
                        <Badge variant="secondary" className="ml-1">
                            {items.investigations.length}
                        </Badge>
                    </TabsTrigger>
                    <TabsTrigger
                        value="prescriptions"
                        className="flex items-center gap-2"
                    >
                        <Pill className="h-4 w-4" />
                        Prescriptions
                        <Badge variant="secondary" className="ml-1">
                            {items.prescriptions.length}
                        </Badge>
                    </TabsTrigger>
                    <TabsTrigger
                        value="procedures"
                        className="flex items-center gap-2"
                    >
                        <Scissors className="h-4 w-4" />
                        Procedures
                        <Badge variant="secondary" className="ml-1">
                            {items.procedures.length}
                        </Badge>
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="investigations" className="mt-4">
                    {renderItemsTable(
                        items.investigations,
                        'No investigations for this claim',
                    )}
                </TabsContent>

                <TabsContent value="prescriptions" className="mt-4">
                    {renderItemsTable(
                        items.prescriptions,
                        'No prescriptions for this claim',
                    )}
                </TabsContent>

                <TabsContent value="procedures" className="mt-4">
                    {renderItemsTable(
                        items.procedures,
                        'No procedures for this claim',
                    )}
                </TabsContent>
            </Tabs>
        </section>
    );
}
