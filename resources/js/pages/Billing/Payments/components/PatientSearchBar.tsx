import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface PatientSearchBarProps {
    onSearch: (query: string) => void;
    isSearching: boolean;
}

export function PatientSearchBar({
    onSearch,
    isSearching,
}: PatientSearchBarProps) {
    const [query, setQuery] = useState('');

    useEffect(() => {
        const timeoutId = setTimeout(() => {
            onSearch(query);
        }, 300);

        return () => clearTimeout(timeoutId);
    }, [query, onSearch]);

    return (
        <div className="space-y-2">
            <Label htmlFor="patient-search">
                Search by name, patient number, or phone
            </Label>
            <div className="relative">
                <Search className="absolute top-3 left-3 h-4 w-4 text-muted-foreground" />
                <Input
                    id="patient-search"
                    placeholder="Enter patient name, number, or phone..."
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    className="pl-10"
                />
                {isSearching && (
                    <Loader2 className="absolute top-3 right-3 h-4 w-4 animate-spin text-muted-foreground" />
                )}
            </div>
        </div>
    );
}
