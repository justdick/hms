import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { LabOrdersTable } from '@/components/Ward/LabOrdersTable';
import { LabResultsDisplay } from '@/components/Ward/LabResultsDisplay';
import { FlaskConical } from 'lucide-react';
import { useMemo, useState } from 'react';

interface LabService {
    id: number;
    name: string;
    code: string;
    price: number;
}

interface LabOrder {
    id: number;
    lab_service?: LabService;
    status: string;
    ordered_at: string;
    priority: string;
    special_instructions?: string;
    result_values?: any;
    result_notes?: string;
    ordered_by?: {
        id: number;
        name: string;
    };
}

interface Props {
    labOrders: LabOrder[];
}

export function LabsTab({ labOrders }: Props) {
    const [selectedOrder, setSelectedOrder] = useState<LabOrder | null>(null);
    const [detailsModalOpen, setDetailsModalOpen] = useState(false);

    // Group lab orders by status
    const labOrdersByStatus = useMemo(() => {
        return {
            pending: labOrders.filter((lab) => lab.status === 'pending'),
            in_progress: labOrders.filter((lab) => lab.status === 'in_progress'),
            completed: labOrders.filter((lab) => lab.status === 'completed'),
            cancelled: labOrders.filter((lab) => lab.status === 'cancelled'),
        };
    }, [labOrders]);

    const handleViewDetails = (order: LabOrder) => {
        setSelectedOrder(order);
        setDetailsModalOpen(true);
    };

    // Empty state
    if (labOrders.length === 0) {
        return (
            <Card>
                <CardContent className="py-12">
                    <div className="flex flex-col items-center justify-center text-center">
                        <FlaskConical className="mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                        <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            No Lab Orders
                        </h3>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            No laboratory tests have been ordered for this patient yet.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            {/* Lab Orders Table */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FlaskConical className="h-5 w-5" />
                        Laboratory Orders
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <LabOrdersTable
                        labOrders={labOrders}
                        onViewDetails={handleViewDetails}
                    />
                </CardContent>
            </Card>

            {/* Details Modal */}
            {selectedOrder && (
                <Dialog open={detailsModalOpen} onOpenChange={setDetailsModalOpen}>
                    <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>Lab Order Details</DialogTitle>
                            <DialogDescription>
                                Detailed information and results for this laboratory test
                            </DialogDescription>
                        </DialogHeader>
                        <LabResultsDisplay order={selectedOrder} />
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}
