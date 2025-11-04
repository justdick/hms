import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Keyboard } from 'lucide-react';

interface Shortcut {
    key: string;
    description: string;
}

interface KeyboardShortcutsHelpProps {
    shortcuts: Shortcut[];
}

export default function KeyboardShortcutsHelp({
    shortcuts,
}: KeyboardShortcutsHelpProps) {
    return (
        <Card className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base text-blue-900 dark:text-blue-100">
                    <Keyboard className="h-5 w-5" />
                    Keyboard Shortcuts
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="space-y-2">
                    {shortcuts.map((shortcut) => (
                        <div
                            key={shortcut.key}
                            className="flex items-center justify-between text-sm"
                        >
                            <span className="text-blue-800 dark:text-blue-200">
                                {shortcut.description}
                            </span>
                            <kbd className="rounded border border-blue-300 bg-white px-2 py-1 font-mono text-xs text-blue-900 dark:border-blue-700 dark:bg-blue-900 dark:text-blue-100">
                                {shortcut.key}
                            </kbd>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
