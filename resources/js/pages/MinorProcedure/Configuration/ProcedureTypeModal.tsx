import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface ProcedureType {
    id: number;
    name: string;
    code: string;
    category: string;
    type: 'minor' | 'major';
    description: string | null;
    price: number;
    is_active: boolean;
}

interface Props {
    open: boolean;
    onClose: () => void;
    categories: string[];
    editingType?: ProcedureType | null;
}

export default function ProcedureTypeModal({
    open,
    onClose,
    categories,
    editingType,
}: Props) {
    const [selectedCategory, setSelectedCategory] = useState('');
    const [suggestedCode, setSuggestedCode] = useState('');
    const [newCategory, setNewCategory] = useState('');

    useEffect(() => {
        if (editingType) {
            setSelectedCategory(editingType.category);
        } else {
            setSelectedCategory('');
            setSuggestedCode('');
            setNewCategory('');
        }
    }, [editingType, open]);

    useEffect(() => {
        if (selectedCategory && !editingType) {
            // Fetch suggested code
            fetch(
                `/minor-procedures/types/suggest-code?category=${encodeURIComponent(selectedCategory)}`,
            )
                .then((res) => res.json())
                .then((data) => setSuggestedCode(data.code))
                .catch(() => setSuggestedCode(''));
        }
    }, [selectedCategory, editingType]);

    const handleCategoryChange = (value: string) => {
        if (value === '__new__') {
            setSelectedCategory('');
        } else {
            setSelectedCategory(value);
            setNewCategory('');
        }
    };

    const effectiveCategory = newCategory || selectedCategory;

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {editingType
                            ? 'Edit Procedure Type'
                            : 'Add Procedure Type'}
                    </DialogTitle>
                    <DialogDescription>
                        {editingType
                            ? 'Update the procedure type details'
                            : 'Create a new procedure type with pricing'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={
                        editingType
                            ? `/minor-procedures/types/${editingType.id}`
                            : '/minor-procedures/types'
                    }
                    method={editingType ? 'put' : 'post'}
                    onSuccess={onClose}
                >
                    {({ errors, processing }) => (
                        <div className="space-y-4">
                            {/* Type */}
                            <div className="space-y-2">
                                <Label htmlFor="type">Procedure Type *</Label>
                                <Select
                                    name="type"
                                    defaultValue={editingType?.type || 'minor'}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="minor">
                                            Minor (OPD Procedures)
                                        </SelectItem>
                                        <SelectItem value="major">
                                            Major (Theatre Procedures)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Minor procedures are performed by nurses at
                                    OPD. Major procedures are theatre
                                    operations.
                                </p>
                                {errors.type && (
                                    <p className="text-sm text-destructive">
                                        {errors.type}
                                    </p>
                                )}
                            </div>

                            {/* Category */}
                            <div className="space-y-2">
                                <Label htmlFor="category">Category *</Label>
                                {editingType ? (
                                    <Input
                                        id="category"
                                        name="category"
                                        defaultValue={editingType.category}
                                        required
                                    />
                                ) : (
                                    <>
                                        <Select
                                            value={
                                                selectedCategory || '__new__'
                                            }
                                            onValueChange={handleCategoryChange}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select category..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {categories.map((cat) => (
                                                    <SelectItem
                                                        key={cat}
                                                        value={cat}
                                                    >
                                                        {cat.replace(/_/g, ' ')}
                                                    </SelectItem>
                                                ))}
                                                <SelectItem value="__new__">
                                                    + New Category
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {!selectedCategory && (
                                            <Input
                                                placeholder="Enter new category name..."
                                                value={newCategory}
                                                onChange={(e) =>
                                                    setNewCategory(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        )}
                                        <input
                                            type="hidden"
                                            name="category"
                                            value={effectiveCategory}
                                        />
                                    </>
                                )}
                                {errors.category && (
                                    <p className="text-sm text-destructive">
                                        {errors.category}
                                    </p>
                                )}
                            </div>

                            {/* Name */}
                            <div className="space-y-2">
                                <Label htmlFor="name">Procedure Name *</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="e.g., Wound Dressing"
                                    defaultValue={editingType?.name}
                                    required
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            {/* Code */}
                            <div className="space-y-2">
                                <Label htmlFor="code">Procedure Code *</Label>
                                <Input
                                    id="code"
                                    name="code"
                                    placeholder={suggestedCode || 'e.g., WD001'}
                                    defaultValue={
                                        editingType?.code || suggestedCode
                                    }
                                    required
                                />
                                {suggestedCode && !editingType && (
                                    <p className="text-xs text-muted-foreground">
                                        Suggested code: {suggestedCode}
                                    </p>
                                )}
                                {errors.code && (
                                    <p className="text-sm text-destructive">
                                        {errors.code}
                                    </p>
                                )}
                            </div>

                            {/* Description */}
                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    placeholder="Brief description of the procedure..."
                                    rows={3}
                                    defaultValue={
                                        editingType?.description || ''
                                    }
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            {/* Price */}
                            <div className="space-y-2">
                                <Label htmlFor="price">Price (KES) *</Label>
                                <Input
                                    id="price"
                                    name="price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    defaultValue={editingType?.price}
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    Set to 0.00 if covered by department
                                    consultation fee only
                                </p>
                                {errors.price && (
                                    <p className="text-sm text-destructive">
                                        {errors.price}
                                    </p>
                                )}
                            </div>

                            {/* Active Status (only for editing) */}
                            {editingType && (
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="is_active"
                                        name="is_active"
                                        defaultChecked={editingType.is_active}
                                    />
                                    <Label
                                        htmlFor="is_active"
                                        className="text-sm font-normal"
                                    >
                                        Active (available for selection)
                                    </Label>
                                </div>
                            )}

                            {/* Action Buttons */}
                            <div className="flex justify-end gap-2 border-t pt-4">
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
                                        ? 'Saving...'
                                        : editingType
                                          ? 'Update'
                                          : 'Create'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
