import { useEffect } from 'react';

interface KeyboardShortcut {
    key: string;
    callback: () => void;
    ctrlKey?: boolean;
    shiftKey?: boolean;
    altKey?: boolean;
    metaKey?: boolean;
}

export function useKeyboardShortcuts(shortcuts: KeyboardShortcut[]) {
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            // Don't trigger shortcuts when user is typing in an input, textarea, or contenteditable
            const target = event.target as HTMLElement;
            if (
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.isContentEditable
            ) {
                return;
            }

            for (const shortcut of shortcuts) {
                const keyMatches = event.key.toLowerCase() === shortcut.key.toLowerCase();
                const ctrlMatches = shortcut.ctrlKey ? event.ctrlKey : !event.ctrlKey;
                const shiftMatches = shortcut.shiftKey ? event.shiftKey : !event.shiftKey;
                const altMatches = shortcut.altKey ? event.altKey : !event.altKey;
                const metaMatches = shortcut.metaKey ? event.metaKey : !event.metaKey;

                if (keyMatches && ctrlMatches && shiftMatches && altMatches && metaMatches) {
                    event.preventDefault();
                    shortcut.callback();
                    break;
                }
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [shortcuts]);
}
