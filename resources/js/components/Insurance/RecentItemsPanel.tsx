import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { AlertTriangle, Plus } from 'lucide-react';
import { useState } from 'react';

interface RecentItem {
    id: number;
    code: string;
    name: string;
    category: string;
    price: number;
    added_date: string;
    is_expensive: boolean;
    coverage_status: 'default' | 'exception' | 'not_covered';
}

interface Props {
    planId: number;
    recentItems: RecentItem[];
    onAddException?: (item: RecentItem) => void;
}

export default function RecentItemsPanel({
    planId,
    recentItems,
    onAddException,
}: Props) {
    const [filterCategory, setFilterCategory] = useState<string | 'all'>('all');

    const getCategoryLabel = (category: string) => {
        return category.charAt(0).toUpperCase() + category.slice(1);
    };

    const getCoverageStatusBadge = (status: string) => {
        switch (status) {
            case 'exception':
                return (
                    <Badge variant="secondary" className="text-xs">
                        Exception
                    </Badge>
                );
            case 'default':
                return (
                    <Badge variant="outline" className="text-xs">
                        Default Coverage
                    </Badge>
                );
            case 'not_covered':
                return (
                    <Badge
                        variant="destructive"
                        className="bg-red-100 text-xs text-red-800 dark:bg-red-900 dark:text-red-200"
                    >
                        Not Covered
                    </Badge>
                );
            default:
                return null;
        }
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffTime = Math.abs(now.getTime() - date.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 0) {
            return 'Today';
        } else if (diffDays === 1) {
            return 'Yesterday';
        } else if (diffDays < 7) {
            return `${diffDays} days ago`;
        } else {
            return date.toLocaleDateString();
        }
    };

    const categories = [
        'all',
        ...Array.from(new Set(recentItems.map((item) => item.category))),
    ];

    const filteredItems =
        filterCategory === 'all'
            ? recentItems
            : recentItems.filter((item) => item.category === filterCategory);

    if (recentItems.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
                <p className="text-sm text-gray-600 dark:text-gray-400">
                    No items added in the last 30 days
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Category Filters - Responsive */}
            <div className="flex flex-wrap gap-2">
                {categories.map((category) => (
                    <Button
                        key={category}
                        size="sm"
                        variant={
                            filterCategory === category ? 'default' : 'outline'
                        }
                        onClick={() => setFilterCategory(category)}
                        aria-pressed={filterCategory === category}
                    >
                        {category === 'all'
                            ? 'All'
                            : getCategoryLabel(category)}
                    </Button>
                ))}
            </div>

            {/* Recent Items List */}
            <div className="space-y-2">
                {filteredItems.length === 0 ? (
                    <div className="rounded-lg border border-gray-200 p-4 text-center dark:border-gray-700">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            No items in this category
                        </p>
                    </div>
                ) : (
                    filteredItems.map((item) => (
                        <div
                            key={`${item.category}-${item.id}`}
                            className="rounded-lg border border-gray-200 bg-white p-4 transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
                        >
                            {/* Card layout for mobile, flex layout for larger screens */}
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                <div className="flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant="outline"
                                            className="text-xs"
                                        >
                                            {getCategoryLabel(item.category)}
                                        </Badge>
                                        <span className="font-mono text-xs text-gray-600 sm:text-sm dark:text-gray-400">
                                            {item.code}
                                        </span>
                                        {item.is_expensive && (
                                            <div className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                                <AlertTriangle className="h-4 w-4" />
                                                <span className="text-xs font-medium">
                                                    High Cost
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <p className="mt-1 text-sm font-medium text-gray-900 sm:text-base dark:text-gray-100">
                                        {item.name}
                                    </p>
                                    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs sm:gap-3 sm:text-sm">
                                        <span className="font-semibold text-gray-900 dark:text-gray-100">
                                            ${item.price.toFixed(2)}
                                        </span>
                                        <span className="hidden text-gray-400 sm:inline">
                                            |
                                        </span>
                                        {getCoverageStatusBadge(
                                            item.coverage_status,
                                        )}
                                        <span className="hidden text-gray-400 sm:inline">
                                            |
                                        </span>
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Added {formatDate(item.added_date)}
                                        </span>
                                    </div>
                                </div>
                                {onAddException &&
                                    item.coverage_status !== 'exception' && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => onAddException(item)}
                                            className="w-full sm:w-auto"
                                            aria-label={`Add exception for ${item.name}`}
                                        >
                                            <Plus className="mr-1 h-4 w-4" />
                                            <span className="hidden sm:inline">Add Exception</span>
                                            <span className="sm:hidden">Add</span>
                                        </Button>
                                    )}
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
