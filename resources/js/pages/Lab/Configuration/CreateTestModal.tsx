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
import { Loader2, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    price?: number;
    description?: string;
    preparation_instructions?: string;
    sample_type?: string;
    turnaround_time?: string;
    normal_range?: string;
    clinical_significance?: string;
    is_active?: boolean;
    test_parameters?: any;
}

interface CreateTestModalProps {
    open: boolean;
    onClose: () => void;
    categories: string[];
    editingService?: LabService;
}

interface FormData {
    name: string;
    code: string;
    category: string;
    description: string;
    preparation_instructions: string;
    price: string;
    sample_type: string;
    turnaround_time: string;
    normal_range: string;
    clinical_significance: string;
}

const sampleTypes = [
    'Blood',
    'Blood Serum',
    'Blood Plasma',
    'Urine',
    'Stool',
    'Sputum',
    'CSF',
    'Tissue',
    'Swab',
    'Other',
];

const turnaroundTimes = [
    '2-4 hours',
    '4-6 hours',
    '6-12 hours',
    '12-24 hours',
    '24-48 hours',
    '48-72 hours',
    '3-5 days',
    '1 week',
    '2 weeks',
];

export default function CreateTestModal({
    open,
    onClose,
    categories,
    editingService,
}: CreateTestModalProps) {
    const isEditing = !!editingService;

    const [formData, setFormData] = useState<FormData>({
        name: editingService?.name || '',
        code: editingService?.code || '',
        category: editingService?.category || '',
        description: editingService?.description || '',
        preparation_instructions:
            editingService?.preparation_instructions || '',
        price: editingService?.price?.toString() || '',
        sample_type: editingService?.sample_type || '',
        turnaround_time: editingService?.turnaround_time || '',
        normal_range: editingService?.normal_range || '',
        clinical_significance: editingService?.clinical_significance || '',
    });

    // Update form data when editingService changes
    useEffect(() => {
        if (editingService) {
            setFormData({
                name: editingService.name || '',
                code: editingService.code || '',
                category: editingService.category || '',
                description: editingService.description || '',
                preparation_instructions:
                    editingService.preparation_instructions || '',
                price: editingService.price?.toString() || '',
                sample_type: editingService.sample_type || '',
                turnaround_time: editingService.turnaround_time || '',
                normal_range: editingService.normal_range || '',
                clinical_significance:
                    editingService.clinical_significance || '',
            });
        } else {
            // Reset form for create mode
            setFormData({
                name: '',
                code: '',
                category: '',
                description: '',
                preparation_instructions: '',
                price: '',
                sample_type: '',
                turnaround_time: '',
                normal_range: '',
                clinical_significance: '',
            });
        }
    }, [editingService]);
    const [newCategory, setNewCategory] = useState('');
    const [showNewCategoryInput, setShowNewCategoryInput] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isLoadingSuggestion, setIsLoadingSuggestion] = useState(false);

    const updateFormData = (field: keyof FormData, value: string) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
    };

    const suggestCode = async () => {
        if (!formData.category || !formData.name || isEditing) return;

        setIsLoadingSuggestion(true);
        try {
            const response = await fetch(
                `/lab/services/suggest-code?category=${encodeURIComponent(formData.category)}&name=${encodeURIComponent(formData.name)}`,
            );
            const data = await response.json();
            if (data.code) {
                updateFormData('code', data.code);
            }
        } catch (error) {
            console.error('Failed to suggest code:', error);
        } finally {
            setIsLoadingSuggestion(false);
        }
    };

    useEffect(() => {
        if (
            formData.category &&
            formData.name &&
            !formData.code &&
            !isEditing
        ) {
            const timeout = setTimeout(suggestCode, 500);
            return () => clearTimeout(timeout);
        }
    }, [formData.category, formData.name, isEditing]);

    const handleCategoryChange = (value: string) => {
        if (value === 'add_new') {
            setShowNewCategoryInput(true);
            setNewCategory('');
        } else {
            updateFormData('category', value);
            setShowNewCategoryInput(false);
        }
    };

    const handleCreateCategory = async () => {
        if (!newCategory.trim()) return;

        try {
            const response = await fetch('/lab/services/create-category', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
                body: JSON.stringify({ name: newCategory.trim() }),
            });

            if (response.ok) {
                const data = await response.json();
                updateFormData('category', data.category);
                setShowNewCategoryInput(false);
                setNewCategory('');
            }
        } catch (error) {
            console.error('Failed to create category:', error);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        if (isEditing) {
            router.put(
                `/lab/services/configuration/${editingService.id}`,
                formData,
                {
                    onFinish: () => {
                        setIsSubmitting(false);
                        onClose();
                    },
                    onError: () => {
                        setIsSubmitting(false);
                    },
                },
            );
        } else {
            router.post('/lab/services/configuration', formData, {
                onFinish: () => {
                    setIsSubmitting(false);
                    onClose();
                    // Reset form
                    setFormData({
                        name: '',
                        code: '',
                        category: '',
                        description: '',
                        preparation_instructions: '',
                        price: '',
                        sample_type: '',
                        turnaround_time: '',
                        normal_range: '',
                        clinical_significance: '',
                    });
                },
                onError: () => {
                    setIsSubmitting(false);
                },
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Plus className="h-5 w-5" />
                        {isEditing ? 'Edit Lab Test' : 'Add New Lab Test'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the laboratory test service details. Note: Test code cannot be changed after creation.'
                            : 'Create a new laboratory test service. After creation, you can configure test parameters for structured result entry.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="name">Test Name *</Label>
                            <Input
                                id="name"
                                placeholder="e.g., Thyroid Function Test"
                                value={formData.name}
                                onChange={(e) =>
                                    updateFormData('name', e.target.value)
                                }
                                required
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="category">Category *</Label>
                            {showNewCategoryInput ? (
                                <div className="flex gap-2">
                                    <Input
                                        placeholder="New category name"
                                        value={newCategory}
                                        onChange={(e) =>
                                            setNewCategory(e.target.value)
                                        }
                                        onKeyPress={(e) =>
                                            e.key === 'Enter' &&
                                            handleCreateCategory()
                                        }
                                    />
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={handleCreateCategory}
                                    >
                                        Add
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setShowNewCategoryInput(false)
                                        }
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            ) : (
                                <Select
                                    value={formData.category}
                                    onValueChange={handleCategoryChange}
                                    required
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select category" />
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
                                        <SelectItem value="add_new">
                                            <Plus className="mr-1 h-3 w-3" />
                                            Add New Category
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="code">Test Code *</Label>
                            <div className="relative">
                                <Input
                                    id="code"
                                    placeholder="e.g., TFT001"
                                    value={formData.code}
                                    onChange={(e) =>
                                        updateFormData('code', e.target.value)
                                    }
                                    disabled={isEditing}
                                    required
                                />
                                {isLoadingSuggestion && (
                                    <Loader2 className="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin" />
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {isEditing
                                    ? 'Test codes cannot be changed after creation'
                                    : 'Auto-suggested based on category and name'}
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="price">Price ($) *</Label>
                            <Input
                                id="price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                value={formData.price}
                                onChange={(e) =>
                                    updateFormData('price', e.target.value)
                                }
                                required
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="sample_type">Sample Type *</Label>
                            <Select
                                value={formData.sample_type}
                                onValueChange={(value) =>
                                    updateFormData('sample_type', value)
                                }
                                required
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select sample type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {sampleTypes.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="turnaround_time">
                                Turnaround Time *
                            </Label>
                            <Select
                                value={formData.turnaround_time}
                                onValueChange={(value) =>
                                    updateFormData('turnaround_time', value)
                                }
                                required
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select turnaround time" />
                                </SelectTrigger>
                                <SelectContent>
                                    {turnaroundTimes.map((time) => (
                                        <SelectItem key={time} value={time}>
                                            {time}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            placeholder="Brief description of what this test measures..."
                            value={formData.description}
                            onChange={(e) =>
                                updateFormData('description', e.target.value)
                            }
                            rows={3}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="preparation_instructions">
                            Preparation Instructions
                        </Label>
                        <Textarea
                            id="preparation_instructions"
                            placeholder="e.g., Fast for 8 hours before test..."
                            value={formData.preparation_instructions}
                            onChange={(e) =>
                                updateFormData(
                                    'preparation_instructions',
                                    e.target.value,
                                )
                            }
                            rows={2}
                        />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="normal_range">Normal Range</Label>
                            <Input
                                id="normal_range"
                                placeholder="e.g., 12.0-16.0 g/dL"
                                value={formData.normal_range}
                                onChange={(e) =>
                                    updateFormData(
                                        'normal_range',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="clinical_significance">
                                Clinical Significance
                            </Label>
                            <Input
                                id="clinical_significance"
                                placeholder="What this test indicates"
                                value={formData.clinical_significance}
                                onChange={(e) =>
                                    updateFormData(
                                        'clinical_significance',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    {isEditing ? 'Updating...' : 'Creating...'}
                                </>
                            ) : isEditing ? (
                                'Update Test'
                            ) : (
                                'Create Test'
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
