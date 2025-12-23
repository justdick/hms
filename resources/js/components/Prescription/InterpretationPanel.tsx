import { cn } from '@/lib/utils';
import {
    AlertCircle,
    AlertTriangle,
    Calendar,
    CheckCircle2,
    Clock,
    Package,
    Pill,
} from 'lucide-react';

interface Drug {
    id: number;
    name: string;
    form: string;
    strength?: string;
    unit_type: string;
    bottle_size?: number;
}

export interface ParsedPrescription {
    isValid: boolean;
    doseQuantity: string | null;
    frequency: string | null;
    frequencyCode: string | null;
    duration: string | null;
    durationDays: number | null;
    quantityToDispense: number | null;
    scheduleType:
        | 'standard'
        | 'split_dose'
        | 'custom_interval'
        | 'taper'
        | 'stat'
        | 'prn'
        | 'topical'
        | 'interval';
    schedulePattern?: {
        type?: string;
        pattern?: { morning: number; noon: number; evening: number };
        intervals_hours?: number[];
        dose_per_interval?: number;
        total_doses?: number;
        doses?: number[];
        duration_days?: number;
    } | null;
    displayText: string | null;
    errors: string[];
    warnings: string[];
}

interface InterpretationPanelProps {
    result: ParsedPrescription | null;
    drug: Drug | null;
    isLoading?: boolean;
    onSwitchToClassic?: () => void;
}

