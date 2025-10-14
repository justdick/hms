import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import patientNumbering from '@/routes/patient-numbering';
import { Head, useForm } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';

interface Props {
    config: {
        patient_number_prefix: string;
        patient_number_year_format: string;
        patient_number_separator: string;
        patient_number_padding: number;
        patient_number_reset: string;
    };
    nextNumber: number;
    currentExample: string;
}

export default function PatientNumbering({
    config,
    nextNumber,
    currentExample,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        patient_number_prefix: config.patient_number_prefix || 'PAT',
        patient_number_year_format: config.patient_number_year_format || 'YYYY',
        patient_number_separator: config.patient_number_separator || '',
        patient_number_padding: config.patient_number_padding || 6,
        patient_number_reset: config.patient_number_reset || 'never',
    });

    const [preview, setPreview] = useState('');

    // Generate preview whenever form data changes
    useEffect(() => {
        const year =
            data.patient_number_year_format === 'YYYY'
                ? new Date().getFullYear()
                : String(new Date().getFullYear()).slice(-2);
        const separator = data.patient_number_separator;
        const paddedNumber = String(nextNumber).padStart(
            data.patient_number_padding,
            '0',
        );

        const previewText = `${data.patient_number_prefix}${separator}${year}${separator}${paddedNumber}`;
        setPreview(previewText);
    }, [data, nextNumber]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(patientNumbering.update.url());
    };

    return (
        <AppLayout>
            <Head title="Patient Numbering Configuration" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        Patient Numbering Configuration
                    </h1>
                    <p className="text-muted-foreground">
                        Configure how patient numbers are generated in the
                        system
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Configuration Form */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Number Format Settings</CardTitle>
                            <CardDescription>
                                Customize the format and structure of patient
                                numbers
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                {/* Prefix */}
                                <div className="space-y-2">
                                    <Label htmlFor="prefix">Prefix</Label>
                                    <Input
                                        id="prefix"
                                        type="text"
                                        value={data.patient_number_prefix}
                                        onChange={(e) =>
                                            setData(
                                                'patient_number_prefix',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="PAT"
                                        maxLength={10}
                                        className={
                                            errors.patient_number_prefix
                                                ? 'border-red-500'
                                                : ''
                                        }
                                    />
                                    {errors.patient_number_prefix && (
                                        <p className="text-sm text-red-500">
                                            {errors.patient_number_prefix}
                                        </p>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        Letters only (e.g., PAT, HOS, MED)
                                    </p>
                                </div>

                                {/* Year Format */}
                                <div className="space-y-2">
                                    <Label htmlFor="year-format">
                                        Year Format
                                    </Label>
                                    <Select
                                        value={data.patient_number_year_format}
                                        onValueChange={(value) =>
                                            setData(
                                                'patient_number_year_format',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="year-format">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="YYYY">
                                                Full Year (2025)
                                            </SelectItem>
                                            <SelectItem value="YY">
                                                Short Year (25)
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.patient_number_year_format && (
                                        <p className="text-sm text-red-500">
                                            {errors.patient_number_year_format}
                                        </p>
                                    )}
                                </div>

                                {/* Separator */}
                                <div className="space-y-2">
                                    <Label htmlFor="separator">Separator</Label>
                                    <Select
                                        value={
                                            data.patient_number_separator ||
                                            'none'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'patient_number_separator',
                                                value === 'none' ? '' : value,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="separator">
                                            <SelectValue placeholder="None" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                None
                                            </SelectItem>
                                            <SelectItem value="-">
                                                Dash (-)
                                            </SelectItem>
                                            <SelectItem value="/">
                                                Slash (/)
                                            </SelectItem>
                                            <SelectItem value="_">
                                                Underscore (_)
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.patient_number_separator && (
                                        <p className="text-sm text-red-500">
                                            {errors.patient_number_separator}
                                        </p>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        Character between prefix, year, and
                                        number
                                    </p>
                                </div>

                                {/* Number Padding */}
                                <div className="space-y-2">
                                    <Label htmlFor="padding">
                                        Number Padding
                                    </Label>
                                    <Input
                                        id="padding"
                                        type="number"
                                        min="3"
                                        max="8"
                                        value={data.patient_number_padding}
                                        onChange={(e) =>
                                            setData(
                                                'patient_number_padding',
                                                parseInt(e.target.value),
                                            )
                                        }
                                        className={
                                            errors.patient_number_padding
                                                ? 'border-red-500'
                                                : ''
                                        }
                                    />
                                    {errors.patient_number_padding && (
                                        <p className="text-sm text-red-500">
                                            {errors.patient_number_padding}
                                        </p>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        Number of digits (3-8). E.g., 6 = 000001
                                    </p>
                                </div>

                                {/* Reset Policy */}
                                <div className="space-y-2">
                                    <Label htmlFor="reset">Reset Policy</Label>
                                    <Select
                                        value={data.patient_number_reset}
                                        onValueChange={(value) =>
                                            setData(
                                                'patient_number_reset',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="reset">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="never">
                                                Never Reset
                                            </SelectItem>
                                            <SelectItem value="yearly">
                                                Reset Yearly
                                            </SelectItem>
                                            <SelectItem value="monthly">
                                                Reset Monthly
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.patient_number_reset && (
                                        <p className="text-sm text-red-500">
                                            {errors.patient_number_reset}
                                        </p>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        When to reset the counter back to 1
                                    </p>
                                </div>

                                {/* Submit Button */}
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full"
                                >
                                    {processing
                                        ? 'Saving...'
                                        : 'Save Configuration'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Preview and Current Example */}
                    <div className="space-y-6">
                        {/* Live Preview */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Live Preview</CardTitle>
                                <CardDescription>
                                    See how the next patient number will look
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="rounded-lg bg-muted p-6 text-center">
                                        <p className="mb-2 text-sm text-muted-foreground">
                                            Next Patient Number
                                        </p>
                                        <p className="font-mono text-3xl font-bold">
                                            {preview}
                                        </p>
                                    </div>
                                    <div className="space-y-1 text-sm text-muted-foreground">
                                        <p>
                                            <strong>Breakdown:</strong>
                                        </p>
                                        <ul className="ml-2 list-inside list-disc space-y-1">
                                            <li>
                                                Prefix:{' '}
                                                <code className="rounded bg-muted px-1 py-0.5">
                                                    {data.patient_number_prefix}
                                                </code>
                                            </li>
                                            <li>
                                                Year:{' '}
                                                <code className="rounded bg-muted px-1 py-0.5">
                                                    {data.patient_number_year_format ===
                                                    'YYYY'
                                                        ? new Date().getFullYear()
                                                        : String(
                                                              new Date().getFullYear(),
                                                          ).slice(-2)}
                                                </code>
                                            </li>
                                            <li>
                                                Number:{' '}
                                                <code className="rounded bg-muted px-1 py-0.5">
                                                    {String(
                                                        nextNumber,
                                                    ).padStart(
                                                        data.patient_number_padding,
                                                        '0',
                                                    )}
                                                </code>
                                            </li>
                                            {data.patient_number_separator && (
                                                <li>
                                                    Separator:{' '}
                                                    <code className="rounded bg-muted px-1 py-0.5">
                                                        "
                                                        {
                                                            data.patient_number_separator
                                                        }
                                                        "
                                                    </code>
                                                </li>
                                            )}
                                        </ul>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Current Example */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Current System Status</CardTitle>
                                <CardDescription>
                                    Last generated patient number
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="rounded-lg bg-muted p-4">
                                    <p className="mb-1 text-sm text-muted-foreground">
                                        Last Patient Number
                                    </p>
                                    <p className="font-mono text-xl font-bold">
                                        {currentExample}
                                    </p>
                                </div>
                                <div className="mt-4 rounded-lg border bg-yellow-50 p-4 dark:bg-yellow-900/20">
                                    <p className="mb-1 text-sm font-semibold">
                                        ⚠️ Important Notes:
                                    </p>
                                    <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                        <li>
                                            Changes only affect NEW patient
                                            registrations
                                        </li>
                                        <li>
                                            Existing patient numbers will NOT
                                            change
                                        </li>
                                        <li>
                                            Ensure format is compatible with
                                            existing system
                                        </li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
