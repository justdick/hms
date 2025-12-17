import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { InsurancePlan } from '../Index';

interface PlanSelectorProps {
    plans: InsurancePlan[];
    selectedPlanId: number | null;
    onPlanChange: (planId: string | null) => void;
}

export function PlanSelector({
    plans,
    selectedPlanId,
    onPlanChange,
}: PlanSelectorProps) {
    // Separate NHIS and private insurance plans
    const nhisPlan = plans.find((p) => p.is_nhis);
    const privatePlans = plans.filter((p) => !p.is_nhis);

    // Group private plans by provider
    const groupedPrivatePlans = privatePlans.reduce(
        (acc, plan) => {
            const provider = plan.provider_name || 'Other';
            if (!acc[provider]) {
                acc[provider] = [];
            }
            acc[provider].push(plan);
            return acc;
        },
        {} as Record<string, InsurancePlan[]>,
    );

    return (
        <Select
            value={selectedPlanId?.toString() || 'none'}
            onValueChange={(value) =>
                onPlanChange(value === 'none' ? null : value)
            }
        >
            <SelectTrigger className="w-[300px]" id="plan-selector">
                <SelectValue placeholder="Select insurance plan" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="none">
                    <span className="text-gray-500">Cash Prices Only</span>
                </SelectItem>

                {/* NHIS Plan - Highlighted */}
                {nhisPlan && (
                    <SelectGroup>
                        <SelectLabel className="text-green-600 dark:text-green-400">
                            NHIS (Government)
                        </SelectLabel>
                        <SelectItem
                            value={nhisPlan.id.toString()}
                            className="font-medium"
                        >
                            <div className="flex items-center gap-2">
                                <span className="h-2 w-2 rounded-full bg-green-500" />
                                {nhisPlan.name}
                            </div>
                        </SelectItem>
                    </SelectGroup>
                )}

                {/* Private Insurance Plans - Grouped by Provider */}
                {Object.entries(groupedPrivatePlans).map(([provider, providerPlans]) => (
                    <SelectGroup key={provider}>
                        <SelectLabel>{provider}</SelectLabel>
                        {providerPlans.map((plan) => (
                            <SelectItem key={plan.id} value={plan.id.toString()}>
                                {plan.name}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                ))}
            </SelectContent>
        </Select>
    );
}