export function InterpretationPanel({
    result,
    drug,
    isLoading = false,
    onSwitchToClassic,
}: InterpretationPanelProps) {
    if (isLoading) {
        return (
            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                    <div className="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-600 dark:border-gray-600 dark:border-t-gray-300" />
                    <span className="text-sm">Parsing prescription...</span>
                </div>
            </div>
        );
    }

    if (!result) {
        return null;
    }

    const hasErrors = result.errors.length > 0;
    const hasWarnings = result.warnings.length > 0;
    const isPartiallyValid =
        !result.isValid &&
        (result.doseQuantity || result.frequency || result.duration);

    // Determine border and background colors based on state
    const borderColor = result.isValid
        ? 'border-green-300 dark:border-green-700'
        : isPartiallyValid || hasWarnings
          ? 'border-yellow-300 dark:border-yellow-700'
          : 'border-red-300 dark:border-red-700';

    const bgColor = result.isValid
        ? 'bg-green-50 dark:bg-green-950/20'
        : isPartiallyValid || hasWarnings
          ? 'bg-yellow-50 dark:bg-yellow-950/20'
          : 'bg-red-50 dark:bg-red-950/20';

    const iconColor = result.isValid
        ? 'text-green-600 dark:text-green-400'
        : isPartiallyValid || hasWarnings
          ? 'text-yellow-600 dark:text-yellow-400'
          : 'text-red-600 dark:text-red-400';

    const StatusIcon = result.isValid
        ? CheckCircle2
        : isPartiallyValid || hasWarnings
          ? AlertTriangle
          : AlertCircle;

    return (
        <div className={cn('rounded-lg border p-4', borderColor, bgColor)}>
            {/* Header */}
            <div className="mb-3 flex items-center gap-2">
                <StatusIcon className={cn('h-5 w-5', iconColor)} />
                <span className={cn('text-sm font-medium', iconColor)}>
                    {result.isValid
                        ? 'Prescription Parsed Successfully'
                        : isPartiallyValid
                          ? 'Partial Match'
                          : 'Unable to Parse'}
                </span>
            </div>

            {/* Valid Result Display */}
            {result.isValid && (
                <div className="space-y-3">
                    {/* Main prescription details */}
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {result.doseQuantity && (
                            <div className="flex items-start gap-2">
                                <Pill className="mt-0.5 h-4 w-4 text-gray-500 dark:text-gray-400" />
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Dose
                                    </p>
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {result.doseQuantity}
                                        {drug &&
                                            drug.unit_type === 'piece' &&
                                            ` ${drug.form}(s)`}
                                        {drug &&
                                            (drug.unit_type === 'bottle' ||
                                                drug.unit_type === 'vial') &&
                                            ' ml'}
                                    </p>
                                </div>
                            </div>
                        )}
                        {result.frequency && (
                            <div className="flex items-start gap-2">
                                <Clock className="mt-0.5 h-4 w-4 text-gray-500 dark:text-gray-400" />
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Frequency
                                    </p>
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {result.frequency}
                                    </p>
                                </div>
                            </div>
                        )}
                        {result.duration && (
                            <div className="flex items-start gap-2">
                                <Calendar className="mt-0.5 h-4 w-4 text-gray-500 dark:text-gray-400" />
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Duration
                                    </p>
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {result.duration}
                                    </p>
                                </div>
                            </div>
                        )}
                        {result.quantityToDispense !== null && (
                            <div className="flex items-start gap-2">
                                <Package className="mt-0.5 h-4 w-4 text-gray-500 dark:text-gray-400" />
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Quantity
                                    </p>
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {result.quantityToDispense}
                                        {drug &&
                                            getUnitLabel(
                                                drug,
                                                result.quantityToDispense,
                                            )}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Schedule details for custom patterns */}
                    {renderScheduleDetails(result)}

                    {/* Warnings */}
                    {hasWarnings && (
                        <div className="mt-2 space-y-1">
                            {result.warnings.map((warning, index) => (
                                <p
                                    key={index}
                                    className="flex items-center gap-1.5 text-xs text-yellow-700 dark:text-yellow-400"
                                >
                                    <AlertTriangle className="h-3 w-3" />
                                    {warning}
                                </p>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {/* Partial/Invalid Result Display */}
            {!result.isValid && (
                <div className="space-y-3">
                    {/* Show what was recognized */}
                    {isPartiallyValid && (
                        <div className="space-y-2">
                            <p className="text-xs text-gray-600 dark:text-gray-400">
                                Recognized:
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {result.doseQuantity && (
                                    <span className="rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                        Dose: {result.doseQuantity}
                                    </span>
                                )}
                                {result.frequency && (
                                    <span className="rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                        Frequency: {result.frequency}
                                    </span>
                                )}
                                {result.duration && (
                                    <span className="rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                        Duration: {result.duration}
                                    </span>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Error messages */}
                    {hasErrors && (
                        <div className="space-y-1">
                            {result.errors.map((error, index) => (
                                <p
                                    key={index}
                                    className="text-sm text-red-700 dark:text-red-400"
                                >
                                    {error}
                                </p>
                            ))}
                        </div>
                    )}

                    {/* Switch to Classic mode link */}
                    {onSwitchToClassic && (
                        <button
                            type="button"
                            onClick={onSwitchToClassic}
                            className="text-sm text-blue-600 underline hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                        >
                            Switch to Classic Mode
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

function getUnitLabel(drug: Drug, quantity: number): string {
    if (drug.unit_type === 'piece') {
        if (drug.form === 'tablet')
            return quantity === 1 ? ' tablet' : ' tablets';
        if (drug.form === 'capsule')
            return quantity === 1 ? ' capsule' : ' capsules';
        return ' pieces';
    }
    if (drug.unit_type === 'bottle')
        return quantity === 1 ? ' bottle' : ' bottles';
    if (drug.unit_type === 'vial') return quantity === 1 ? ' vial' : ' vials';
    if (drug.unit_type === 'tube') return quantity === 1 ? ' tube' : ' tubes';
    return '';
}

function renderScheduleDetails(result: ParsedPrescription): React.ReactNode {
    if (!result.schedulePattern) return null;

    const { scheduleType, schedulePattern } = result;

    if (scheduleType === 'split_dose' && schedulePattern.pattern) {
        const { morning, noon, evening } = schedulePattern.pattern;
        return (
            <div className="rounded bg-green-100 p-2 dark:bg-green-900/30">
                <p className="text-xs font-medium text-green-800 dark:text-green-300">
                    Split Dose Schedule
                </p>
                <p className="text-sm text-green-700 dark:text-green-400">
                    Morning: {morning} | Noon: {noon} | Evening: {evening}
                </p>
            </div>
        );
    }

    if (scheduleType === 'custom_interval' && schedulePattern.intervals_hours) {
        return (
            <div className="rounded bg-green-100 p-2 dark:bg-green-900/30">
                <p className="text-xs font-medium text-green-800 dark:text-green-300">
                    Custom Interval Schedule
                </p>
                <p className="text-sm text-green-700 dark:text-green-400">
                    At hours: {schedulePattern.intervals_hours.join('h, ')}h
                </p>
                {schedulePattern.dose_per_interval && (
                    <p className="text-xs text-green-600 dark:text-green-500">
                        {schedulePattern.dose_per_interval} dose(s) at each
                        interval
                    </p>
                )}
            </div>
        );
    }

    if (scheduleType === 'taper' && schedulePattern.doses) {
        return (
            <div className="rounded bg-green-100 p-2 dark:bg-green-900/30">
                <p className="text-xs font-medium text-green-800 dark:text-green-300">
                    Taper Schedule
                </p>
                <div className="mt-1 flex flex-wrap gap-1">
                    {schedulePattern.doses.map((dose, index) => (
                        <span
                            key={index}
                            className="rounded bg-green-200 px-1.5 py-0.5 text-xs text-green-800 dark:bg-green-800/50 dark:text-green-300"
                        >
                            Day {index + 1}: {dose}
                        </span>
                    ))}
                </div>
            </div>
        );
    }

    return null;
}
