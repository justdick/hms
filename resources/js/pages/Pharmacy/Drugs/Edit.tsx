import DrugController from '@/actions/App/Http/Controllers/Pharmacy/DrugController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    AlertCircle,
    ArrowLeft,
    BarChart3,
    ExternalLink,
    Eye,
    Info,
    Package,
    Save,
} from 'lucide-react';
import { FormEventHandler } from 'react';

interface Drug {
    id: number;
    name: string;
    generic_name?: string;
    brand_name?: string;
    drug_code: string;
    category: string;
    form: string;
    strength?: string;
    description?: string;
    unit_price: number | null;
    unit_type: string;
    bottle_size?: number;
    minimum_stock_level: number;
    maximum_stock_level: number;
    is_active: boolean;
    nhis_claim_qty_as_one: boolean;
}

interface Props {
    drug: Drug;
    categories: string[];
    canManageNhisSettings: boolean;
}

export default function EditDrug({
    drug,
    categories,
    canManageNhisSettings,
}: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: drug.name || '',
        generic_name: drug.generic_name || '',
        brand_name: drug.brand_name || '',
        drug_code: drug.drug_code || '',
        category: drug.category || '',
        form: drug.form || '',
        strength: drug.strength || '',
        description: drug.description || '',
        unit_type: drug.unit_type || '',
        bottle_size: drug.bottle_size?.toString() || '',
        minimum_stock_level: drug.minimum_stock_level?.toString() || '',
        maximum_stock_level: drug.maximum_stock_level?.toString() || '',
        is_active: drug.is_active,
        nhis_claim_qty_as_one: drug.nhis_claim_qty_as_one ?? false,
    });

    const drugForms = [
        'Tablet',
        'Capsule',
        'Syrup',
        'Injection',
        'Cream',
        'Ointment',
        'Drops',
        'Inhaler',
        'Powder',
        'Solution',
        'Suspension',
        'Gel',
        'Patch',
        'Spray',
    ];

    const unitTypes = [
        {
            value: 'piece',
            label: 'Piece (for tablets, capsules - sold individually)',
        },
        { value: 'bottle', label: 'Bottle (for syrups, suspensions)' },
        { value: 'vial', label: 'Vial (for injections)' },
        { value: 'tube', label: 'Tube (for creams, ointments)' },
        { value: 'box', label: 'Box (for sachets, patches)' },
    ];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(DrugController.update(drug.id));
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pharmacy', href: '/pharmacy' },
                { title: 'Drugs', href: '/pharmacy/drugs' },
                { title: drug.name, href: `/pharmacy/drugs/${drug.id}` },
                { title: 'Edit', href: `/pharmacy/drugs/${drug.id}/edit` },
            ]}
        >
            <Head title={`Edit ${drug.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/pharmacy/drugs/${drug.id}`}>
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Drug
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Package className="h-6 w-6" />
                                Edit Drug: {drug.name}
                            </h1>
                            <p className="text-muted-foreground">
                                Update drug information and settings
                            </p>
                        </div>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={`/pharmacy/drugs/${drug.id}`}>
                            <Eye className="mr-1 h-4 w-4" />
                            View Drug
                        </Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        {/* Basic Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Package className="h-5 w-5" />
                                    Basic Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Drug Name *</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        className={
                                            errors.name ? 'border-red-500' : ''
                                        }
                                        placeholder="Enter drug name"
                                    />
                                    {errors.name && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="generic_name">
                                        Generic Name
                                    </Label>
                                    <Input
                                        id="generic_name"
                                        type="text"
                                        value={data.generic_name}
                                        onChange={(e) =>
                                            setData(
                                                'generic_name',
                                                e.target.value,
                                            )
                                        }
                                        className={
                                            errors.generic_name
                                                ? 'border-red-500'
                                                : ''
                                        }
                                        placeholder="Enter generic name"
                                    />
                                    {errors.generic_name && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.generic_name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="brand_name">
                                        Brand Name
                                    </Label>
                                    <Input
                                        id="brand_name"
                                        type="text"
                                        value={data.brand_name}
                                        onChange={(e) =>
                                            setData(
                                                'brand_name',
                                                e.target.value,
                                            )
                                        }
                                        className={
                                            errors.brand_name
                                                ? 'border-red-500'
                                                : ''
                                        }
                                        placeholder="Enter brand name"
                                    />
                                    {errors.brand_name && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.brand_name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="drug_code">
                                        Drug Code *
                                    </Label>
                                    <Input
                                        id="drug_code"
                                        type="text"
                                        value={data.drug_code}
                                        onChange={(e) =>
                                            setData('drug_code', e.target.value)
                                        }
                                        className={
                                            errors.drug_code
                                                ? 'border-red-500'
                                                : ''
                                        }
                                        placeholder="Enter unique drug code"
                                    />
                                    {errors.drug_code && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.drug_code}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="category">Category *</Label>
                                    <Select
                                        value={data.category}
                                        onValueChange={(value) =>
                                            setData('category', value)
                                        }
                                    >
                                        <SelectTrigger
                                            className={
                                                errors.category
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                        >
                                            <SelectValue placeholder="Select or enter category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((category) => (
                                                <SelectItem
                                                    key={category}
                                                    value={category}
                                                >
                                                    {category}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.category && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.category}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="form">Form *</Label>
                                    <Select
                                        value={data.form}
                                        onValueChange={(value) =>
                                            setData('form', value)
                                        }
                                    >
                                        <SelectTrigger
                                            className={
                                                errors.form
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                        >
                                            <SelectValue placeholder="Select drug form" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {drugForms.map((form) => (
                                                <SelectItem
                                                    key={form}
                                                    value={form}
                                                >
                                                    {form}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.form && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.form}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="strength">Strength</Label>
                                    <Input
                                        id="strength"
                                        type="text"
                                        value={data.strength}
                                        onChange={(e) =>
                                            setData('strength', e.target.value)
                                        }
                                        className={
                                            errors.strength
                                                ? 'border-red-500'
                                                : ''
                                        }
                                        placeholder="e.g., 500mg, 250mg/5ml, 1g"
                                    />
                                    {errors.strength && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.strength}
                                        </p>
                                    )}
                                    <p className="text-xs text-gray-500">
                                        Examples: "500mg" for tablets,
                                        "250mg/5ml" for syrups, "1g/10ml" for
                                        injections
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) =>
                                            setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        className={
                                            errors.description
                                                ? 'border-red-500'
                                                : ''
                                        }
                                        placeholder="Enter drug description"
                                        rows={3}
                                    />
                                    {errors.description && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.description}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Stock Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Package className="h-5 w-5" />
                                    Stock Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Alert>
                                    <Info className="h-4 w-4" />
                                    <AlertDescription className="flex items-center justify-between">
                                        <span>
                                            {drug.unit_price !== null
                                                ? `Current price: GHS ${drug.unit_price.toFixed(2)}`
                                                : 'Price not set'}
                                            . Manage pricing in the Pricing
                                            Dashboard.
                                        </span>
                                        <Button
                                            variant="link"
                                            size="sm"
                                            className="ml-2 h-auto p-0"
                                            asChild
                                        >
                                            <Link
                                                href={`/admin/pricing-dashboard?search=${encodeURIComponent(drug.drug_code)}`}
                                                target="_blank"
                                            >
                                                Set Price
                                                <ExternalLink className="ml-1 h-3 w-3" />
                                            </Link>
                                        </Button>
                                    </AlertDescription>
                                </Alert>

                                <div className="space-y-2">
                                    <Label htmlFor="unit_type">
                                        Unit Type *
                                    </Label>
                                    <Select
                                        value={data.unit_type}
                                        onValueChange={(value) =>
                                            setData('unit_type', value)
                                        }
                                    >
                                        <SelectTrigger
                                            className={
                                                errors.unit_type
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                        >
                                            <SelectValue placeholder="Select unit type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {unitTypes.map((unit) => (
                                                <SelectItem
                                                    key={unit.value}
                                                    value={unit.value}
                                                >
                                                    {unit.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.unit_type && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.unit_type}
                                        </p>
                                    )}
                                    <p className="text-xs text-gray-500">
                                        This determines how the drug is counted
                                        for billing and inventory
                                    </p>
                                </div>

                                {/* Bottle Size - Only show for bottles and vials */}
                                {(data.unit_type === 'bottle' ||
                                    data.unit_type === 'vial') && (
                                    <div className="space-y-2">
                                        <Label htmlFor="bottle_size">
                                            Bottle/Vial Size (ml) *
                                        </Label>
                                        <Input
                                            id="bottle_size"
                                            type="number"
                                            min="1"
                                            value={data.bottle_size}
                                            onChange={(e) =>
                                                setData(
                                                    'bottle_size',
                                                    e.target.value,
                                                )
                                            }
                                            className={
                                                errors.bottle_size
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                            placeholder={
                                                data.unit_type === 'bottle'
                                                    ? 'e.g., 100'
                                                    : 'e.g., 10'
                                            }
                                        />
                                        {errors.bottle_size && (
                                            <p className="flex items-center gap-1 text-sm text-red-500">
                                                <AlertCircle className="h-3 w-3" />
                                                {errors.bottle_size}
                                            </p>
                                        )}
                                        <p className="text-xs text-gray-500">
                                            Volume in milliliters (ml) per{' '}
                                            {data.unit_type}. Common sizes:
                                            50ml, 100ml, 200ml for bottles; 5ml,
                                            10ml, 20ml for vials.
                                        </p>
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="minimum_stock_level">
                                        Minimum Stock Level *
                                    </Label>
                                    <Input
                                        id="minimum_stock_level"
                                        type="number"
                                        min="0"
                                        value={data.minimum_stock_level}
                                        onChange={(e) =>
                                            setData(
                                                'minimum_stock_level',
                                                e.target.value,
                                            )
                                        }
                                        className={
                                            errors.minimum_stock_level
                                                ? 'border-red-500'
                                                : ''
                                        }
                                        placeholder="Enter minimum stock level"
                                    />
                                    {errors.minimum_stock_level && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.minimum_stock_level}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="maximum_stock_level">
                                        Maximum Stock Level *
                                    </Label>
                                    <Input
                                        id="maximum_stock_level"
                                        type="number"
                                        min="0"
                                        value={data.maximum_stock_level}
                                        onChange={(e) =>
                                            setData(
                                                'maximum_stock_level',
                                                e.target.value,
                                            )
                                        }
                                        className={
                                            errors.maximum_stock_level
                                                ? 'border-red-500'
                                                : ''
                                        }
                                        placeholder="Enter maximum stock level"
                                    />
                                    {errors.maximum_stock_level && (
                                        <p className="flex items-center gap-1 text-sm text-red-500">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.maximum_stock_level}
                                        </p>
                                    )}
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="is_active"
                                        checked={data.is_active}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'is_active',
                                                checked as boolean,
                                            )
                                        }
                                    />
                                    <Label htmlFor="is_active">
                                        Active Drug
                                    </Label>
                                </div>

                                <div className="space-y-2">
                                    {canManageNhisSettings && (
                                        <>
                                            <div className="flex items-center space-x-2">
                                                <Checkbox
                                                    id="nhis_claim_qty_as_one"
                                                    checked={
                                                        data.nhis_claim_qty_as_one
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setData(
                                                            'nhis_claim_qty_as_one',
                                                            checked as boolean,
                                                        )
                                                    }
                                                />
                                                <Label htmlFor="nhis_claim_qty_as_one">
                                                    NHIS Claim Qty as 1 (Pack)
                                                </Label>
                                            </div>
                                            <p className="text-xs text-gray-500">
                                                For drugs like Arthemeter and
                                                Pessary where NHIS requires
                                                quantity = 1 regardless of
                                                actual tablets dispensed
                                            </p>
                                        </>
                                    )}
                                </div>

                                <div className="rounded-lg bg-amber-50 p-3 dark:bg-amber-950">
                                    <h4 className="mb-1 font-medium text-amber-900 dark:text-amber-100">
                                        <BarChart3 className="mr-1 inline h-4 w-4" />
                                        Update Guidelines
                                    </h4>
                                    <p className="text-xs text-amber-700 dark:text-amber-300">
                                        Changes to stock levels will not affect
                                        existing batches. These settings apply
                                        to new batches and general drug
                                        information.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Form Actions */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-end gap-2">
                                <Button type="button" variant="outline" asChild>
                                    <Link href={`/pharmacy/drugs/${drug.id}`}>
                                        Cancel
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <>
                                            <BarChart3 className="mr-1 h-4 w-4 animate-spin" />
                                            Updating...
                                        </>
                                    ) : (
                                        <>
                                            <Save className="mr-1 h-4 w-4" />
                                            Update Drug
                                        </>
                                    )}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
