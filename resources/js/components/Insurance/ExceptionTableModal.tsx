import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
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
import { formatCurrency } from '@/lib/utils';
import axios from 'axios';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    Edit,
    MoreVertical,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';

interface CoverageException {
    id: number;
    item_code: string;
    item_description: string;
    coverage_type: string;
    coverage_value: number | string;
    tariff_amount?: number | string | null;
    nhis_tariff_price?: number | string | null;
    patient_copay_percentage: number | string;
    patient_copay_amount?: number | string | null;
    is_covered: boolean;
    notes?: string;
}

interface Props {
    open: boolean;
    onClose: () => void;
    category: string;
    categoryLabel: string;
    exceptions: CoverageException[];
    planId: number;
    onDelete?: (id: number) => void;
    onEdit?: (exception: CoverageException) => void;
}

const ITEMS_PER_PAGE = 20;

export default function ExceptionTableModal({
    open,
    onClose,
    category,
    categoryLabel,
    exceptions,
    planId,
    onDelete,
    onEdit,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const [sortColumn, setSortColumn] =
        useState<keyof CoverageException>('item_code');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');

    // Filter and sort
    const filteredAndSorted = useMemo(() => {
        let result = [...exceptions];

        // Filter
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            result = result.filter(
                (ex) =>
                    ex.item_code.toLowerCase().includes(query) ||
                    ex.item_description.toLowerCase().includes(query),
            );
        }

        // Sort
        result.sort((a, b) => {
            const aVal = a[sortColumn];
            const bVal = b[sortColumn];

            if (typeof aVal === 'string' && typeof bVal === 'string') {
                return sortDirection === 'asc'
                    ? aVal.localeCompare(bVal)
                    : bVal.localeCompare(aVal);
            }

            if (typeof aVal === 'number' && typeof bVal === 'number') {
                return sortDirection === 'asc' ? aVal - bVal : bVal - aVal;
            }

            return 0;
        });

        return result;
    }, [exceptions, searchQuery, sortColumn, sortDirection]);

    // Pagination
    const totalPages = Math.ceil(filteredAndSorted.length / ITEMS_PER_PAGE);
    const paginatedData = filteredAndSorted.slice(
        (currentPage - 1) * ITEMS_PER_PAGE,
        currentPage * ITEMS_PER_PAGE,
    );

    const handleSort = (column: keyof CoverageException) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    const handleExport = async () => {
        try {
            const response = await axios.get(
                `/admin/insurance/plans/${planId}/coverage/${category}/exceptions/export`,
                { responseType: 'blob' },
            );

            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute(
                'download',
                `${category}_exceptions_${new Date().toISOString().split('T')[0]}.xlsx`,
            );
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Export failed:', error);
        }
    };

    const getCoverageDisplay = (exception: CoverageException) => {
        switch (exception.coverage_type) {
            case 'percentage':
                return `${exception.coverage_value}%`;
            case 'fixed':
                const fixedValue =
                    typeof exception.coverage_value === 'number'
                        ? exception.coverage_value
                        : parseFloat(exception.coverage_value || '0');
                return `$${fixedValue.toFixed(2)}`;
            case 'full':
                return 'Full Coverage';
            case 'excluded':
                return 'Excluded';
            default:
                return exception.coverage_value;
        }
    };

    const getCoverageTypeBadge = (type: string) => {
        const variants: Record<string, { variant: any; label: string }> = {
            percentage: { variant: 'default', label: 'Percentage' },
            fixed: { variant: 'secondary', label: 'Fixed Amount' },
            full: { variant: 'default', label: 'Full' },
            excluded: { variant: 'destructive', label: 'Excluded' },
        };

        const config = variants[type] || { variant: 'default', label: type };
        return <Badge variant={config.variant}>{config.label}</Badge>;
    };

    const getCopayDisplay = (exception: CoverageException) => {
        // For NHIS/full coverage, show fixed copay amount if set
        if (
            exception.patient_copay_amount &&
            parseFloat(String(exception.patient_copay_amount)) > 0
        ) {
            return formatCurrency(
                parseFloat(String(exception.patient_copay_amount)),
            );
        }
        // For percentage-based coverage, show percentage
        if (
            exception.patient_copay_percentage &&
            parseFloat(String(exception.patient_copay_percentage)) > 0
        ) {
            return `${parseFloat(String(exception.patient_copay_percentage)).toFixed(0)}%`;
        }
        return 'None';
    };

    const getTariffDisplay = (exception: CoverageException) => {
        // First check NHIS tariff (from mapping)
        if (
            exception.nhis_tariff_price &&
            parseFloat(String(exception.nhis_tariff_price)) > 0
        ) {
            return formatCurrency(
                parseFloat(String(exception.nhis_tariff_price)),
            );
        }
        // Then check custom tariff amount
        if (
            exception.tariff_amount &&
            parseFloat(String(exception.tariff_amount)) > 0
        ) {
            return formatCurrency(parseFloat(String(exception.tariff_amount)));
        }
        return '-';
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="flex max-h-[95vh] w-[95vw] max-w-7xl flex-col">
                <DialogHeader>
                    <DialogTitle className="flex items-center justify-between">
                        <span>{categoryLabel} - Coverage Exceptions</span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleExport}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    </DialogTitle>
                    <DialogDescription>
                        Manage coverage exceptions for{' '}
                        {categoryLabel.toLowerCase()}.{filteredAndSorted.length}{' '}
                        exception{filteredAndSorted.length !== 1 ? 's' : ''}{' '}
                        configured.
                    </DialogDescription>
                </DialogHeader>

                {/* Search Bar */}
                <div className="relative">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                    <Input
                        type="text"
                        placeholder="Search by item code or name..."
                        value={searchQuery}
                        onChange={(e) => {
                            setSearchQuery(e.target.value);
                            setCurrentPage(1);
                        }}
                        className="pr-10 pl-10"
                    />
                    {searchQuery && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="absolute top-1/2 right-1 h-7 w-7 -translate-y-1/2 p-0"
                            onClick={() => setSearchQuery('')}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    )}
                </div>

                {/* Table */}
                <div className="flex-1 overflow-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead
                                    className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                    onClick={() => handleSort('item_code')}
                                >
                                    Item Code{' '}
                                    {sortColumn === 'item_code' &&
                                        (sortDirection === 'asc' ? '↑' : '↓')}
                                </TableHead>
                                <TableHead
                                    className="min-w-[200px] cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                    onClick={() =>
                                        handleSort('item_description')
                                    }
                                >
                                    Name{' '}
                                    {sortColumn === 'item_description' &&
                                        (sortDirection === 'asc' ? '↑' : '↓')}
                                </TableHead>
                                <TableHead
                                    className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                    onClick={() => handleSort('coverage_value')}
                                >
                                    Coverage{' '}
                                    {sortColumn === 'coverage_value' &&
                                        (sortDirection === 'asc' ? '↑' : '↓')}
                                </TableHead>
                                <TableHead className="text-right">
                                    Insurance Tariff
                                </TableHead>
                                <TableHead className="text-right">
                                    Patient Copay
                                </TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginatedData.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-8 text-center text-gray-500"
                                    >
                                        {searchQuery
                                            ? 'No exceptions match your search.'
                                            : 'No exceptions configured.'}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                paginatedData.map((exception) => (
                                    <TableRow key={exception.id}>
                                        <TableCell className="font-mono text-sm">
                                            {exception.item_code}
                                        </TableCell>
                                        <TableCell className="max-w-[250px] truncate">
                                            {exception.item_description}
                                        </TableCell>
                                        <TableCell>
                                            {getCoverageTypeBadge(
                                                exception.coverage_type,
                                            )}
                                            <span className="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                                {exception.coverage_type ===
                                                    'percentage' &&
                                                    `${exception.coverage_value}%`}
                                                {exception.coverage_type ===
                                                    'fixed' &&
                                                    formatCurrency(
                                                        parseFloat(
                                                            String(
                                                                exception.coverage_value ||
                                                                    0,
                                                            ),
                                                        ),
                                                    )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right text-blue-600 dark:text-blue-400">
                                            {getTariffDisplay(exception)}
                                        </TableCell>
                                        <TableCell className="text-right font-medium text-orange-600 dark:text-orange-400">
                                            {getCopayDisplay(exception)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        <MoreVertical className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            onEdit?.(exception)
                                                        }
                                                    >
                                                        <Edit className="mr-2 h-4 w-4" />
                                                        Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        className="text-red-600"
                                                        onClick={() =>
                                                            onDelete?.(
                                                                exception.id,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {totalPages > 1 && (
                    <div className="flex items-center justify-between border-t pt-4">
                        <div className="text-sm text-gray-600 dark:text-gray-400">
                            Showing {(currentPage - 1) * ITEMS_PER_PAGE + 1} to{' '}
                            {Math.min(
                                currentPage * ITEMS_PER_PAGE,
                                filteredAndSorted.length,
                            )}{' '}
                            of {filteredAndSorted.length} exceptions
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCurrentPage(currentPage - 1)}
                                disabled={currentPage === 1}
                            >
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </Button>
                            <div className="text-sm">
                                Page {currentPage} of {totalPages}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCurrentPage(currentPage + 1)}
                                disabled={currentPage === totalPages}
                            >
                                Next
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
