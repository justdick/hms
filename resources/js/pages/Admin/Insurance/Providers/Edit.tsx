import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Shield } from 'lucide-react';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
    contact_person?: string;
    phone?: string;
    email?: string;
    address?: string;
    claim_submission_method?: string;
    payment_terms_days?: number;
    notes?: string;
}

interface Props {
    provider: InsuranceProvider;
}

export default function InsuranceProviderEdit({ provider }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: provider.name || '',
        code: provider.code || '',
        contact_person: provider.contact_person || '',
        phone: provider.phone || '',
        email: provider.email || '',
        address: provider.address || '',
        claim_submission_method: provider.claim_submission_method || 'email',
        payment_terms_days: provider.payment_terms_days || 30,
        notes: provider.notes || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/insurance/providers/${provider.id}`);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                {
                    title: 'Insurance Providers',
                    href: '/admin/insurance/providers',
                },
                {
                    title: provider.name,
                    href: `/admin/insurance/providers/${provider.id}`,
                },
                { title: 'Edit', href: '' },
            ]}
        >
            <Head title={`Edit ${provider.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href={`/admin/insurance/providers/${provider.id}`}>
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Provider
                        </Button>
                    </Link>
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Shield className="h-8 w-8" />
                            Edit Insurance Provider
                        </h1>
                        <p className="mt-1 text-gray-600 dark:text-gray-400">
                            Update provider information
                        </p>
                    </div>
                </div>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle>Provider Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Basic Information */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="name">
                                        Provider Name *
                                    </Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder="e.g., National Health Insurance"
                                        required
                                        className="mt-1"
                                    />
                                    {errors.name && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="code">
                                        Provider Code *
                                    </Label>
                                    <Input
                                        id="code"
                                        type="text"
                                        value={data.code}
                                        onChange={(e) =>
                                            setData(
                                                'code',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="e.g., NHIS"
                                        maxLength={20}
                                        required
                                        className="mt-1"
                                    />
                                    {errors.code && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.code}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Contact Information */}
                            <div className="space-y-4 border-t pt-4 dark:border-gray-700">
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Contact Information
                                </h3>

                                <div>
                                    <Label htmlFor="contact_person">
                                        Contact Person
                                    </Label>
                                    <Input
                                        id="contact_person"
                                        type="text"
                                        value={data.contact_person}
                                        onChange={(e) =>
                                            setData(
                                                'contact_person',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g., John Doe"
                                        className="mt-1"
                                    />
                                    {errors.contact_person && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.contact_person}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="phone">Phone</Label>
                                        <Input
                                            id="phone"
                                            type="tel"
                                            value={data.phone}
                                            onChange={(e) =>
                                                setData('phone', e.target.value)
                                            }
                                            placeholder="e.g., +1234567890"
                                            className="mt-1"
                                        />
                                        {errors.phone && (
                                            <p className="mt-1 text-sm text-red-600">
                                                {errors.phone}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) =>
                                                setData('email', e.target.value)
                                            }
                                            placeholder="e.g., info@provider.com"
                                            className="mt-1"
                                        />
                                        {errors.email && (
                                            <p className="mt-1 text-sm text-red-600">
                                                {errors.email}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="address">Address</Label>
                                    <Textarea
                                        id="address"
                                        value={data.address}
                                        onChange={(e) =>
                                            setData('address', e.target.value)
                                        }
                                        placeholder="Provider's physical address..."
                                        rows={3}
                                        className="mt-1"
                                    />
                                    {errors.address && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.address}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Claim & Payment Settings */}
                            <div className="space-y-4 border-t pt-4 dark:border-gray-700">
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Claim & Payment Settings
                                </h3>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="claim_submission_method">
                                            Claim Submission Method
                                        </Label>
                                        <Select
                                            value={data.claim_submission_method}
                                            onValueChange={(value) =>
                                                setData(
                                                    'claim_submission_method',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="mt-1">
                                                <SelectValue placeholder="Select method" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="email">
                                                    Email
                                                </SelectItem>
                                                <SelectItem value="portal">
                                                    Online Portal
                                                </SelectItem>
                                                <SelectItem value="manual">
                                                    Manual/Physical
                                                </SelectItem>
                                                <SelectItem value="edi">
                                                    EDI
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.claim_submission_method && (
                                            <p className="mt-1 text-sm text-red-600">
                                                {errors.claim_submission_method}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <Label htmlFor="payment_terms_days">
                                            Payment Terms (Days)
                                        </Label>
                                        <Input
                                            id="payment_terms_days"
                                            type="number"
                                            value={data.payment_terms_days}
                                            onChange={(e) =>
                                                setData(
                                                    'payment_terms_days',
                                                    parseInt(e.target.value) ||
                                                        0,
                                                )
                                            }
                                            min={0}
                                            max={365}
                                            className="mt-1"
                                        />
                                        {errors.payment_terms_days && (
                                            <p className="mt-1 text-sm text-red-600">
                                                {errors.payment_terms_days}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Additional Notes */}
                            <div className="border-t pt-4 dark:border-gray-700">
                                <Label htmlFor="notes">Notes (Optional)</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                    placeholder="Any additional notes or information..."
                                    rows={4}
                                    className="mt-1"
                                />
                                {errors.notes && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.notes}
                                    </p>
                                )}
                            </div>

                            <div className="flex gap-4 border-t pt-6 dark:border-gray-700">
                                <Link
                                    href={`/admin/insurance/providers/${provider.id}`}
                                >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="flex-1 md:flex-none"
                                    >
                                        Cancel
                                    </Button>
                                </Link>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 md:flex-none"
                                >
                                    {processing
                                        ? 'Updating...'
                                        : 'Update Provider'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
