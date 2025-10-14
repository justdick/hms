import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
    age: number;
    gender: string;
    phone_number: string | null;
    has_checkin_today: boolean;
}

interface PatientSearchFormProps {
    onPatientSelected: (patient: Patient) => void;
    searchEndpoint?: string;
}

export default function PatientSearchForm({
    onPatientSelected,
    searchEndpoint = '/checkin/patients/search',
}: PatientSearchFormProps) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<Patient[]>([]);
    const [isSearching, setIsSearching] = useState(false);

    const searchPatients = async (query: string) => {
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        setIsSearching(true);
        try {
            const response = await fetch(
                `${searchEndpoint}?search=${encodeURIComponent(query)}`,
            );
            const result = await response.json();
            setSearchResults(result.patients || []);
        } catch (error) {
            console.error('Search error:', error);
            toast.error('Failed to search patients');
        } finally {
            setIsSearching(false);
        }
    };

    useEffect(() => {
        const timeoutId = setTimeout(() => {
            searchPatients(searchQuery);
        }, 300);

        return () => clearTimeout(timeoutId);
    }, [searchQuery]);

    const handlePatientSelect = (patient: Patient) => {
        if (patient.has_checkin_today) {
            toast.error('Patient is already checked in today');
            return;
        }
        onPatientSelected(patient);
    };

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="search">
                    Search by name, patient number, or phone
                </Label>
                <div className="relative">
                    <Search className="absolute top-3 left-3 h-4 w-4 text-muted-foreground" />
                    <Input
                        id="search"
                        placeholder="Enter patient name, number, or phone..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="pl-10"
                    />
                    {isSearching && (
                        <Loader2 className="absolute top-3 right-3 h-4 w-4 animate-spin text-muted-foreground" />
                    )}
                </div>
            </div>

            <div className="max-h-96 space-y-2 overflow-y-auto">
                {searchResults.length === 0 &&
                    searchQuery.length >= 2 &&
                    !isSearching && (
                        <div className="py-8 text-center text-muted-foreground">
                            No patients found matching "{searchQuery}"
                        </div>
                    )}

                {searchResults.map((patient) => (
                    <div
                        key={patient.id}
                        className={`cursor-pointer rounded-lg border p-4 transition-colors ${
                            patient.has_checkin_today
                                ? 'cursor-not-allowed bg-muted/50'
                                : 'hover:bg-muted/50'
                        }`}
                        onClick={() => handlePatientSelect(patient)}
                    >
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="font-medium">
                                    {patient.full_name}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {patient.patient_number} • {patient.age}{' '}
                                    years • {patient.gender}
                                </p>
                                {patient.phone_number && (
                                    <p className="text-sm text-muted-foreground">
                                        {patient.phone_number}
                                    </p>
                                )}
                            </div>
                            <div className="flex gap-2">
                                {patient.has_checkin_today && (
                                    <Badge variant="secondary">
                                        Checked in today
                                    </Badge>
                                )}
                                {!patient.has_checkin_today && (
                                    <Badge variant="outline">Available</Badge>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
