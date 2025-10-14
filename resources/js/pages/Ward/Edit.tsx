import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Hospital } from 'lucide-react';

interface Ward {
    id: number;
    name: string;
    code: string;
    description?: string;
    is_active: boolean;
    total_beds: number;
    available_beds: number;
}

interface Props {
    ward: Ward;
}

export default function WardEdit({ ward }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: ward.name,
        code: ward.code,
        description: ward.description || '',
        is_active: ward.is_active,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/wards/${ward.id}`);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Wards', href: '/wards' },
                { title: ward.name, href: `/wards/${ward.id}` },
                { title: 'Edit', href: '' },
            ]}
        >
            <Head title={`Edit ${ward.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href={`/wards/${ward.id}`}>
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Ward
                        </Button>
                    </Link>
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900">
                            <Hospital className="h-8 w-8" />
                            Edit Ward: {ward.name}
                        </h1>
                        <p className="mt-1 text-gray-600">
                            Update ward information and configuration
                        </p>
                    </div>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Ward Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Basic Information */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="name">Ward Name</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder="e.g., General Ward A"
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
                                    <Label htmlFor="code">Ward Code</Label>
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
                                        placeholder="e.g., GWA"
                                        maxLength={10}
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

                            <div>
                                <Label htmlFor="description">
                                    Description (Optional)
                                </Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    placeholder="Brief description of the ward..."
                                    rows={3}
                                    className="mt-1"
                                />
                                {errors.description && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            {/* Bed Information (Read-only) */}
                            <div className="rounded-lg border bg-gray-50 p-4">
                                <h3 className="mb-2 font-semibold text-gray-900">
                                    Bed Information
                                </h3>
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p className="text-gray-600">
                                            Total Beds
                                        </p>
                                        <p className="text-lg font-semibold">
                                            {ward.total_beds}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-gray-600">
                                            Available Beds
                                        </p>
                                        <p className="text-lg font-semibold text-green-600">
                                            {ward.available_beds}
                                        </p>
                                    </div>
                                </div>
                                <p className="mt-2 text-xs text-gray-500">
                                    To manage individual beds, visit the ward
                                    details page.
                                </p>
                            </div>

                            {/* Active Status */}
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', !!checked)
                                    }
                                />
                                <Label
                                    htmlFor="is_active"
                                    className="text-sm font-medium"
                                >
                                    Active Ward
                                </Label>
                            </div>
                            {errors.is_active && (
                                <p className="text-sm text-red-600">
                                    {errors.is_active}
                                </p>
                            )}

                            <div className="flex gap-4 border-t pt-6">
                                <Link href={`/wards/${ward.id}`}>
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
                                    {processing ? 'Updating...' : 'Update Ward'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
