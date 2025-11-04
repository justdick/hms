import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    AddExceptionModal,
    ExceptionList,
    InlinePercentageEdit,
    HelpTooltip,
    SuccessMessage,
    QuickActionsMenu,
    KeyboardShortcutsHelp,
} from '@/components/Insurance';
import BulkImportModal from '@/components/Insurance/BulkImportModal';
import RecentItemsPanel from '@/components/Insurance/RecentItemsPanel';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    Download,
    FileText,
    Pill,
    Plus,
    Stethoscope,
    TestTube,
    Bed,
    Heart,
    Activity,
    Clock,
    Upload,
} from 'lucide-react';
import axios from 'axios';
import { useState, useEffect } from 'react';
import { useKeyboardShortcuts } from '@/hooks/useKeyboardShortcuts';

interface InsuranceProvider {
    id: number;
    name: string;
}

interface InsurancePlan {
    id: number;
    plan_name: string;
    plan_code: string;
    provider?: InsuranceProvider;
}

interface CategoryData {
    category: string;
    default_coverage: number | null;
    exception_count: number;
    general_rule_id: number | null;
}

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
    plan: {
        data: InsurancePlan;
    };
    categories: CategoryData[];
}

const categoryLabels: Record<string, string> = {
    consultation: 'Consultations',
    drug: 'Drugs',
    lab: 'Lab Tests',
    procedure: 'Procedures',
    ward: 'Ward Services',
    nursing: 'Nursing Services',
};

const categoryIcons: Record<string, React.ElementType> = {
    consultation: Stethoscope,
    drug: Pill,
    lab: TestTube,
    procedure: Activity,
    ward: Bed,
    nursing: Heart,
};

