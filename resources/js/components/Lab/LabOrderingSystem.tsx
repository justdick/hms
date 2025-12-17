import AsyncLabServiceSelect from '@/components/Lab/AsyncLabServiceSelect';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { AlertTriangle, TestTube, X } from 'lucide-react';
import { useState } from 'react';

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    sample_type: string;
    turnaround_time: string;
}

interface LabOrder {
    lab_service_id: number;
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
}

interface SelectedTest extends LabOrder {
    service: LabService;
}

interface LabOrderingSystemProps {
    onOrderSubmit: (orders: LabOrder[]) => void;
    onClose?: () => void;
}

export default function LabOrderingSystem({
    onOrderSubmit,
    onClose,
}: LabOrderingSystemProps) {
    const [selectedTests, setSelectedTests] = useState<Map<number, SelectedTest>>(
        new Map(),
    );

    const handleServiceSelect = (service: LabService) => {
        if (selectedTests.has(service.id)) {
            return; // Already selected
        }

        const newSelected = new Map(selectedTests);
        newSelected.set(service.id, {
            lab_service_id: service.id,
            priority: 'routine',
            special_instructions: '',
            service,
        });
        setSelectedTests(newSelected);
    };

    const updateTestOrder = (serviceId: number, updates: Partial<LabOrder>) => {
        const newSelected = new Map(selectedTests);
        const existing = newSelected.get(serviceId);
        if (existing) {
            newSelected.set(serviceId, { ...existing, ...updates });
            setSelectedTests(newSelected);
        }
    };

    const removeTest = (serviceId: number) => {
        const newSelected = new Map(selectedTests);
        newSelected.delete(serviceId);
        setSelectedTests(newSelected);
    };

    const handleSubmit = () => {
        const orders = Array.from(selectedTests.values()).map(
            ({ lab_service_id, priority, special_instructions }) => ({
                lab_service_id,
                priority,
                special_instructions,
            }),
        );
        onOrderSubmit(orders);
    };

    const getPriorityColor = (priority: string) => {
        const colors = {
            routine: 'bg-blue-100 text-blue-800',
            urgent: 'bg-orange-100 text-orange-800',
            stat: 'bg-red-100 text-red-800',
        };
        return colors[priority as keyof typeof colors] || colors.routine;
    };

    const excludeIds = Array.from(selectedTests.keys());

    return (
        <Dialog open onOpenChange={() => onClose?.()}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-hidden">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <TestTube className="h-5 w-5" />
                        Order Lab Tests
                    </DialogTitle>
                    <DialogDescription>
                        Search and select laboratory tests to order
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    {/* Search */}
                    <div>
                        <Label>Search Lab Tests</Label>
                        <AsyncLabServiceSelect
                            onSelect={handleServiceSelect}
                            excludeIds={excludeIds}
                            placeholder="Search by test name or code..."
                        />
                    </div>

                    {/* Selected Tests */}
                    <div className="max-h-[400px] overflow-y-auto">
                        {selectedTests.size === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                <TestTube className="mx-auto mb-2 h-12 w-12 opacity-50" />
                                <p>No tests selected</p>
                                <p className="text-sm">
                                    Search and select tests above
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {Array.from(selectedTests.values()).map(
                                    (test) => (
                                        <Card key={test.lab_service_id}>
                                            <CardContent className="pt-4">
                                                <div className="mb-3 flex items-start justify-between">
                                                    <div>
                                                        <h3 className="font-medium">
                                                            {test.service.name}
                                                        </h3>
                                                        <p className="text-sm text-muted-foreground">
                                                            {test.service.code} •{' '}
                                                            {test.service.category}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Sample: {test.service.sample_type}
                                                        </p>
                                                    </div>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeTest(
                                                                test.lab_service_id,
                                                            )
                                                        }
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </div>

                                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <div>
                                                        <Label>Priority</Label>
                                                        <Select
                                                            value={test.priority}
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                updateTestOrder(
                                                                    test.lab_service_id,
                                                                    {
                                                                        priority:
                                                                            value as any,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger>
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="routine">
                                                                    Routine
                                                                </SelectItem>
                                                                <SelectItem value="urgent">
                                                                    Urgent
                                                                </SelectItem>
                                                                <SelectItem value="stat">
                                                                    STAT
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="flex items-center">
                                                        <Badge
                                                            className={getPriorityColor(
                                                                test.priority,
                                                            )}
                                                        >
                                                            {test.priority.toUpperCase()}
                                                        </Badge>
                                                        {test.priority ===
                                                            'stat' && (
                                                            <AlertTriangle className="ml-2 h-4 w-4 text-red-500" />
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="mt-3">
                                                    <Label>
                                                        Special Instructions
                                                    </Label>
                                                    <Textarea
                                                        placeholder="Any special instructions..."
                                                        value={
                                                            test.special_instructions ||
                                                            ''
                                                        }
                                                        onChange={(e) =>
                                                            updateTestOrder(
                                                                test.lab_service_id,
                                                                {
                                                                    special_instructions:
                                                                        e.target
                                                                            .value,
                                                                },
                                                            )
                                                        }
                                                        className="mt-1"
                                                        rows={2}
                                                    />
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ),
                                )}
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    {selectedTests.size > 0 && (
                        <div className="flex items-center justify-between border-t pt-4">
                            <p className="text-sm text-muted-foreground">
                                {selectedTests.size} test
                                {selectedTests.size !== 1 ? 's' : ''} selected
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        setSelectedTests(new Map())
                                    }
                                >
                                    Clear All
                                </Button>
                                <Button onClick={handleSubmit}>
                                    <TestTube className="mr-2 h-4 w-4" />
                                    Submit Orders
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
