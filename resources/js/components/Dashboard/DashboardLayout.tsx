import * as React from 'react';

import { cn } from '@/lib/utils';

export interface DashboardLayoutProps extends React.ComponentProps<'div'> {
    children: React.ReactNode;
}

export function DashboardLayout({
    children,
    className,
    ...props
}: DashboardLayoutProps) {
    return (
        <div className={cn('flex flex-col gap-4 sm:gap-6', className)} {...props}>
            {children}
        </div>
    );
}

export interface DashboardMetricsGridProps extends React.ComponentProps<'div'> {
    children: React.ReactNode;
    columns?: 2 | 3 | 4;
}

export function DashboardMetricsGrid({
    children,
    columns = 3,
    className,
    ...props
}: DashboardMetricsGridProps) {
    return (
        <div
            className={cn(
                'grid gap-3 sm:gap-4',
                {
                    'grid-cols-1 sm:grid-cols-2': columns === 2,
                    'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3': columns === 3,
                    'grid-cols-2 sm:grid-cols-2 lg:grid-cols-4': columns === 4,
                },
                className
            )}
            {...props}
        >
            {children}
        </div>
    );
}

export interface DashboardContentGridProps extends React.ComponentProps<'div'> {
    children: React.ReactNode;
}

export function DashboardContentGrid({
    children,
    className,
    ...props
}: DashboardContentGridProps) {
    return (
        <div
            className={cn(
                'grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
                className
            )}
            {...props}
        >
            {children}
        </div>
    );
}

export interface DashboardMainContentProps extends React.ComponentProps<'div'> {
    children: React.ReactNode;
}

export function DashboardMainContent({
    children,
    className,
    ...props
}: DashboardMainContentProps) {
    return (
        <div className={cn('col-span-1 md:col-span-2', className)} {...props}>
            {children}
        </div>
    );
}

export interface DashboardSideContentProps extends React.ComponentProps<'div'> {
    children?: React.ReactNode;
}

export function DashboardSideContent({
    children,
    className,
    ...props
}: DashboardSideContentProps) {
    if (!children) {
        return null;
    }

    return (
        <div className={cn('col-span-1', className)} {...props}>
            {children}
        </div>
    );
}
