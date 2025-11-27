import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { HelpCircle } from 'lucide-react';

interface HelpTooltipProps {
    content: string;
    example?: string;
    shortcut?: string;
    side?: 'top' | 'right' | 'bottom' | 'left';
    className?: string;
}

export function HelpTooltip({
    content,
    example,
    shortcut,
    side = 'top',
    className = '',
}: HelpTooltipProps) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    className={`inline-flex items-center text-gray-400 transition-colors hover:text-gray-600 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:hover:text-gray-300 ${className}`}
                    onClick={(e) => e.preventDefault()}
                    aria-label="Show help information"
                >
                    <HelpCircle className="h-4 w-4" aria-hidden="true" />
                    <span className="sr-only">Help</span>
                </button>
            </TooltipTrigger>
            <TooltipContent side={side} className="max-w-xs" role="tooltip">
                <div className="space-y-2">
                    <p className="text-sm">{content}</p>
                    {example && (
                        <p className="text-xs text-gray-300 italic dark:text-gray-400">
                            Example: {example}
                        </p>
                    )}
                    {shortcut && (
                        <p className="text-xs text-gray-300 dark:text-gray-400">
                            Keyboard shortcut:{' '}
                            <kbd className="rounded border border-gray-400 bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                {shortcut}
                            </kbd>
                        </p>
                    )}
                </div>
            </TooltipContent>
        </Tooltip>
    );
}
