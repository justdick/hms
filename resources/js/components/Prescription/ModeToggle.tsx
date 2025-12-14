import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import { Sparkles, List } from 'lucide-react';

export type PrescriptionMode = 'smart' | 'classic';

interface ModeToggleProps {
    mode: PrescriptionMode;
    onChange: (mode: PrescriptionMode) => void;
    disabled?: boolean;
}

export function ModeToggle({ mode, onChange, disabled = false }: ModeToggleProps) {
    const isSmartMode = mode === 'smart';

    return (
        <div className="flex items-center gap-3">
            <div
                className={cn(
                    'flex items-center gap-1.5 text-sm font-medium transition-colors',
                    !isSmartMode
                        ? 'text-gray-900 dark:text-gray-100'
                        : 'text-gray-400 dark:text-gray-500'
                )}
            >
                <List className="h-4 w-4" />
                <span>Classic</span>
            </div>
            <Switch
                id="prescription-mode"
                checked={isSmartMode}
                onCheckedChange={(checked) => onChange(checked ? 'smart' : 'classic')}
                disabled={disabled}
                aria-label="Toggle prescription input mode"
            />
            <div
                className={cn(
                    'flex items-center gap-1.5 text-sm font-medium transition-colors',
                    isSmartMode
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-gray-400 dark:text-gray-500'
                )}
            >
                <Sparkles className="h-4 w-4" />
                <span>Smart</span>
            </div>
        </div>
    );
}
