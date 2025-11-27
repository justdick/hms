import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { router } from '@inertiajs/react';
import { CheckCircle, Package, Pill, User } from 'lucide-react';
import { useState } from 'react';

interface Drug {
    id: number;
    name: string;
    form: string;
    unit_type: string;
    unit_price: number;
}

interface MinorProcedureType {
    id: number;
    name: string;
    code: string;
}

interface MinorProcedure {
    id: number;
    procedure_type: MinorProcedureType;
    performed_at: string;
}

interface Supply {
    id: number;
    minor_procedure_id: number;
    drug_id: number;
    drug: Drug;
    quantity: number;
    dispensed: boolean;
    dispensed_at: string | null;
    dispensed_by: number | null;
    minor_procedure: MinorProcedure;
}

interface SupplyData {
    supply: Supply;
    procedure_type: string;
    performed_at: string;
}

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    patient: Patient;
    supplies: SupplyData[];
}

export function DispenseSuppliesModal({
    open,
    onOpenChange,
    patient,
    supplies,
}: Props) {
    const [dispensing, setDispensing] = useState<Set<number>>(new Set());

    const allDispensed = supplies.every((sd) => sd.supply.dispensed);

    const handleDispense = (supplyId: number) => {
        setDispensing((prev) => new Set(prev).add(supplyId));

        router.post(
            `/pharmacy/supplies/${supplyId}/dispense`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setDispensing((prev) => {
                        const next = new Set(prev);
                        next.delete(supplyId);
                        return next;
                    });
                },
                onSuccess: () => {
                    // Check if all supplies are now dispensed
                    const allDone = supplies.every(
                        (sd) =>
                            sd.supply.id === supplyId || sd.supply.dispensed,
                    );
                    if (allDone) {
                        onOpenChange(false);
                    }
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-4xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Pill className="h-5 w-5" />
                        Dispense Minor Procedure Supplies
                    </DialogTitle>
                    <DialogDescription>
                        <div className="flex items-center gap-2">
                            <User className="h-4 w-4" />
                            <span className="font-medium">
                                {patient.full_name}
                            </span>
                            <span className="text-muted-foreground">
                                ({patient.patient_number})
                            </span>
                        </div>
                    </DialogDescription>
                </DialogHeader>

                <ScrollArea className="max-h-[60vh]">
                    <div className="space-y-4">
                        {allDispensed && (
                            <Alert className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                                <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                                <AlertDescription className="text-green-900 dark:text-green-100">
                                    All supplies have been dispensed
                                </AlertDescription>
                            </Alert>
                        )}

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Drug</TableHead>
                                    <TableHead>Procedure</TableHead>
                                    <TableHead>Quantity</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Action
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {supplies.map((supplyData) => {
                                    const { supply } = supplyData;
                                    const isDispensing = dispensing.has(
                                        supply.id,
                                    );
                                    const totalAmount =
                                        supply.drug.unit_price *
                                        supply.quantity;

                                    return (
                                        <TableRow key={supply.id}>
                                            <TableCell>
                                                <div>
                                                    <div className="font-medium">
                                                        {supply.drug.name}
                                                    </div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {supply.drug.form}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-sm">
                                                    {supplyData.procedure_type}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {new Date(
                                                        supplyData.performed_at,
                                                    ).toLocaleDateString()}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Package className="h-4 w-4 text-muted-foreground" />
                                                    <span>
                                                        {supply.quantity}{' '}
                                                        {supply.drug.unit_type}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-medium">
                                                    KES{' '}
                                                    {totalAmount.toLocaleString()}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {supply.dispensed ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-green-200 bg-green-50 text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300"
                                                    >
                                                        <CheckCircle className="mr-1 h-3 w-3" />
                                                        Dispensed
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        Pending
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {!supply.dispensed && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDispense(
                                                                supply.id,
                                                            )
                                                        }
                                                        disabled={isDispensing}
                                                    >
                                                        {isDispensing
                                                            ? 'Dispensing...'
                                                            : 'Dispense'}
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                </ScrollArea>

                <div className="flex justify-end gap-2 border-t pt-4">
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
