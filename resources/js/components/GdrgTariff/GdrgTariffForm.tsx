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

interface GdrgTariff {
    id: number;
    code: string;
    name: string;
    mdc_category: string;
    tariff_price: number;
    age_category: string;
    is_active: boolean;
}

interface GdrgTariffFormProps {
    isOpen: boolean;
    onClose: () => void;
    tariff?: GdrgTariff | null;
    mdcCategories: string[];
    ageCategories: string[];
}

const ageCategoryLabels: Record<string, string> = {
    adult: 'Adult',
    child: 'Child',
    all: 'All Ages',
};

export function GdrgTariffForm({
    isOpen,
    onClose,
    tariff,
    mdcCategories,
    ageCategories,
}: GdrgTariffFormProps) {
    const isEditing = !!tariff;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: tariff?.code || '',
        name: tariff?.name || '',
        mdc_category: tariff?.mdc_category || '',
        tariff_price: tariff?.tariff_price?.toString() || '',
        age_category: tariff?.age_category || 'all',
        is_active: tariff?.is_active ?? true,
    });

    useEffect(() => {
        if (tariff) {
            setData({
                code: tariff.code,
                name: tariff.name,
                mdc_category: tariff.mdc_category,
                tariff_price: tariff.tariff_price.toString(),
                age_category: tariff.age_category,
                is_active: tariff.is_active,
            });
        } else {
            reset();
        }
    }, [tariff]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (isEditing && tariff) {
            put(`/admin/gdrg-tariffs/${tariff.id}`, {
                onSuccess: () => {
                    onClose();
                    reset();
                },
            });
        } else {
            post('/admin/gdrg-tariffs', {
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
                        {isEditing ? 'Edit G-DRG Tariff' : 'Add G-DRG Tariff'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the G-DRG tariff details below.'
                            : 'Enter the details for the new G-DRG tariff.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="code">G-DRG Code *</Label>
                            <Input
                                id="code"
                                value={data.code}
                                onChange={(e) =>
                                    setData(
                                        'code',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="e.g., G-DRG-001"
                                required
                            />
                            {errors.code && (
                                <p className="text-sm text-red-600">
                                    {errors.code}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="age_category">Age Category *</Label>
                            <Select
                                value={data.age_category}
                                onValueChange={(value) =>
                                    setData('age_category', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select age category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ageCategories.map((cat) => (
                                        <SelectItem key={cat} value={cat}>
                                            {ageCategoryLabels[cat] || cat}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.age_category && (
                                <p className="text-sm text-red-600">
                                    {errors.age_category}
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
                            placeholder="e.g., General Consultation - Adult"
                            required
                        />
                        {errors.name && (
                            <p className="text-sm text-red-600">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="mdc_category">MDC Category *</Label>
                        <Input
                            id="mdc_category"
                            value={data.mdc_category}
                            onChange={(e) =>
                                setData('mdc_category', e.target.value)
                            }
                            placeholder="e.g., Out Patient, In Patient"
                            list="mdc_category_list"
                            required
                        />
                        <datalist id="mdc_category_list">
                            {mdcCategories.map((cat) => (
                                <option key={cat} value={cat} />
                            ))}
                        </datalist>
                        {errors.mdc_category && (
                            <p className="text-sm text-red-600">
                                {errors.mdc_category}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="tariff_price">
                            Tariff Price (GHS) *
                        </Label>
                        <Input
                            id="tariff_price"
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.tariff_price}
                            onChange={(e) =>
                                setData('tariff_price', e.target.value)
                            }
                            placeholder="0.00"
                            required
                        />
                        {errors.tariff_price && (
                            <p className="text-sm text-red-600">
                                {errors.tariff_price}
                            </p>
                        )}
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

export type { GdrgTariff };
