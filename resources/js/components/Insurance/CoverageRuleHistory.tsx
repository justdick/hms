import { router } from '@inertiajs/react';
import { useState } from 'react';

interface HistoryEntry {
    id: number;
    action: string;
    user: {
        id: number;
        name: string;
    } | null;
    old_values: Record<string, any> | null;
    new_values: Record<string, any> | null;
    batch_id: string | null;
    created_at: string;
}

interface CoverageRuleHistoryProps {
    ruleId: number;
    isOpen: boolean;
    onClose: () => void;
}

const fieldLabels: Record<string, string> = {
    coverage_value: 'Coverage Percentage',
    patient_copay_percentage: 'Patient Copay',
    coverage_type: 'Coverage Type',
    is_covered: 'Is Covered',
    is_active: 'Is Active',
    item_code: 'Item Code',
    item_description: 'Item Description',
    max_quantity_per_visit: 'Max Quantity',
    max_amount_per_visit: 'Max Amount',
    requires_preauthorization: 'Requires Preauth',
    effective_from: 'Effective From',
    effective_to: 'Effective To',
    notes: 'Notes',
};

export default function CoverageRuleHistory({
    ruleId,
    isOpen,
    onClose,
}: CoverageRuleHistoryProps) {
    const [history, setHistory] = useState<HistoryEntry[]>([]);
    const [loading, setLoading] = useState(false);

    const loadHistory = () => {
        setLoading(true);
        router.get(
            `/admin/insurance/coverage-rules/${ruleId}/history`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['history'],
                onSuccess: (page: any) => {
                    setHistory(page.props.history || []);
                    setLoading(false);
                },
                onError: () => {
                    setLoading(false);
                },
            },
        );
    };

    if (!isOpen) return null;

    if (isOpen && history.length === 0 && !loading) {
        loadHistory();
    }

    const formatValue = (key: string, value: any): string => {
        if (value === null || value === undefined) return 'N/A';
        if (typeof value === 'boolean') return value ? 'Yes' : 'No';
        if (key.includes('percentage') || key.includes('value')) {
            return `${value}%`;
        }
        return String(value);
    };

    const getChangedFields = (entry: HistoryEntry) => {
        if (!entry.old_values || !entry.new_values) return [];

        const changed: Array<{
            field: string;
            label: string;
            oldValue: any;
            newValue: any;
        }> = [];

        Object.keys(entry.new_values).forEach((key) => {
            const oldValue = entry.old_values?.[key];
            const newValue = entry.new_values?.[key];

            if (oldValue !== newValue && fieldLabels[key]) {
                changed.push({
                    field: key,
                    label: fieldLabels[key],
                    oldValue,
                    newValue,
                });
            }
        });

        return changed;
    };

    const getActionColor = (action: string) => {
        switch (action) {
            case 'created':
                return 'text-green-600 dark:text-green-400';
            case 'updated':
                return 'text-blue-600 dark:text-blue-400';
            case 'deleted':
                return 'text-red-600 dark:text-red-400';
            default:
                return 'text-gray-600 dark:text-gray-400';
        }
    };

    const getActionIcon = (action: string) => {
        switch (action) {
            case 'created':
                return '✓';
            case 'updated':
                return '✎';
            case 'deleted':
                return '✗';
            default:
                return '•';
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-lg bg-white shadow-xl dark:bg-gray-800">
                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
                        Change History
                    </h2>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <svg
                            className="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <div
                    className="overflow-y-auto p-6"
                    style={{ maxHeight: 'calc(90vh - 140px)' }}
                >
                    {loading ? (
                        <div className="flex items-center justify-center py-12">
                            <div className="h-8 w-8 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
                        </div>
                    ) : history.length === 0 ? (
                        <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                            No history available for this rule.
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {history.map((entry, index) => {
                                const changedFields = getChangedFields(entry);
                                const isGrouped =
                                    entry.batch_id &&
                                    index > 0 &&
                                    history[index - 1].batch_id ===
                                        entry.batch_id;

                                return (
                                    <div
                                        key={entry.id}
                                        className={`rounded-lg border ${
                                            isGrouped
                                                ? 'ml-4 border-l-4 border-blue-300 dark:border-blue-600'
                                                : 'border-gray-200 dark:border-gray-700'
                                        } bg-gray-50 p-4 dark:bg-gray-900`}
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-start gap-3">
                                                <span
                                                    className={`text-2xl ${getActionColor(
                                                        entry.action,
                                                    )}`}
                                                >
                                                    {getActionIcon(
                                                        entry.action,
                                                    )}
                                                </span>
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className={`font-semibold capitalize ${getActionColor(
                                                                entry.action,
                                                            )}`}
                                                        >
                                                            {entry.action}
                                                        </span>
                                                        {isGrouped && (
                                                            <span className="text-xs text-blue-600 dark:text-blue-400">
                                                                (Batch Update)
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                        {entry.user ? (
                                                            <span>
                                                                by{' '}
                                                                <span className="font-medium">
                                                                    {
                                                                        entry
                                                                            .user
                                                                            .name
                                                                    }
                                                                </span>
                                                            </span>
                                                        ) : (
                                                            <span>
                                                                by System
                                                            </span>
                                                        )}
                                                        {' • '}
                                                        {new Date(
                                                            entry.created_at,
                                                        ).toLocaleString()}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {changedFields.length > 0 && (
                                            <div className="mt-4 space-y-2">
                                                {changedFields.map((change) => (
                                                    <div
                                                        key={change.field}
                                                        className="rounded bg-white p-3 dark:bg-gray-800"
                                                    >
                                                        <div className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            {change.label}
                                                        </div>
                                                        <div className="mt-1 flex items-center gap-2 text-sm">
                                                            <span className="text-red-600 line-through dark:text-red-400">
                                                                {formatValue(
                                                                    change.field,
                                                                    change.oldValue,
                                                                )}
                                                            </span>
                                                            <span className="text-gray-400">
                                                                →
                                                            </span>
                                                            <span className="font-medium text-green-600 dark:text-green-400">
                                                                {formatValue(
                                                                    change.field,
                                                                    change.newValue,
                                                                )}
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                <div className="border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                    <button
                        onClick={onClose}
                        className="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
}
