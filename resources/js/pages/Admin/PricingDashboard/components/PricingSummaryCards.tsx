import { StatCard } from '@/components/ui/stat-card';
import { cn } from '@/lib/utils';
import {
    AlertTriangle,
    CheckCircle,
    CircleDollarSign,
    Link2,
    Link2Off,
    Sparkles,
} from 'lucide-react';

export interface PricingSummary {
    unpriced: number;
    priced: number;
    nhis_mapped: number;
    nhis_unmapped: number;
    flexible_copay: number;
}

interface PricingSummaryCardsProps {
    summary: PricingSummary;
    isNhis: boolean;
    onFilterClick?: (filter: string) => void;
    activeFilter?: string | null;
}

export function PricingSummaryCards({
    summary,
    isNhis,
    onFilterClick,
    activeFilter,
}: PricingSummaryCardsProps) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            {/* Priced Items */}
            <div
                onClick={() => onFilterClick?.('priced')}
                className={cn(
                    'cursor-pointer transition-all hover:scale-[1.02]',
                    activeFilter === 'priced' && 'ring-2 ring-primary ring-offset-2 rounded-lg',
                )}
            >
                <StatCard
                    label="Priced Items"
                    value={summary.priced}
                    icon={<CheckCircle className="h-5 w-5" />}
                    variant="success"
                />
            </div>

            {/* Unpriced Items */}
            <div
                onClick={() => onFilterClick?.('unpriced')}
                className={cn(
                    'cursor-pointer transition-all hover:scale-[1.02]',
                    activeFilter === 'unpriced' && 'ring-2 ring-primary ring-offset-2 rounded-lg',
                )}
            >
                <StatCard
                    label="Unpriced Items"
                    value={summary.unpriced}
                    icon={<AlertTriangle className="h-5 w-5" />}
                    variant="error"
                />
            </div>

            {/* NHIS-specific cards */}
            {isNhis && (
                <>
                    {/* NHIS Mapped */}
                    <StatCard
                        label="NHIS Mapped"
                        value={summary.nhis_mapped}
                        icon={<Link2 className="h-5 w-5" />}
                        variant="info"
                    />

                    {/* NHIS Unmapped */}
                    <StatCard
                        label="Not Mapped"
                        value={summary.nhis_unmapped}
                        icon={<Link2Off className="h-5 w-5" />}
                        variant="warning"
                    />

                    {/* Flexible Copay */}
                    <StatCard
                        label="Flexible Copay"
                        value={summary.flexible_copay}
                        icon={<Sparkles className="h-5 w-5" />}
                        variant="default"
                    />
                </>
            )}

            {/* Total for non-NHIS */}
            {!isNhis && (
                <div
                    onClick={() => onFilterClick?.('all')}
                    className={cn(
                        'cursor-pointer transition-all hover:scale-[1.02]',
                        (activeFilter === 'all' || !activeFilter) &&
                            'ring-2 ring-primary ring-offset-2 rounded-lg',
                    )}
                >
                    <StatCard
                        label="Total Items"
                        value={summary.priced + summary.unpriced}
                        icon={<CircleDollarSign className="h-5 w-5" />}
                        variant="default"
                    />
                </div>
            )}
        </div>
    );
}
