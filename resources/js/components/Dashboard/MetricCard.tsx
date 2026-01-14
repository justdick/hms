import { Link } from '@inertiajs/react';
import { cva, type VariantProps } from 'class-variance-authority';
import { type LucideIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

const metricCardVariants = cva(
    'flex items-center gap-3 sm:gap-4 rounded-xl border p-3 sm:p-4 shadow-sm transition-colors',
    {
        variants: {
            variant: {
                default: 'bg-primary/10 text-card-foreground border-primary/20',
                primary: 'bg-primary/10 text-card-foreground border-primary/20',
                accent: 'bg-accent/10 text-card-foreground border-accent/20',
                success: 'bg-success/10 text-card-foreground border-success/20',
                warning: 'bg-warning/15 text-card-foreground border-warning/30',
                danger: 'bg-destructive/15 text-card-foreground border-destructive/30',
                info: 'bg-info/10 text-card-foreground border-info/20',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

const metricIconVariants = cva(
    'flex h-10 w-10 sm:h-12 sm:w-12 shrink-0 items-center justify-center rounded-lg',
    {
        variants: {
            variant: {
                default: 'bg-primary/20 text-primary',
                primary: 'bg-primary/20 text-primary',
                accent: 'bg-accent/20 text-accent',
                success: 'bg-success/20 text-success',
                warning: 'bg-warning/20 text-warning',
                danger: 'bg-destructive/20 text-destructive',
                info: 'bg-info/20 text-info',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

export interface MetricCardProps
    extends
        React.ComponentProps<'div'>,
        VariantProps<typeof metricCardVariants> {
    title: string;
    value: number | string;
    icon: LucideIcon;
    href?: string;
}

export function MetricCard({
    className,
    variant,
    title,
    value,
    icon: Icon,
    href,
    ...props
}: MetricCardProps) {
    const content = (
        <>
            <div className={cn(metricIconVariants({ variant }))}>
                <Icon className="h-5 w-5 sm:h-6 sm:w-6" aria-hidden="true" />
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-0.5 sm:gap-1">
                <span className="truncate text-xs font-medium text-muted-foreground sm:text-sm">
                    {title}
                </span>
                <span className="text-xl leading-none font-bold tracking-tight sm:text-2xl">
                    {value}
                </span>
            </div>
        </>
    );

    const cardClasses = cn(
        metricCardVariants({ variant }),
        href && 'cursor-pointer hover:bg-accent/50',
        className,
    );

    if (href) {
        return (
            <Link
                href={href}
                className={cardClasses}
                aria-label={`${title}: ${value}`}
                {...(props as React.ComponentProps<typeof Link>)}
            >
                {content}
            </Link>
        );
    }

    return (
        <div
            className={cardClasses}
            role="status"
            aria-label={`${title}: ${value}`}
            {...props}
        >
            {content}
        </div>
    );
}

export { metricCardVariants, metricIconVariants };
