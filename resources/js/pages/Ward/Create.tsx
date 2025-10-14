import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Hospital } from 'lucide-react';

interface Props {}

export default function WardCreate({}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        code: '',
        description: '',
        bed_count: 10,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/wards');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Wards', href: '/wards' },
                { title: 'Create Ward', href: '' },
            ]}
        >
            <Head title="Create Ward" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/wards">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Wards
                        </Button>
                    </Link>
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900">
                            <Hospital className="h-8 w-8" />
                            Create New Ward
                        </h1>
                        <p className="mt-1 text-gray-600">
                            Set up a new ward with beds and configuration
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

                            <div>
                                <Label htmlFor="bed_count">
                                    Number of Beds
                                </Label>
                                <Input
                                    id="bed_count"
                                    type="number"
                                    value={data.bed_count}
                                    onChange={(e) =>
                                        setData(
                                            'bed_count',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                    min={1}
                                    max={100}
                                    required
                                    className="mt-1"
                                />
                                <p className="mt-1 text-sm text-gray-600">
                                    Beds will be automatically created (01, 02,
                                    03, etc.)
                                </p>
                                {errors.bed_count && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.bed_count}
                                    </p>
                                )}
                            </div>

                            <div className="flex gap-4 border-t pt-6">
                                <Link href="/wards">
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
                                    {processing ? 'Creating...' : 'Create Ward'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
