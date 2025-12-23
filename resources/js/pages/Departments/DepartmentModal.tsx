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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { Building2, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface Department {
    id: number;
    name: string;
    code: string;
    description?: string;
    type: string;
    is_active: boolean;
    checkins_count?: number;
    users_count?: number;
}

interface DepartmentModalProps {
    open: boolean;
    onClose: () => void;
    department?: Department | null;
    types: Record<string, string>;
}

interface FormData {
    name: string;
    code: string;
    description: string;
    type: string;
    is_active: boolean;
}

export default function DepartmentModal({
    open,
    onClose,
    department,
    types,
}: DepartmentModalProps) {
    const isEditing = !!department;

    const [formData, setFormData] = useState<FormData>({
        name: '',
        code: '',
        description: '',
        type: 'opd',
        is_active: true,
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (department) {
            setFormData({
                name: department.name,
                code: department.code,
                description: department.description || '',
                type: department.type,
                is_active: department.is_active,
            });
        } else {
            setFormData({
                name: '',
                code: '',
                description: '',
                type: 'opd',
                is_active: true,
            });
        }
        setErrors({});
    }, [department, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setErrors({});

        const options = {
            onSuccess: () => {
                setIsSubmitting(false);
                onClose();
            },
            onError: (errors: Record<string, string>) => {
                setIsSubmitting(false);
                setErrors(errors);
            },
        };

        if (isEditing) {
            router.put(`/departments/${department.id}`, formData, options);
        } else {
            router.post('/departments', formData, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Building2 className="h-5 w-5" />
                        {isEditing ? 'Edit Department' : 'Add Department'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update department details.'
                            : 'Add a new department or clinic to the system.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="name">Name *</Label>
                            <Input
                                id="name"
                                value={formData.name}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        name: e.target.value,
                                    })
                                }
                                placeholder="e.g., General OPD"
                            />
                            {errors.name && (
                                <p className="text-sm text-red-600">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="code">Code *</Label>
                            <Input
                                id="code"
                                value={formData.code}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        code: e.target.value.toUpperCase(),
                                    })
                                }
                                placeholder="e.g., OPDC"
                                maxLength={10}
                            />
                            {errors.code && (
                                <p className="text-sm text-red-600">
                                    {errors.code}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Max 10 chars, used for NHIS
                            </p>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            value={formData.description}
                            onChange={(e) =>
                                setFormData({
                                    ...formData,
                                    description: e.target.value,
                                })
                            }
                            placeholder="Brief description"
                            rows={2}
                        />
                        {errors.description && (
                            <p className="text-sm text-red-600">
                                {errors.description}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="type">Type *</Label>
                            <Select
                                value={formData.type}
                                onValueChange={(value) =>
                                    setFormData({ ...formData, type: value })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(types).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            {errors.type && (
                                <p className="text-sm text-red-600">
                                    {errors.type}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center space-x-3 pt-8">
                            <Switch
                                id="is_active"
                                checked={formData.is_active}
                                onCheckedChange={(checked) =>
                                    setFormData({
                                        ...formData,
                                        is_active: checked,
                                    })
                                }
                            />
                            <Label htmlFor="is_active">Active</Label>
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
                                    {isEditing ? 'Saving...' : 'Creating...'}
                                </>
                            ) : isEditing ? (
                                'Save Changes'
                            ) : (
                                'Create Department'
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
