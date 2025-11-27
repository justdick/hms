import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronUp, LucideIcon } from 'lucide-react';
import { ReactNode, useState } from 'react';

/**
 * Props for the AnalyticsWidget component
 */
interface AnalyticsWidgetProps {
    /** Title displayed in the widget header */
    title: string;
    /** Description text shown below the title */
    description: string;
    /** Lucide icon component to display */
    icon: LucideIcon;
    /** Tailwind color class for the icon background */
    color: string;
    /** Summary content shown when widget is collapsed */
    summary: ReactNode;
    /** Detailed content shown when widget is expanded */
    details?: ReactNode;
    /** Whether the widget is currently loading data */
    isLoading?: boolean;
    /** Callback function triggered when widget is expanded (for lazy loading) */
    onExpand?: () => void;
}

/**
 * AnalyticsWidget - Expandable widget component for displaying analytics data
 *
 * Features:
 * - Collapsible/expandable interface
 * - Lazy loading support via onExpand callback
 * - Skeleton loading states
 * - Keyboard navigation support (Enter/Space to toggle)
 * - Accessibility compliant with ARIA attributes
 *
 * @example
 * ```tsx
 * <AnalyticsWidget
 *   title="Claims Summary"
 *   description="Overview of all claims"
 *   icon={FileText}
 *   color="text-blue-600"
 *   summary={<div>Total: 150 claims</div>}
 *   details={<ClaimsTable />}
 *   onExpand={loadClaimsData}
 * />
 * ```
 */
export default function AnalyticsWidget({
    title,
    description,
    icon: Icon,
    color,
    summary,
    details,
    isLoading = false,
    onExpand,
}: AnalyticsWidgetProps) {
    const [isExpanded, setIsExpanded] = useState(false);

    const handleToggle = () => {
        const newExpandedState = !isExpanded;
        setIsExpanded(newExpandedState);

        // Call onExpand callback when expanding (for lazy loading)
        if (newExpandedState && onExpand) {
            onExpand();
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            handleToggle();
        }
    };

    return (
        <Card className="transition-all focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 hover:shadow-md dark:hover:shadow-primary/10">
            <CardHeader>
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <div
                            className={cn('rounded-lg bg-muted p-3', color)}
                            aria-hidden="true"
                        >
                            <Icon className="h-6 w-6" />
                        </div>
                        <div>
                            <CardTitle className="text-lg">{title}</CardTitle>
                            <CardDescription className="mt-1">
                                {description}
                            </CardDescription>
                        </div>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleToggle}
                        onKeyDown={handleKeyDown}
                        className="ml-2"
                        aria-expanded={isExpanded}
                        aria-label={`${isExpanded ? 'Collapse' : 'Expand'} ${title} widget`}
                    >
                        {isExpanded ? (
                            <ChevronUp className="h-4 w-4" aria-hidden="true" />
                        ) : (
                            <ChevronDown
                                className="h-4 w-4"
                                aria-hidden="true"
                            />
                        )}
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                {/* Summary - Always visible */}
                <div className="space-y-4">{summary}</div>

                {/* Details - Shown when expanded */}
                {isExpanded && (
                    <div className="mt-6 border-t pt-6 dark:border-gray-700">
                        {isLoading ? (
                            <div className="space-y-3">
                                <Skeleton className="h-4 w-full" />
                                <Skeleton className="h-4 w-3/4" />
                                <Skeleton className="h-4 w-5/6" />
                                <Skeleton className="h-32 w-full" />
                            </div>
                        ) : (
                            details
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
