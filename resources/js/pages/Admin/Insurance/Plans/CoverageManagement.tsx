import {
    AddExceptionModal,
    ExceptionTableModal,
    HelpTooltip,
    InlinePercentageEdit,
    SuccessMessage,
} from '@/components/Insurance';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { cache, TTL } from '@/lib/cache';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Bed,
    CheckCircle2,
    Download,
    FileText,
    Heart,
    HelpCircle,
    List,
    Pill,
    Plus,
    Stethoscope,
    TestTube,
    Upload,
    XCircle,
} from 'lucide-react';
import { lazy, Suspense, useState } from 'react';

// Lazy load BulkImportModal for better performance
const BulkImportModal = lazy(
    () => import('@/components/Insurance/BulkImportModal'),
);

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

export default function CoverageManagement({
    plan: planWrapper,
    categories,
}: Props) {
    const plan = planWrapper.data;
    const [categoryData, setCategoryData] =
        useState<CategoryData[]>(categories);
    const [exceptions, setExceptions] = useState<
        Record<string, CoverageException[]>
    >({});
    const [loadingExceptions, setLoadingExceptions] = useState<
        Record<string, boolean>
    >({});
    const [addModalOpen, setAddModalOpen] = useState(false);
    const [tableModalOpen, setTableModalOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<string | null>(
        null,
    );
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

    const getCoverageIcon = (coverage: number | null) => {
        if (coverage === null) return HelpCircle;
        if (coverage >= 80) return CheckCircle2;
        if (coverage >= 50) return AlertTriangle;
        return XCircle;
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

    const loadExceptions = async (category: string) => {
        // Check cache first
        const cacheKey = `coverage-exceptions-${plan.id}-${category}`;
        const cachedData = cache.get<CoverageException[]>(cacheKey);

        if (cachedData) {
            setExceptions((prev) => ({
                ...prev,
                [category]: cachedData,
            }));
            return;
        }

        setLoadingExceptions((prev) => ({ ...prev, [category]: true }));
        try {
            const response = await axios.get(
                `/admin/insurance/plans/${plan.id}/coverage/${category}/exceptions`,
            );
            const exceptionsData = response.data.exceptions;

            // Cache the data for 5 minutes
            cache.set(cacheKey, exceptionsData, TTL.FIVE_MINUTES);

            setExceptions((prev) => ({
                ...prev,
                [category]: exceptionsData,
            }));
        } catch (error) {
            console.error('Failed to load exceptions:', error);
        } finally {
            setLoadingExceptions((prev) => ({ ...prev, [category]: false }));
        }
    };

    const handleViewExceptions = async (category: string) => {
        setSelectedCategory(category);
        if (!exceptions[category]) {
            await loadExceptions(category);
        }
        setTableModalOpen(true);
    };

    const handleAddException = (category: string) => {
        setSelectedCategory(category);
        setAddModalOpen(true);
    };

    const handleModalSuccess = () => {
        // Invalidate cache for the selected category
        if (selectedCategory) {
            const cacheKey = `coverage-exceptions-${plan.id}-${selectedCategory}`;
            cache.invalidate(cacheKey);

            // Reload exceptions for the selected category
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
                nextSteps:
                    'The exception is now active and will be used for billing.',
            });
        }
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
                { title: 'Coverage Management', href: '' },
            ]}
        >
            <Head title={`${plan.plan_name} - Coverage Management`} />

            {/* Skip to main content link for keyboard navigation */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded focus:bg-blue-600 focus:px-4 focus:py-2 focus:text-white focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
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
                                <span className="hidden sm:inline">
                                    Back to Plan
                                </span>
                                <span className="sm:hidden">Back</span>
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900 sm:text-3xl dark:text-gray-100">
                                    <FileText className="h-6 w-6 sm:h-8 sm:w-8" />
                                    <span className="hidden sm:inline">
                                        Coverage Management
                                    </span>
                                    <span className="sm:hidden">Coverage</span>
                                </h1>
                                <HelpTooltip
                                    content="Manage coverage rules, exceptions, and tariffs for all service categories. Click on any category card to see details and add exceptions."
                                    side="right"
                                />
                            </div>
                            <p className="mt-1 text-sm text-gray-600 sm:text-base dark:text-gray-400">
                                {plan.plan_name} - {plan.provider?.name}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                // No default - user must select category
                                setSelectedCategory(null);
                                setImportModalOpen(true);
                            }}
                        >
                            <Upload className="mr-2 h-4 w-4" />
                            <span className="hidden sm:inline">
                                Bulk Import
                            </span>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                window.location.href = `/admin/insurance/plans/${plan.id}/coverage-rules/export?include_history=true`;
                            }}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            <span className="hidden sm:inline">Export</span>
                        </Button>
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
                                        Get Started with Coverage Rules
                                    </h3>
                                    <p className="mt-1 text-sm text-blue-800 dark:text-blue-200">
                                        Click on any category card below to set
                                        the default coverage percentage. You can
                                        then add exceptions for specific items
                                        that need different coverage.
                                    </p>
                                    <p className="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                        <span className="font-medium">
                                            Common coverage ranges:
                                        </span>{' '}
                                        Consultations (70-80%), Drugs & Labs
                                        (80-90%), Procedures (60-80%), Ward &
                                        Nursing (90-100%)
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
                        const StatusIcon = getCoverageIcon(
                            category.default_coverage,
                        );
                        const colorClasses = getCoverageColorClasses(
                            category.default_coverage,
                        );

                        return (
                            <Card
                                key={category.category}
                                className={`transition-all hover:shadow-lg ${colorClasses.border} border-2`}
                            >
                                <CardHeader className={`${colorClasses.bg}`}>
                                    <div className="flex items-center gap-3">
                                        <div className="relative">
                                            <Icon
                                                className={`h-6 w-6 ${colorClasses.text}`}
                                            />
                                            <StatusIcon
                                                className={`absolute -right-1 -bottom-1 h-3 w-3 ${colorClasses.text}`}
                                                aria-label={`Coverage status: ${getCoverageColor(category.default_coverage)}`}
                                            />
                                        </div>
                                        <CardTitle
                                            className={`text-lg ${colorClasses.text}`}
                                        >
                                            {categoryLabels[category.category]}
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-6">
                                    <div className="space-y-4">
                                        {/* Coverage Percentage */}
                                        <div
                                            className="text-center"
                                            onClick={(e) => e.stopPropagation()}
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
                                                        onSave={(newValue) =>
                                                            handleCoverageUpdate(
                                                                category.category,
                                                                newValue,
                                                            )
                                                        }
                                                        className={`text-4xl font-bold ${colorClasses.text}`}
                                                    />
                                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                                        Default Coverage
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
                                                        Default Coverage
                                                    </p>
                                                </>
                                            )}
                                        </div>

                                        {/* Exception Count */}
                                        <div className="flex items-center justify-center gap-2">
                                            <Badge
                                                variant={
                                                    category.exception_count > 0
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                                className={
                                                    category.exception_count > 0
                                                        ? 'bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200'
                                                        : ''
                                                }
                                            >
                                                <span className="font-semibold">
                                                    {category.exception_count}
                                                </span>{' '}
                                                Exception
                                                {category.exception_count !==
                                                    1 && 's'}
                                            </Badge>
                                        </div>

                                        {/* Action Buttons */}
                                        <div className="flex gap-2 border-t pt-4">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    handleViewExceptions(
                                                        category.category,
                                                    )
                                                }
                                                className="flex-1"
                                                disabled={
                                                    category.exception_count ===
                                                    0
                                                }
                                            >
                                                <List className="mr-2 h-4 w-4" />
                                                View{' '}
                                                {category.exception_count > 0
                                                    ? `(${category.exception_count})`
                                                    : ''}
                                            </Button>
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    handleAddException(
                                                        category.category,
                                                    )
                                                }
                                                className="flex-1"
                                            >
                                                <Plus className="mr-2 h-4 w-4" />
                                                Add
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>

            {/* Add Exception Modal */}
            {selectedCategory && (
                <AddExceptionModal
                    open={addModalOpen}
                    onClose={() => setAddModalOpen(false)}
                    planId={plan.id}
                    category={selectedCategory}
                    defaultCoverage={
                        categoryData.find(
                            (c) => c.category === selectedCategory,
                        )?.default_coverage
                    }
                    onSuccess={handleModalSuccess}
                />
            )}

            {/* Exception Table Modal */}
            {selectedCategory && tableModalOpen && (
                <ExceptionTableModal
                    open={tableModalOpen}
                    onClose={() => setTableModalOpen(false)}
                    category={selectedCategory}
                    categoryLabel={categoryLabels[selectedCategory]}
                    exceptions={exceptions[selectedCategory] || []}
                    planId={plan.id}
                />
            )}

            {/* Bulk Import Modal */}
            {importModalOpen && (
                <Suspense
                    fallback={
                        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                            <div className="rounded-lg bg-white p-6 dark:bg-gray-900">
                                <Skeleton className="mb-4 h-8 w-64" />
                                <Skeleton className="h-32 w-96" />
                            </div>
                        </div>
                    }
                >
                    <BulkImportModal
                        open={importModalOpen}
                        onClose={() => setImportModalOpen(false)}
                        planId={plan.id}
                        category={selectedCategory || ''}
                        onSuccess={handleModalSuccess}
                        onCategoryChange={(newCategory) =>
                            setSelectedCategory(newCategory)
                        }
                    />
                </Suspense>
            )}
        </AppLayout>
    );
}
