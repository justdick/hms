import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useForm } from '@inertiajs/react';
import { Loader2, Search, UserPlus } from 'lucide-react';
import React, { useEffect, useState } from 'react';
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

interface PatientSearchModalProps {
    open: boolean;
    onClose: () => void;
    onPatientSelected: (patient: Patient) => void;
}

export default function PatientSearchModal({
    open,
    onClose,
    onPatientSelected,
}: PatientSearchModalProps) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<Patient[]>([]);
    const [isSearching, setIsSearching] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        first_name: '',
        last_name: '',
        gender: '',
        date_of_birth: '',
        phone_number: '',
        address: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        national_id: '',
    });

    const searchPatients = async (query: string) => {
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        setIsSearching(true);
        try {
            const response = await fetch(
                `/checkin/patients/search?search=${encodeURIComponent(query)}`,
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

    const handleRegisterPatient = (e: React.FormEvent) => {
        e.preventDefault();

        post('/checkin/patients', {
            onSuccess: (response: any) => {
                toast.success('Patient registered successfully');
                reset();
                // The patient should be in the response
                if (response.props?.patient) {
                    onPatientSelected(response.props.patient);
                }
            },
            onError: () => {
                toast.error('Failed to register patient');
            },
        });
    };

    const handlePatientSelect = (patient: Patient) => {
        if (patient.has_checkin_today) {
            toast.error('Patient is already checked in today');
            return;
        }
        onPatientSelected(patient);
    };

    const handleModalClose = () => {
        setSearchQuery('');
        setSearchResults([]);
        reset();
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={handleModalClose}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Find or Register Patient</DialogTitle>
                    <DialogDescription>
                        Search for existing patients or register a new patient
                        for check-in.
                    </DialogDescription>
                </DialogHeader>

                <Tabs defaultValue="search" className="w-full">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="search" className="gap-2">
                            <Search className="h-4 w-4" />
                            Search Patient
                        </TabsTrigger>
                        <TabsTrigger value="register" className="gap-2">
                            <UserPlus className="h-4 w-4" />
                            Register New
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="search" className="space-y-4">
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
                                    onChange={(e) =>
                                        setSearchQuery(e.target.value)
                                    }
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
                                        No patients found matching "
                                        {searchQuery}"
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
                                                {patient.patient_number} •{' '}
                                                {patient.age} years •{' '}
                                                {patient.gender}
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
                                                <Badge variant="outline">
                                                    Available
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </TabsContent>

                    <TabsContent value="register" className="space-y-4">
                        <form
                            onSubmit={handleRegisterPatient}
                            className="space-y-4"
                        >
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="first_name">
                                        First Name *
                                    </Label>
                                    <Input
                                        id="first_name"
                                        value={data.first_name}
                                        onChange={(e) =>
                                            setData(
                                                'first_name',
                                                e.target.value,
                                            )
                                        }
                                        error={errors.first_name}
                                        required
                                    />
                                    {errors.first_name && (
                                        <p className="text-sm text-destructive">
                                            {errors.first_name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="last_name">
                                        Last Name *
                                    </Label>
                                    <Input
                                        id="last_name"
                                        value={data.last_name}
                                        onChange={(e) =>
                                            setData('last_name', e.target.value)
                                        }
                                        error={errors.last_name}
                                        required
                                    />
                                    {errors.last_name && (
                                        <p className="text-sm text-destructive">
                                            {errors.last_name}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="gender">Gender *</Label>
                                    <select
                                        id="gender"
                                        value={data.gender}
                                        onChange={(e) =>
                                            setData('gender', e.target.value)
                                        }
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                        required
                                    >
                                        <option value="">Select gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                    {errors.gender && (
                                        <p className="text-sm text-destructive">
                                            {errors.gender}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="date_of_birth">
                                        Date of Birth *
                                    </Label>
                                    <Input
                                        id="date_of_birth"
                                        type="date"
                                        value={data.date_of_birth}
                                        onChange={(e) =>
                                            setData(
                                                'date_of_birth',
                                                e.target.value,
                                            )
                                        }
                                        error={errors.date_of_birth}
                                        required
                                    />
                                    {errors.date_of_birth && (
                                        <p className="text-sm text-destructive">
                                            {errors.date_of_birth}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="phone_number">
                                        Phone Number
                                    </Label>
                                    <Input
                                        id="phone_number"
                                        value={data.phone_number}
                                        onChange={(e) =>
                                            setData(
                                                'phone_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="+255..."
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="national_id">
                                        National ID
                                    </Label>
                                    <Input
                                        id="national_id"
                                        value={data.national_id}
                                        onChange={(e) =>
                                            setData(
                                                'national_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address">Address</Label>
                                <Input
                                    id="address"
                                    value={data.address}
                                    onChange={(e) =>
                                        setData('address', e.target.value)
                                    }
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="emergency_contact_name">
                                        Emergency Contact Name
                                    </Label>
                                    <Input
                                        id="emergency_contact_name"
                                        value={data.emergency_contact_name}
                                        onChange={(e) =>
                                            setData(
                                                'emergency_contact_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="emergency_contact_phone">
                                        Emergency Contact Phone
                                    </Label>
                                    <Input
                                        id="emergency_contact_phone"
                                        value={data.emergency_contact_phone}
                                        onChange={(e) =>
                                            setData(
                                                'emergency_contact_phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleModalClose}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    )}
                                    Register & Check-in
                                </Button>
                            </div>
                        </form>
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}
