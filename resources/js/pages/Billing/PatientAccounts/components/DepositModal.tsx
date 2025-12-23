import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { Loader2, Search, User } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface PatientResult {
    id: number;
    full_name: string;
    patient_number: string;
    phone_number: string;
    account_balance: number;
    credit_limit: number;
    available_balance: number;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    paymentMethods: PaymentMethod[];
    formatCurrency: (amount: number | string) => string;
    preselectedPatient?: PatientResult;
}

export function DepositModal({
    isOpen,
    onClose,
    paymentMethods,
    formatCurrency,
    preselectedPatient,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<PatientResult[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [selectedPatient, setSelectedPatient] =
        useState<PatientResult | null>(preselectedPatient || null);

    // Restore preselected patient when modal opens
    useEffect(() => {
        if (isOpen && preselectedPatient) {
            setSelectedPatient(preselectedPatient);
        }
    }, [isOpen, preselectedPatient]);

    const [amount, setAmount] = useState('');
    const [paymentMethodId, setPaymentMethodId] = useState('');
    const [paymentReference, setPaymentReference] = useState('');
    const [notes, setNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const searchPatients = useCallback(async (query: string) => {
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        setIsSearching(true);
        try {
            const response = await fetch(
                `/billing/patient-accounts/search-patients?search=${encodeURIComponent(query)}`,
            );
            const data = await response.json();
            setSearchResults(data.patients || []);
        } catch (error) {
            console.error('Search error:', error);
            setSearchResults([]);
        } finally {
            setIsSearching(false);
        }
    }, []);

    const handlePatientSelect = (patient: PatientResult) => {
        setSelectedPatient(patient);
        setSearchResults([]);
        setSearchQuery('');
    };

    const handleSubmit = () => {
        setErrors({});

        if (!selectedPatient) {
            setErrors({ patient_id: 'Please select a patient' });
            return;
        }

        if (!amount || Number(amount) < 1) {
            setErrors({
                amount: 'Please enter a valid amount (minimum GHS 1.00)',
            });
            return;
        }

        if (!paymentMethodId) {
            setErrors({ payment_method_id: 'Please select a payment method' });
            return;
        }

        setIsSubmitting(true);

        router.post(
            '/billing/patient-accounts/deposit',
            {
                patient_id: selectedPatient.id,
                amount: Number(amount),
                payment_method_id: Number(paymentMethodId),
                payment_reference: paymentReference || null,
                notes: notes || null,
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
        setSelectedPatient(null);
        setSearchQuery('');
        setSearchResults([]);
        setAmount('');
        setPaymentMethodId('');
        setPaymentReference('');
        setNotes('');
        setErrors({});
    };

    const handleClose = () => {
        resetForm();
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>New Deposit</DialogTitle>
                    <DialogDescription>
                        Add funds to a patient's account
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* Patient Search */}
                    {!selectedPatient ? (
                        <div className="space-y-2">
                            <Label>Search Patient</Label>
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <Input
                                    placeholder="Search by name, patient number, or phone..."
                                    value={searchQuery}
                                    onChange={(e) => {
                                        setSearchQuery(e.target.value);
                                        searchPatients(e.target.value);
                                    }}
                                    className="pl-10"
                                />
                            </div>
                            {errors.patient_id && (
                                <p className="text-sm text-red-500">
                                    {errors.patient_id}
                                </p>
                            )}

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
                                            className="w-full border-b px-4 py-3 text-left last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-800"
                                            onClick={() =>
                                                handlePatientSelect(patient)
                                            }
                                        >
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <div className="font-medium">
                                                        {patient.full_name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {patient.patient_number}{' '}
                                                        • {patient.phone_number}
                                                    </div>
                                                </div>
                                                <div className="text-right text-sm">
                                                    <div
                                                        className={
                                                            patient.account_balance >=
                                                            0
                                                                ? 'text-green-600'
                                                                : 'text-red-600'
                                                        }
                                                    >
                                                        {formatCurrency(
                                                            patient.account_balance,
                                                        )}
                                                    </div>
                                                    {patient.credit_limit >
                                                        0 && (
                                                        <div className="text-xs text-gray-500">
                                                            Credit:{' '}
                                                            {formatCurrency(
                                                                patient.credit_limit,
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="rounded-lg border bg-gray-50 p-4 dark:bg-gray-800">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                        <User className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div className="font-medium">
                                            {selectedPatient.full_name}
                                        </div>
                                        <div className="text-sm text-gray-500">
                                            {selectedPatient.patient_number}
                                        </div>
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
                            <div className="mt-2 flex gap-4 text-sm">
                                <div>
                                    <span className="text-gray-500">
                                        Balance:{' '}
                                    </span>
                                    <span
                                        className={
                                            selectedPatient.account_balance >= 0
                                                ? 'text-green-600'
                                                : 'text-red-600'
                                        }
                                    >
                                        {formatCurrency(
                                            selectedPatient.account_balance,
                                        )}
                                    </span>
                                </div>
                                {selectedPatient.credit_limit > 0 && (
                                    <div>
                                        <span className="text-gray-500">
                                            Credit:{' '}
                                        </span>
                                        <span className="text-blue-600">
                                            {formatCurrency(
                                                selectedPatient.credit_limit,
                                            )}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Amount */}
                    <div className="space-y-2">
                        <Label htmlFor="amount">Amount (GHS)</Label>
                        <Input
                            id="amount"
                            type="number"
                            min="1"
                            step="0.01"
                            placeholder="0.00"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                        />
                        {errors.amount && (
                            <p className="text-sm text-red-500">
                                {errors.amount}
                            </p>
                        )}
                    </div>

                    {/* Payment Method */}
                    <div className="space-y-2">
                        <Label htmlFor="payment_method">Payment Method</Label>
                        <Select
                            value={paymentMethodId}
                            onValueChange={setPaymentMethodId}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select payment method" />
                            </SelectTrigger>
                            <SelectContent>
                                {paymentMethods.map((method) => (
                                    <SelectItem
                                        key={method.id}
                                        value={String(method.id)}
                                    >
                                        {method.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.payment_method_id && (
                            <p className="text-sm text-red-500">
                                {errors.payment_method_id}
                            </p>
                        )}
                    </div>

                    {/* Payment Reference */}
                    <div className="space-y-2">
                        <Label htmlFor="payment_reference">
                            Payment Reference (Optional)
                        </Label>
                        <Input
                            id="payment_reference"
                            placeholder="e.g., Transaction ID, Cheque number"
                            value={paymentReference}
                            onChange={(e) =>
                                setPaymentReference(e.target.value)
                            }
                        />
                    </div>

                    {/* Notes */}
                    <div className="space-y-2">
                        <Label htmlFor="notes">Notes (Optional)</Label>
                        <Textarea
                            id="notes"
                            placeholder="Any additional notes..."
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={2}
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={handleClose}
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
                                Processing...
                            </>
                        ) : (
                            'Record Deposit'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
