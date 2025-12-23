import '../css/app.css';

import { Toaster } from '@/components/ui/sonner';
import { ThemeProvider } from '@/contexts/theme-context';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import type { ThemeConfig } from './types';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Handle session expiration - redirect to login on 403/419 errors
router.on('invalid', (event) => {
    const status = event.detail.response?.status;
    if (status === 403 || status === 419) {
        event.preventDefault();
        window.location.href = '/login';
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        const initialTheme = (
            props.initialPage.props as { theme?: ThemeConfig }
        ).theme;

        root.render(
            <ThemeProvider initialTheme={initialTheme}>
                <App {...props} />
                <Toaster />
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
