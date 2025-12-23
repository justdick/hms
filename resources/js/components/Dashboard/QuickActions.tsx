import { Link } from '@inertiajs/react';
import * as LucideIcons from 'lucide-react';
import { type LucideIcon, Zap } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface QuickAction {
    key: string;
    label: string;
    route: string;
    icon: string;
    href: string | null;
    variant?:
        | 'default'
        | 'primary'
        | 'accent'
        | 'success'
        | 'warning'
        | 'danger'
        | 'info';
}

export interface QuickActionsProps extends React.ComponentProps<typeof Card> {
    actions: QuickAction[];
    title?: string;
    columns?: 2 | 3 | 4;
}

function getIconComponent(iconName: string): LucideIcon {
    const icons = LucideIcons as unknown as Record<string, LucideIcon>;
    return icons[iconName] || LucideIcons.Circle;
}

const variantStyles: Record<string, string> = {
    default: 'hover:bg-muted text-foreground hover:text-foreground',
    primary:
        'border-primary/30 bg-primary/5 hover:bg-primary/10 text-foreground hover:text-foreground [&_svg]:text-primary',
    accent: 'border-accent/30 bg-accent/5 hover:bg-accent/10 text-foreground hover:text-foreground [&_svg]:text-accent',
    success:
        'border-success/30 bg-success/5 hover:bg-success/10 text-foreground hover:text-foreground [&_svg]:text-success',
    warning:
        'border-warning/30 bg-warning/5 hover:bg-warning/10 text-foreground hover:text-foreground [&_svg]:text-warning',
    danger: 'border-destructive/30 bg-destructive/5 hover:bg-destructive/10 text-foreground hover:text-foreground [&_svg]:text-destructive',
    info: 'border-info/30 bg-info/5 hover:bg-info/10 text-foreground hover:text-foreground [&_svg]:text-info',
};

export function QuickActions({
    actions,
    title = 'Quick Actions',
    columns = 4,
    className,
    ...props
}: QuickActionsProps) {
    if (actions.length === 0) {
        return null;
    }

    return (
        <Card className={cn('', className)} {...props}>
            <CardHeader className="pb-2 sm:pb-3">
                <div className="flex items-center gap-2">
                    <Zap className="h-5 w-5 text-primary" />
                    <CardTitle className="text-sm font-semibold sm:text-base">
                        {title}
                    </CardTitle>
                </div>
            </CardHeader>
            <CardContent className="pt-0">
                <div
                    className={cn(
                        'grid gap-3',
                        columns === 2 && 'grid-cols-2',
                        columns === 3 && 'grid-cols-2 sm:grid-cols-3',
                        columns === 4 && 'grid-cols-2 sm:grid-cols-4',
                    )}
                >
                    {actions.map((action) => {
                        const Icon = getIconComponent(action.icon);
                        const variant = action.variant || 'default';

                        if (!action.href) {
                            return null;
                        }

                        return (
                            <Button
                                key={action.key}
                                variant="outline"
                                className={cn(
                                    'flex h-auto flex-col items-center justify-center gap-2 px-3 py-4 text-center',
                                    variantStyles[variant],
                                )}
                                asChild
                            >
                                <Link href={action.href}>
                                    <Icon
                                        className="h-6 w-6 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span className="text-xs leading-tight font-medium">
                                        {action.label}
                                    </span>
                                </Link>
                            </Button>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}

/**
 * Compact horizontal quick actions for sidebar or narrow spaces
 */
export function QuickActionsCompact({
    actions,
    title = 'Quick Actions',
    className,
    ...props
}: Omit<QuickActionsProps, 'columns'>) {
    if (actions.length === 0) {
        return null;
    }

    return (
        <Card className={cn('', className)} {...props}>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-semibold">{title}</CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
                <div className="flex flex-col gap-1.5">
                    {actions.map((action) => {
                        const Icon = getIconComponent(action.icon);

                        if (!action.href) {
                            return null;
                        }

                        return (
                            <Button
                                key={action.key}
                                variant="ghost"
                                size="sm"
                                className="h-9 justify-start px-3"
                                asChild
                            >
                                <Link href={action.href}>
                                    <Icon
                                        className="h-4 w-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span className="truncate">
                                        {action.label}
                                    </span>
                                </Link>
                            </Button>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}
