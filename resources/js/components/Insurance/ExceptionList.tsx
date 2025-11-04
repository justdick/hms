import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/react';
import { Edit, History, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CoverageRuleHistory from './CoverageRuleHistory';

interface CoverageException {
    id: number;
    item_code: string;
    item_description: string;
    coverage_type: string;
    coverage_value: number;
    patient_copay_percentage: number;
    is_covered: boolean;
    notes?: string;
}

interface Props {
    exceptions: CoverageException[];
    defaultCoverage: number | null;
    planId: number;
    category: string;
    onEdit?: (exception: CoverageException) => void;
}

export default function ExceptionList({
    exceptions,
    defaultCoverage,
    planId,
    category,
    onEdit,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [filterType, setFilterType] = useState<'all' | 'full' | 'excluded'>(
        'all',
    );
    const [historyRuleId, setHistoryRuleId] = useState<number | null>(null);

    const formatCoverage = (exception: CoverageException) => {
        if (exception.coverage_type === 'full') {
            return '100%';
        }
        if (exception.coverage_type === 'excluded' || !exception.is_covered) {
            return '0%';
        }
        if (exception.coverage_type === 'percentage') {
            return `${exception.coverage_value}%`;
        }
        if (exception.coverage_type === 'fixed') {
            return `$${exception.coverage_value}`;
        }
        return 'N/A';
    };

    const getComparisonText = (exception: CoverageException) => {
        if (defaultCoverage === null) {
            return null;
        }

        const exceptionValue =
            exception.coverage_type === 'full'
                ? 100
                : exception.coverage_type === 'excluded' || !exception.is_covered
                  ? 0
                  : exception.coverage_value;

        if (exceptionValue === defaultCoverage) {
            return null;
        }

        return `Default: ${defaultCoverage}% → This item: ${formatCoverage(exception)}`;
    };

    const handleDelete = (exceptionId: number) => {
        if (
            confirm(
                'Are you sure you want to delete this coverage exception? The item will revert to the default coverage.',
            )
        ) {
            router.delete(`/admin/insurance/coverage-rules/${exceptionId}`, {
                preserveScroll: true,
            });
        }
    };

    // Filter exceptions
    const filteredExceptions = exceptions.filter((exception) => {
        // Search filter
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            const matchesSearch =
                exception.item_code.toLowerCase().includes(query) ||
                exception.item_description.toLowerCase().includes(query);
            if (!matchesSearch) return false;
        }

        // Type filter
        if (filterType === 'full') {
            return exception.coverage_type === 'full';
        }
        if (filterType === 'excluded') {
            return (
                exception.coverage_type === 'excluded' || !exception.is_covered
            );
        }

        return true;
    });

    if (exceptions.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
                <p className="text-sm text-gray-600 dark:text-gray-400">
                    All items in this category use the default coverage
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Search and Filters - Responsive */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative flex-1">
                    <Search className="absolute top-2.5 left-3 h-4 w-4 text-gray-400" />
                    <Input
                        type="text"
                        placeholder="Search exceptions..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="pl-9"
                        aria-label="Search exceptions by name or code"
                    />
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        variant={filterType === 'all' ? 'default' : 'outline'}
                        onClick={() => setFilterType('all')}
                        aria-pressed={filterType === 'all'}
                    >
                        <span className="hidden xs:inline">All ({exceptions.length})</span>
                        <span className="xs:hidden">All</span>
                    </Button>
                    <Button
                        size="sm"
                        variant={filterType === 'full' ? 'default' : 'outline'}
                        onClick={() => setFilterType('full')}
                        aria-pressed={filterType === 'full'}
                    >
                        <span className="hidden sm:inline">Fully Covered</span>
                        <span className="sm:hidden">Full</span>
                    </Button>
                    <Button
                        size="sm"
                        variant={
                            filterType === 'excluded' ? 'default' : 'outline'
                        }
                        onClick={() => setFilterType('excluded')}
                        aria-pressed={filterType === 'excluded'}
                    >
                        <span className="hidden sm:inline">Excluded</span>
                        <span className="sm:hidden">None</span>
                    </Button>
                </div>
            </div>

            {/* Exception List */}
            <div className="space-y-2">
                {filteredExceptions.length === 0 ? (
                    <div className="rounded-lg border border-gray-200 p-4 text-center dark:border-gray-700">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            No exceptions match your search
                        </p>
                    </div>
                ) : (
                    filteredExceptions.map((exception) => {
                        const comparison = getComparisonText(exception);
                        return (
                            <div
                                key={exception.id}
                                className="rounded-lg border border-gray-200 bg-white p-4 transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
                            >
                                {/* Card layout for mobile, flex layout for larger screens */}
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                    <div className="flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="secondary">
                                                Exception
                                            </Badge>
                                            <span className="font-mono text-xs text-gray-600 sm:text-sm dark:text-gray-400">
                                                {exception.item_code}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm font-medium text-gray-900 sm:text-base dark:text-gray-100">
                                            {exception.item_description}
                                        </p>
                                        <div className="mt-2 flex flex-wrap items-center gap-2 text-xs sm:gap-3 sm:text-sm">
                                            <span className="font-semibold text-green-700 dark:text-green-400">
                                                Coverage:{' '}
                                                {formatCoverage(exception)}
                                            </span>
                                            <span className="hidden text-gray-400 sm:inline">
                                                |
                                            </span>
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Patient Copay:{' '}
                                                {exception.patient_copay_percentage}
                                                %
                                            </span>
                                        </div>
                                        {comparison && (
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                                {comparison}
                                            </p>
                                        )}
                                        {exception.notes && (
                                            <p className="mt-2 text-xs text-gray-600 sm:text-sm dark:text-gray-400">
                                                Note: {exception.notes}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex gap-2 sm:flex-col">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                setHistoryRuleId(exception.id)
                                            }
                                            aria-label="View history"
                                            title="View change history"
                                        >
                                            <History className="h-4 w-4" />
                                            <span className="ml-2 sm:hidden">History</span>
                                        </Button>
                                        {onEdit && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    onEdit(exception)
                                                }
                                                aria-label="Edit exception"
                                            >
                                                <Edit className="h-4 w-4" />
                                                <span className="ml-2 sm:hidden">Edit</span>
                                            </Button>
                                        )}
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                handleDelete(exception.id)
                                            }
                                            aria-label="Delete exception"
                                        >
                                            <Trash2 className="h-4 w-4 text-red-600" />
                                            <span className="ml-2 sm:hidden">Delete</span>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            {/* History Modal */}
            {historyRuleId && (
                <CoverageRuleHistory
                    ruleId={historyRuleId}
                    isOpen={historyRuleId !== null}
                    onClose={() => setHistoryRuleId(null)}
                />
            )}
        </div>
    );
}
