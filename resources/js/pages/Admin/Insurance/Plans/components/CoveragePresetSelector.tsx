import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { Check, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface CoveragePreset {
    id: string;
    name: string;
    description: string;
    coverages: {
        consultation: number;
        drug: number;
        lab: number;
        procedure: number;
        ward: number;
        nursing: number;
    } | null;
}

interface Props {
    selectedPresetId?: string;
    onPresetSelect: (preset: CoveragePreset) => void;
}

const categoryLabels: Record<string, string> = {
    consultation: 'Consultation',
    drug: 'Drugs',
    lab: 'Lab Services',
    procedure: 'Procedures',
    ward: 'Ward Services',
    nursing: 'Nursing Services',
};

export default function CoveragePresetSelector({
    selectedPresetId,
    onPresetSelect,
}: Props) {
    const [presets, setPresets] = useState<CoveragePreset[]>([]);
    const [loading, setLoading] = useState(true);
    const [selectedId, setSelectedId] = useState<string | undefined>(
        selectedPresetId,
    );

    useEffect(() => {
        fetchPresets();
    }, []);

    const fetchPresets = async () => {
        try {
            const response = await axios.get('/admin/insurance/coverage-presets');
            setPresets(response.data.presets);
        } catch (error) {
            console.error('Failed to fetch presets:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSelectPreset = (preset: CoveragePreset) => {
        setSelectedId(preset.id);
        onPresetSelect(preset);
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-12">
                <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div>
                <Label className="text-base">Choose a Coverage Preset</Label>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Select a preset to quickly configure coverage percentages, or choose Custom to set your own values.
                </p>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {presets.map((preset) => (
                    <Card
                        key={preset.id}
                        className={cn(
                            'cursor-pointer transition-all hover:border-primary',
                            selectedId === preset.id &&
                                'border-primary ring-2 ring-primary ring-offset-2',
                        )}
                        onClick={() => handleSelectPreset(preset)}
                    >
                        <CardHeader className="pb-3">
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <CardTitle className="text-lg">
                                        {preset.name}
                                    </CardTitle>
                                    <CardDescription className="mt-1">
                                        {preset.description}
                                    </CardDescription>
                                </div>
                                {selectedId === preset.id && (
                                    <div className="flex h-6 w-6 items-center justify-center rounded-full bg-primary">
                                        <Check className="h-4 w-4 text-white" />
                                    </div>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {preset.coverages ? (
                                <div className="space-y-2">
                                    <p className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        Coverage Preview:
                                    </p>
                                    <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                        {Object.entries(preset.coverages).map(
                                            ([category, percentage]) => (
                                                <div
                                                    key={category}
                                                    className="flex justify-between"
                                                >
                                                    <span className="text-gray-600 dark:text-gray-400">
                                                        {categoryLabels[category]}:
                                                    </span>
                                                    <span className="font-semibold text-gray-900 dark:text-gray-100">
                                                        {percentage}%
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    Configure all coverage percentages manually
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}
