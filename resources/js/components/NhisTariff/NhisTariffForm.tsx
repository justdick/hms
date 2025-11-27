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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

interface NhisTariff {
    id: number;
    nhis_code: string;
    name: string;
    category: string;
    price: number;
    unit: string | null;
    is_active: boolean;
}

interface NhisTariffFormProps {
    isOpen: boolean;
    onClose: () => void;
    tariff?: NhisTariff | null;
    categories: string[];
}

const categoryLabels: Record<string, string> = {
    medicine: 'Medicine',
    lab: 'Laboratory',
    procedure: 'Procedure',
    consultation: 'Consultation',
    consumable: 'Consumable',
};

export function NhisTariffForm({
    isOpen,
    onClose,
    tariff,
    categories,
}: NhisTariffFormProps) {
    const isEditing = !!tariff;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        nhis_code: tariff?.nhis_code || '',
        name: tariff?.name || '',
        category: tariff?.category || 'medicine',
        price: tariff?.price?.toString() || '',
        unit: tariff?.unit || '',
        is_active: tariff?.is_active ?? true,
    });

    useEffect(() => {
        if (tariff) {
            setData({
                nhis_code: tariff.nhis_code,
                name: tariff.name,
                category: tariff.category,
                price: tariff.price.toString(),
                unit: tariff.unit || '',
                is_active: tariff.is_active,
            });
        } else {
            reset();
        }
    }, [tariff]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (isEditing && tariff) {
            put(`/admin/nhis-tariffs/${tariff.id}`, {
                onSuccess: () => {
                    onClose();
                    reset();
                },
            });
        } else {
            post('/admin/nhis-tariffs', {
                onSuccess: () => {
                    onClose();
                    reset();
                },
            });
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit NHIS Tariff' : 'Add NHIS Tariff'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the tariff details below.'
                            : 'Enter the details for the new NHIS tariff.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="nhis_code">NHIS Code *</Label>
                            <Input
                                id="nhis_code"
                                value={data.nhis_code}
                                onChange={(e) =>
                                    setData(
                                        'nhis_code',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="e.g., MED-001"
                                required
                            />
                            {errors.nhis_code && (
                                <p className="text-sm text-red-600">
                                    {errors.nhis_code}
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
                                <SelectTrigger>
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((cat) => (
                                        <SelectItem key={cat} value={cat}>
                                            {categoryLabels[cat] || cat}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.category && (
                                <p className="text-sm text-red-600">
                                    {errors.category}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="name">Name *</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g., Paracetamol 500mg"
                            required
                        />
                        {errors.name && (
                            <p className="text-sm text-red-600">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="price">Price (GHS) *</Label>
                            <Input
                                id="price"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.price}
                                onChange={(e) =>
                                    setData('price', e.target.value)
                                }
                                placeholder="0.00"
                                required
                            />
                            {errors.price && (
                                <p className="text-sm text-red-600">
                                    {errors.price}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="unit">Unit</Label>
                            <Input
                                id="unit"
                                value={data.unit}
                                onChange={(e) =>
                                    setData('unit', e.target.value)
                                }
                                placeholder="e.g., tablet, test"
                            />
                            {errors.unit && (
                                <p className="text-sm text-red-600">
                                    {errors.unit}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="is_active" className="cursor-pointer">
                            Active
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? isEditing
                                    ? 'Updating...'
                                    : 'Creating...'
                                : isEditing
                                  ? 'Update Tariff'
                                  : 'Create Tariff'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export type { NhisTariff };
