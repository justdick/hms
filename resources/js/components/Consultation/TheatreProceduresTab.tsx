import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { Plus, Stethoscope, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface ProcedureType {
    id: number;
    name: string;
    code: string;
    type: 'minor' | 'major';
    category: string;
    price: number;
}

interface ConsultationProcedure {
    id: number;
    procedure_type: ProcedureType;
    comments: string | null;
    performed_at: string;
    doctor: {
        id: number;
        name: string;
    };
}

interface Props {
    consultationId: number;
    procedures: ConsultationProcedure[];
    availableProcedures: ProcedureType[];
}

export default function TheatreProceduresTab({
    consultationId,
    procedures,
    availableProcedures,
}: Props) {
    const [selectedProcedureId, setSelectedProcedureId] = useState<
        string | null
    >(null);
    const [comments, setComments] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleAddProcedure = () => {
        if (!selectedProcedureId) {
            toast.error('Please select a procedure');
            return;
        }

        setIsSubmitting(true);

        router.post(
            `/consultation/${consultationId}/procedures`,
            {
                minor_procedure_type_id: selectedProcedureId,
                comments: comments || null,
                performed_at: new Date().toISOString(),
            },
            {
                onSuccess: () => {
                    toast.success('Procedure added successfully');
                    setSelectedProcedureId(null);
                    setComments('');
                },
                onError: (errors) => {
                    toast.error(errors.error || 'Failed to add procedure');
                },
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    const handleDeleteProcedure = (procedureId: number) => {
        if (
            !confirm(
                'Are you sure you want to remove this procedure from the consultation?',
            )
        ) {
            return;
        }

        router.delete(
            `/consultation/${consultationId}/procedures/${procedureId}`,
            {
                onSuccess: () => {
                    toast.success('Procedure removed successfully');
                },
                onError: (errors) => {
                    toast.error(errors.error || 'Failed to remove procedure');
                },
            },
        );
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-KE', {
            style: 'currency',
            currency: 'KES',
        }).format(amount);
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-KE', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    };

    return (
        <div className="space-y-6">
            {/* Add Procedure Form */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Plus className="h-5 w-5" />
                        Document Procedure
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="procedure">
                                Select Procedure *
                            </Label>
                            <Select
                                value={selectedProcedureId || ''}
                                onValueChange={setSelectedProcedureId}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a procedure..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableProcedures.map((proc) => (
                                        <SelectItem
                                            key={proc.id}
                                            value={proc.id.toString()}
                                        >
                                            <div className="flex items-center gap-2">
                                                <span>{proc.name}</span>
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        proc.type === 'major'
                                                            ? 'border-purple-200 bg-purple-100 text-purple-700'
                                                            : 'border-blue-200 bg-blue-100 text-blue-700'
                                                    }
                                                >
                                                    {proc.type === 'major'
                                                        ? 'Major'
                                                        : 'Minor'}
                                                </Badge>
                                                <span className="text-muted-foreground">
                                                    {formatCurrency(proc.price)}
                                                </span>
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="comments">
                                Comments (Optional)
                            </Label>
                            <Textarea
                                id="comments"
                                placeholder="Add any relevant notes about the procedure..."
                                value={comments}
                                onChange={(e) => setComments(e.target.value)}
                                rows={3}
                            />
                        </div>
                    </div>

                    <Button
                        onClick={handleAddProcedure}
                        disabled={!selectedProcedureId || isSubmitting}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        {isSubmitting ? 'Adding...' : 'Add Procedure'}
                    </Button>
                </CardContent>
            </Card>

            {/* Procedures List */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Stethoscope className="h-5 w-5" />
                        Documented Procedures ({procedures.length})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {procedures.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Stethoscope className="mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-semibold">
                                No Procedures Documented
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Add procedures performed during this
                                consultation
                            </p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Procedure</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Comments</TableHead>
                                    <TableHead>Performed By</TableHead>
                                    <TableHead>Date/Time</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {procedures.map((procedure) => (
                                    <TableRow key={procedure.id}>
                                        <TableCell className="font-medium">
                                            {procedure.procedure_type.name}
                                            <div className="text-xs text-muted-foreground">
                                                {procedure.procedure_type.code}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    procedure.procedure_type
                                                        .type === 'major'
                                                        ? 'border-purple-200 bg-purple-100 text-purple-700'
                                                        : 'border-blue-200 bg-blue-100 text-blue-700'
                                                }
                                            >
                                                {procedure.procedure_type
                                                    .type === 'major'
                                                    ? 'Major'
                                                    : 'Minor'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="capitalize">
                                            {procedure.procedure_type.category.replace(
                                                /_/g,
                                                ' ',
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate">
                                            {procedure.comments || (
                                                <span className="text-muted-foreground">
                                                    No comments
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {procedure.doctor.name}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {formatDateTime(
                                                procedure.performed_at,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    handleDeleteProcedure(
                                                        procedure.id,
                                                    )
                                                }
                                                className="text-destructive hover:text-destructive"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