export default function CoverageDashboard({ plan: planWrapper, categories }: Props) {
    const plan = planWrapper.data;
    const [expandedCategory, setExpandedCategory] = useState<string | null>(
        null,
    );
    const [categoryData, setCategoryData] =
        useState<CategoryData[]>(categories);
    const [exceptions, setExceptions] = useState<
        Record<string, CoverageException[]>
    >({});
    const [loadingExceptions, setLoadingExceptions] = useState<
        Record<string, boolean>
    >({});
    const [modalOpen, setModalOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<string | null>(
        null,
    );
    const [recentItems, setRecentItems] = useState<RecentItem[]>([]);
    const [loadingRecentItems, setLoadingRecentItems] = useState(false);
    const [selectedRecentItem, setSelectedRecentItem] =
        useState<RecentItem | null>(null);
    const [successMessage, setSuccessMessage] = useState<{
        message: string;
        nextSteps?: string;
    } | null>(null);

    const getCoverageColor = (coverage: number | null): string => {
        if (coverage === null) return 'gray';
        if (coverage >= 80) return 'green';
        if (coverage >= 50) return 'yellow';
        return 'red';
    };

    const handleCoverageUpdate = (category: string, newValue: number) => {
        setCategoryData((prev) =>
            prev.map((cat) =>
                cat.category === category
                    ? { ...cat, default_coverage: newValue }
                    : cat,
            ),
        );
    };

    const getCoverageColorClasses = (
        coverage: number | null,
    ): { bg: string; border: string; text: string } => {
        const color = getCoverageColor(coverage);
        const colorMap: Record<
            string,
            { bg: string; border: string; text: string }
        > = {
            green: {
                bg: 'bg-green-50 dark:bg-green-950',
                border: 'border-green-300 dark:border-green-700',
                text: 'text-green-900 dark:text-green-100',
            },
            yellow: {
                bg: 'bg-yellow-50 dark:bg-yellow-950',
                border: 'border-yellow-300 dark:border-yellow-700',
                text: 'text-yellow-900 dark:text-yellow-100',
            },
            red: {
                bg: 'bg-red-50 dark:bg-red-950',
                border: 'border-red-300 dark:border-red-700',
                text: 'text-red-900 dark:text-red-100',
            },
            gray: {
                bg: 'bg-gray-50 dark:bg-gray-900',
                border: 'border-gray-300 dark:border-gray-700',
                text: 'text-gray-900 dark:text-gray-100',
            },
        };
        return colorMap[color];
    };

    const toggleCategory = async (category: string) => {
        const newExpanded = expandedCategory === category ? null : category;
        setExpandedCategory(newExpanded);

        // Load exceptions if expanding and not already loaded
        if (newExpanded && !exceptions[category]) {
            await loadExceptions(category);
        }
    };

    const loadExceptions = async (category: string) => {
        setLoadingExceptions((prev) => ({ ...prev, [category]: true }));
        try {
            const response = await axios.get(
                `/admin/insurance/plans/${plan.id}/coverage/${category}/exceptions`,
            );
            setExceptions((prev) => ({
                ...prev,
                [category]: response.data.exceptions,
            }));
        } catch (error) {
            console.error('Failed to load exceptions:', error);
        } finally {
            setLoadingExceptions((prev) => ({ ...prev, [category]: false }));
        }
    };

    const handleAddException = (category: string) => {
        setSelectedCategory(category);
        setModalOpen(true);
    };

    const handleModalSuccess = () => {
        // Reload exceptions for the selected category
        if (selectedCategory) {
            loadExceptions(selectedCategory);
            // Update exception count
            setCategoryData((prev) =>
                prev.map((cat) =>
                    cat.category === selectedCategory
                        ? {
                              ...cat,
                              exception_count: cat.exception_count + 1,
                          }
                        : cat,
                ),
            );
            
            // Show success message
            setSuccessMessage({
                message: 'Coverage exception added successfully!',
                nextSteps: 'The exception is now active and will be used for billing.',
            });
        }
        // Reload recent items to update coverage status
        loadRecentItems();
        // Clear selected recent item
        setSelectedRecentItem(null);
    };

    const loadRecentItems = async () => {
        setLoadingRecentItems(true);
        try {
            const response = await axios.get(
                `/admin/insurance/plans/${plan.id}/recent-items`,
            );
            setRecentItems(response.data.recent_items);
        } catch (error) {
            console.error('Failed to load recent items:', error);
        } finally {
            setLoadingRecentItems(false);
        }
    };

    const handleAddExceptionFromRecent = (item: RecentItem) => {
        setSelectedRecentItem(item);
        setSelectedCategory(item.category);
        setModalOpen(true);
    };

    useEffect(() => {
        loadRecentItems();
    }, []);

    // Keyboard shortcuts
    useKeyboardShortcuts([
        {
            key: 'n',
            callback: () => {
                // Open add exception modal with default category
                setSelectedCategory('drug');
                setModalOpen(true);
            },
        },
        {
            key: 'e',
            callback: () => {
                // Enable edit mode - expand first category if none expanded
                if (!expandedCategory && categoryData.length > 0) {
                    toggleCategory(categoryData[0].category);
                }
            },
        },
    ]);

    const handleManageRules = (category: string) => {
        router.visit(`/admin/insurance/plans/${plan.id}/coverage-rules`, {
            data: { category },
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance Plans', href: '/admin/insurance/plans' },
                {
                    title: plan.plan_name,
                    href: `/admin/insurance/plans/${plan.id}`,
                },
                { title: 'Coverage Dashboard', href: '' },
            ]}
        >
            <Head title={`${plan.plan_name} - Coverage Dashboard`} />

            {/* Skip to main content link for keyboard navigation */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-blue-600 focus:px-4 focus:py-2 focus:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            >
                Skip to main content
            </a>

            <div id="main-content" className="space-y-6">
                {/* Success Message */}
                {successMessage && (
                    <SuccessMessage
                        message={successMessage.message}
                        nextSteps={successMessage.nextSteps}
                        onClose={() => setSuccessMessage(null)}
                    />
                )}

                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <Link href={`/admin/insurance/plans/${plan.id}`}>
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                <span className="hidden sm:inline">Back to Plan</span>
                                <span className="sm:hidden">Back</span>
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900 sm:text-3xl dark:text-gray-100">
                                    <FileText className="h-6 w-6 sm:h-8 sm:w-8" />
                                    <span className="hidden sm:inline">Coverage Dashboard</span>
                                    <span className="sm:hidden">Coverage</span>
                                </h1>
                                <HelpTooltip
                                    content="View and manage coverage rules for all service categories. Click on any category card to see details and exceptions."
                                    side="right"
                                />
                            </div>
                            <p className="mt-1 text-sm text-gray-600 sm:text-base dark:text-gray-400">
                                {plan.plan_name} - {plan.provider?.name}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <QuickActionsMenu
                            planId={plan.id}
                            onAddException={() => {
                                // Open modal without a specific category - user will select
                                setSelectedCategory('drug'); // Default to drug category
                                setModalOpen(true);
                            }}
                            onBulkImport={() => {
                                setSelectedCategory('drug'); // Default to drug category
                                setImportModalOpen(true);
                            }}
                            onExportRules={() => {
                                window.location.href = `/admin/insurance/plans/${plan.id}/coverage-rules/export?include_history=true`;
                            }}
                        />
                    </div>
                </div>

                {/* Empty State Guidance */}
                {categoryData.every((cat) => cat.default_coverage === null) && (
                    <Card className="border-2 border-blue-300 bg-blue-50 dark:border-blue-700 dark:bg-blue-950">
                        <CardContent className="pt-6">
                            <div className="flex items-start gap-3">
                                <AlertCircle className="mt-0.5 h-6 w-6 text-blue-600 dark:text-blue-400" />
                                <div>
                                    <h3 className="font-semibold text-blue-900 dark:text-blue-100">
                                        Get Started
                                    </h3>
                                    <p className="mt-1 text-sm text-blue-800 dark:text-blue-200">
                                        Start by setting default coverage for each category below. 
                                        Click on any category card to expand and set the default coverage percentage.
                                    </p>
                                    <p className="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                        Tip: Most plans use 70-80% for consultations, 80-90% for drugs and labs, 
                                        and 100% for ward services.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Category Cards Grid - Responsive: stack on mobile, 2 cols on tablet, 3 cols on desktop */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {categoryData.map((category) => {
                        const Icon =
                            categoryIcons[category.category] || FileText;
                        const colorClasses = getCoverageColorClasses(
                            category.default_coverage,
                        );
                        const isExpanded =
                            expandedCategory === category.category;

                        return (
                            <TooltipProvider key={category.category}>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Card
                                            className={`cursor-pointer transition-all hover:shadow-lg focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 ${colorClasses.border} border-2`}
                                            onClick={() =>
                                                toggleCategory(category.category)
                                            }
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' || e.key === ' ') {
                                                    e.preventDefault();
                                                    toggleCategory(category.category);
                                                }
                                            }}
                                            tabIndex={0}
                                            role="button"
                                            aria-expanded={isExpanded}
                                            aria-label={`${categoryLabels[category.category]} category, ${category.default_coverage !== null ? `${category.default_coverage}% coverage` : 'not configured'}, ${category.exception_count} exceptions`}
                                        >
                                            <CardHeader
                                                className={`${colorClasses.bg}`}
                                            >
                                                <div className="flex items-center justify-between">
                                                    <div className="flex items-center gap-3">
                                                        <Icon
                                                            className={`h-6 w-6 ${colorClasses.text}`}
                                                        />
                                                        <CardTitle
                                                            className={`text-lg ${colorClasses.text}`}
                                                        >
                                                            {
                                                                categoryLabels[
                                                                    category
                                                                        .category
                                                                ]
                                                            }
                                                        </CardTitle>
                                                    </div>
                                                    {isExpanded ? (
                                                        <ChevronUp
                                                            className={`h-5 w-5 ${colorClasses.text}`}
                                                        />
                                                    ) : (
                                                        <ChevronDown
                                                            className={`h-5 w-5 ${colorClasses.text}`}
                                                        />
                                                    )}
                                                </div>
                                            </CardHeader>
                                            <CardContent className="pt-6">
                                                <div className="space-y-4">
                                                    {/* Coverage Percentage */}
                                                    <div
                                                        className="text-center"
                                                        onClick={(e) =>
                                                            e.stopPropagation()
                                                        }
                                                    >
                                                        {category.default_coverage !==
                                                            null &&
                                                        category.general_rule_id ? (
                                                            <div className="flex flex-col items-center gap-1">
                                                                <InlinePercentageEdit
                                                                    value={
                                                                        category.default_coverage
                                                                    }
                                                                    ruleId={
                                                                        category.general_rule_id
                                                                    }
                                                                    onSave={(
                                                                        newValue,
                                                                    ) =>
                                                                        handleCoverageUpdate(
                                                                            category.category,
                                                                            newValue,
                                                                        )
                                                                    }
                                                                    className={`text-4xl font-bold ${colorClasses.text}`}
                                                                />
                                                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                                                    Default
                                                                    Coverage
                                                                </p>
                                                            </div>
                                                        ) : (
                                                            <>
                                                                <div
                                                                    className={`text-4xl font-bold ${colorClasses.text}`}
                                                                >
                                                                    N/A
                                                                </div>
                                                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                                    Default
                                                                    Coverage
                                                                </p>
                                                            </>
                                                        )}
                                                    </div>

                                                    {/* Exception Count */}
                                                    <div className="flex items-center justify-center gap-2">
                                                        <Badge
                                                            variant={
                                                                category.exception_count >
                                                                0
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {
                                                                category.exception_count
                                                            }{' '}
                                                            Exception
                                                            {category.exception_count !==
                                                                1 && 's'}
                                                        </Badge>
                                                    </div>

                                                    {/* Expanded Content */}
                                                    {isExpanded && (
                                                        <div
                                                            className="space-y-4 border-t pt-4"
                                                            onClick={(e) =>
                                                                e.stopPropagation()
                                                            }
                                                        >
                                                            {/* Default Rule Info */}
                                                            <div className="flex items-start gap-2">
                                                                <AlertCircle className="mt-0.5 h-4 w-4 text-gray-500" />
                                                                <div className="flex-1">
                                                                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                        Default
                                                                        Rule
                                                                    </p>
                                                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                                                        {category.default_coverage !==
                                                                        null
                                                                            ? `${category.default_coverage}% coverage for all items`
                                                                            : 'No default rule set'}
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            {/* Action Buttons - Responsive: stack on very small screens */}
                                                            <div className="grid grid-cols-1 gap-2 xs:grid-cols-2">
                                                                <Button
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleAddException(
                                                                            category.category,
                                                                        )
                                                                    }
                                                                >
                                                                    <Plus className="mr-2 h-4 w-4" />
                                                                    <span className="hidden xs:inline">Add Exception</span>
                                                                    <span className="xs:hidden">Add</span>
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() => {
                                                                        setSelectedCategory(category.category);
                                                                        setImportModalOpen(true);
                                                                    }}
                                                                >
                                                                    <span className="hidden xs:inline">Import Bulk</span>
                                                                    <span className="xs:hidden">Import</span>
                                                                </Button>
                                                            </div>

                                                            {/* Exception List */}
                                                            {category.exception_count >
                                                                0 && (
                                                                <div className="space-y-2">
                                                                    <h4 className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                                        Item-Specific
                                                                        Exceptions
                                                                    </h4>
                                                                    {loadingExceptions[
                                                                        category
                                                                            .category
                                                                    ] ? (
                                                                        <div 
                                                                            className="flex items-center justify-center py-4"
                                                                            role="status"
                                                                            aria-live="polite"
                                                                            aria-busy="true"
                                                                        >
                                                                            <div className="h-6 w-6 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                                                                            <span className="sr-only">Loading exceptions...</span>
                                                                        </div>
                                                                    ) : (
                                                                        exceptions[
                                                                            category
                                                                                .category
                                                                        ] && (
                                                                            <ExceptionList
                                                                                exceptions={
                                                                                    exceptions[
                                                                                        category
                                                                                            .category
                                                                                    ]
                                                                                }
                                                                                defaultCoverage={
                                                                                    category.default_coverage
                                                                                }
                                                                                planId={
                                                                                    plan.id
                                                                                }
                                                                                category={
                                                                                    category.category
                                                                                }
                                                                            />
                                                                        )
                                                                    )}
                                                                </div>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>
                                            Click to{' '}
                                            {isExpanded ? 'collapse' : 'expand'}{' '}
                                            details
                                        </p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        );
                    })}
                </div>

                {/* Keyboard Shortcuts Help */}
                <KeyboardShortcutsHelp
                    shortcuts={[
                        { key: 'N', description: 'Add new exception' },
                        { key: 'E', description: 'Enable edit mode' },
                    ]}
                />

                {/* Recent Items Section */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-3">
                            <Clock className="h-6 w-6 text-gray-700 dark:text-gray-300" />
                            <CardTitle className="text-xl">
                                Recently Added Items (Last 30 Days)
                            </CardTitle>
                        </div>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Monitor newly added items and quickly add coverage
                            exceptions for high-cost items
                        </p>
                    </CardHeader>
                    <CardContent>
                        {loadingRecentItems ? (
                            <div 
                                className="flex items-center justify-center py-8"
                                role="status"
                                aria-live="polite"
                                aria-busy="true"
                            >
                                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-300 border-t-blue-600"></div>
                                <span className="sr-only">Loading recent items...</span>
                            </div>
                        ) : (
                            <RecentItemsPanel
                                planId={plan.id}
                                recentItems={recentItems}
                                onAddException={handleAddExceptionFromRecent}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Add Exception Modal */}
            {selectedCategory && (
                <>
                    <AddExceptionModal
                        open={modalOpen}
                        onClose={() => setModalOpen(false)}
                        planId={plan.id}
                        category={selectedCategory}
                        defaultCoverage={
                            categoryData.find(
                                (c) => c.category === selectedCategory,
                            )?.default_coverage
                        }
                        onSuccess={handleModalSuccess}
                    />
                    <BulkImportModal
                        open={importModalOpen}
                        onClose={() => setImportModalOpen(false)}
                        planId={plan.id}
                        category={selectedCategory}
                        onSuccess={handleModalSuccess}
                    />
                </>
            )}
        </AppLayout>
    );
}
