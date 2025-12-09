import { StatCard } from '@/components/ui/stat-card';
import { Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';

interface CollectionSummary {
    cashier: {
        id: number;
        name: string;
    };
    date: string;
    total_amount: number;
    transaction_count: number;
}

interface MyCollectionsCardProps {
    formatCurrency: (amount: number) => string;
    onViewDetails?: () => void;
}

export function MyCollectionsCard({
    formatCurrency,
    onViewDetails,
}: MyCollectionsCardProps) {
    const [summary, setSummary] = useState<CollectionSummary | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const fetchCollections = async () => {
        try {
            const response = await fetch('/billing/my-collections');
            const data = await response.json();
            setSummary(data.summary);
        } catch (error) {
            console.error('Failed to fetch collections:', error);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchCollections();
        // Refresh every 30 seconds
        const interval = setInterval(fetchCollections, 30000);
        return () => clearInterval(interval);
    }, []);

    if (isLoading || !summary) {
        return (
            <StatCard
                label="My Collections Today"
                value="Loading..."
                icon={<Wallet className="h-4 w-4" />}
                variant="info"
            />
        );
    }

    return (
        <div onClick={onViewDetails} className={onViewDetails ? 'cursor-pointer' : ''}>
            <StatCard
                label={`My Collections Today · ${summary.transaction_count} transaction${summary.transaction_count !== 1 ? 's' : ''}`}
                value={formatCurrency(summary.total_amount)}
                icon={<Wallet className="h-4 w-4" />}
                variant="info"
            />
        </div>
    );
}
