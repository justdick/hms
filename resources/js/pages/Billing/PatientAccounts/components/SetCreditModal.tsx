import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { Infinity, Loader2, Search, User } from 'lucide-react';
import { useEffect, useState } from 'react';

interface PatientResult {
    id: number;
    full_name: string;
    patient_number: string;
    phone_number: string | null;
    account_balance: number;
    credit_limit: number;
    available_balance: number;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    formatCurrency: (amount: number | string) => string;
}

const UNLIMITED_CREDIT_VALUE = 999999999;

export function SetCreditModal({ isOpen, onClose, formatCurrency }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<PatientResult[]>([]);
    const [selectedPatient, setSelectedPatient] =
        useState<PatientResult | null>(null);
    const [isSearching, setIsSearching] = useState(false);
    const [creditLimit, setCreditLimit] = useState('');
    const [isUnlimited, setIsUnlimited] = useState(false);
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (!isOpen) {
            resetForm();
        }
    }, [isOpen]);

    useEffect(() => {
        const searchPatients = async () => {
            if (searchQuery.length < 2) {
                setSearchResults([]);
                return;
            }

            setIsSearching(true);
            try {
                const response = await fetch(
                    `/billing/patient-accounts/search-patients?search=${encodeURIComponent(searchQuery)}`,
                );
                const data = await response.json();
                setSearchResults(data.patients || []);
            } catch (error) {
                console.error('Search failed:', error);
            } finally {
                setIsSearching(false);
            }
        };

        const debounce = setTimeout(searchPatients, 300);
        return () => clearTimeout(debounce);
    }, [searchQuery]);

    const handleSelectPatient = (patient: PatientResult) => {
        setSelectedPatient(patient);
        setSearchQuery('');
        setSearchResults([]);
        const isCurrentlyUnlimited =
            patient.credit_limit >= UNLIMITED_CREDIT_VALUE;
        setIsUnlimited(isCurrentlyUnlimited);
        setCreditLimit(
            isCurrentlyUnlimited ? '0' : String(patient.credit_limit),
        );
    };

    const handleSubmit = () => {
        if (!selectedPatient) return;

        setErrors({});
        setIsSubmitting(true);

        const finalCreditLimit = isUnlimited
            ? UNLIMITED_CREDIT_VALUE
            : Number(creditLimit);

        router.post(
            `/billing/patient-accounts/patient/${selectedPatient.id}/credit-limit`,
            {
                credit_limit: finalCreditLimit,
                reason: reason || null,
            },
            {
                onSuccess: () => {
                    resetForm();
                    onClose();
                },
                onError: (errors) => {
                    setErrors(errors as Record<string, string>);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    const resetForm = () => {
        setSearchQuery('');
        setSearchResults([]);
        setSelectedPatient(null);
        setCreditLimit('');
        setIsUnlimited(false);
        setReason('');
        setErrors({});
    };

    const handleUnlimitedChange = (checked: boolean) => {
        setIsUnlimited(checked);
        if (checked) {
            setCreditLimit('0');
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Set Credit Limit</DialogTitle>
                    <DialogDescription>
                        Search for a patient and set their credit limit
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {!selectedPatient ? (
                        <div className="space-y-2">
                            <Label>Search Patient</Label>
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <Input
                                    placeholder="Search by name, patient number, or phone..."
                                    value={searchQuery}
                                    onChange={(e) =>
                                        setSearchQuery(e.target.value)
                                    }
                                    className="pl-10"
                                />
                            </div>
                            {isSearching && (
                                <div className="flex items-center justify-center py-4">
                                    <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
                                </div>
                            )}
                            {searchResults.length > 0 && (
                                <div className="max-h-48 overflow-y-auto rounded-md border">
                                    {searchResults.map((patient) => (
                                        <button
                                            key={patient.id}
                                            type="button"
                                            className="w-full border-b px-3 py-2 text-left last:border-b-0 hover:bg-gray-100 dark:hover:bg-gray-800"
                                            onClick={() =>
                                                handleSelectPatient(patient)
                                            }
                                        >
                                            <div className="flex items-center gap-2">
                                                <User className="h-4 w-4 text-gray-400" />
                                                <div>
                                                    <div className="font-medium">
                                                        {patient.full_name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {patient.patient_number}{' '}
                                                        •{' '}
                                                        {patient.phone_number ||
                                                            'No phone'}
                                                    </div>
                                                </div>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                            {searchQuery.length >= 2 &&
                                !isSearching &&
                                searchResults.length === 0 && (
                                    <p className="py-2 text-center text-sm text-gray-500">
                                        No patients found
                                    </p>
                                )}
                        </div>
                    ) : (
                        <>
                            <div className="rounded-lg border bg-gray-50 p-4 dark:bg-gray-800">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="font-medium">
                                            {selectedPatient.full_name}
                                        </div>
                                        <div className="text-sm text-gray-500">
                                            {selectedPatient.patient_number}
                                        </div>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setSelectedPatient(null)}
                                    >
                                        Change
                                    </Button>
                                </div>
                                <div className="mt-2 text-sm">
                                    <span className="text-gray-500">
                                        Current Credit:{' '}
                                    </span>
                                    <span className="font-medium text-blue-600">
                                        {selectedPatient.credit_limit >=
                                        UNLIMITED_CREDIT_VALUE
                                            ? 'Unlimited'
                                            : formatCurrency(
                                                  selectedPatient.credit_limit,
                                              )}
                                    </span>
                                </div>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="unlimited"
                                    checked={isUnlimited}
                                    onCheckedChange={handleUnlimitedChange}
                                />
                                <Label
                                    htmlFor="unlimited"
                                    className="flex cursor-pointer items-center gap-2"
                                >
                                    <Infinity className="h-4 w-4" />
                                    Unlimited Credit
                                </Label>
                            </div>

                            {!isUnlimited && (
                                <div className="space-y-2">
                                    <Label htmlFor="credit_limit">
                                        Credit Limit (GHS)
                                    </Label>
                                    <Input
                                        id="credit_limit"
                                        type="number"
                                        min="0"
                                        step="100"
                                        placeholder="0.00"
                                        value={creditLimit}
                                        onChange={(e) =>
                                            setCreditLimit(e.target.value)
                                        }
                                    />
                                    <p className="text-sm text-gray-500">
                                        Set to 0 to remove credit privileges
                                    </p>
                                    {errors.credit_limit && (
                                        <p className="text-sm text-red-500">
                                            {errors.credit_limit}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="reason">
                                    Reason (Optional)
                                </Label>
                                <Textarea
                                    id="reason"
                                    placeholder="Explain why this credit limit is being set..."
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                    rows={2}
                                />
                                {errors.reason && (
                                    <p className="text-sm text-red-500">
                                        {errors.reason}
                                    </p>
                                )}
                            </div>
                        </>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={onClose}
                        disabled={isSubmitting}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={isSubmitting || !selectedPatient}
                    >
                        {isSubmitting ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Saving...
                            </>
                        ) : (
                            'Save Credit Limit'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
