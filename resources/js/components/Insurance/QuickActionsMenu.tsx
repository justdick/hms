import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ChevronDown, Plus, Copy, Upload, Download, FileText } from 'lucide-react';

interface QuickActionsMenuProps {
    planId: number;
    onAddException: () => void;
    onBulkImport: () => void;
    onExportRules: () => void;
    onCopyFromPlan?: () => void;
}

export default function QuickActionsMenu({
    planId,
    onAddException,
    onBulkImport,
    onExportRules,
    onCopyFromPlan,
}: QuickActionsMenuProps) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    Quick Actions
                    <ChevronDown className="ml-2 h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>Common Tasks</DropdownMenuLabel>
                <DropdownMenuSeparator />
                
                <DropdownMenuItem
                    onClick={onAddException}
                    className="cursor-pointer"
                >
                    <Plus className="mr-2 h-4 w-4" />
                    <span>Add Exception</span>
                    <kbd className="ml-auto rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                        N
                    </kbd>
                </DropdownMenuItem>
                
                <DropdownMenuItem
                    onClick={onBulkImport}
                    className="cursor-pointer"
                >
                    <Upload className="mr-2 h-4 w-4" />
                    <span>Bulk Import</span>
                </DropdownMenuItem>
                
                <DropdownMenuItem
                    onClick={onExportRules}
                    className="cursor-pointer"
                >
                    <Download className="mr-2 h-4 w-4" />
                    <span>Export Rules</span>
                </DropdownMenuItem>
                
                <DropdownMenuSeparator />
                
                {onCopyFromPlan && (
                    <DropdownMenuItem
                        onClick={onCopyFromPlan}
                        className="cursor-pointer"
                    >
                        <Copy className="mr-2 h-4 w-4" />
                        <span>Copy from Another Plan</span>
                    </DropdownMenuItem>
                )}
                
                <DropdownMenuItem
                    onClick={() => window.location.href = `/admin/insurance/plans/${planId}/coverage-rules`}
                    className="cursor-pointer"
                >
                    <FileText className="mr-2 h-4 w-4" />
                    <span>Manage All Rules</span>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
