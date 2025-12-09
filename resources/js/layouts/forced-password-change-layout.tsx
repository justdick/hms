import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { type PropsWithChildren } from 'react';

interface ForcedPasswordChangeLayoutProps {
    title?: string;
    description?: string;
}

export default function ForcedPasswordChangeLayout({
    children,
    title = 'Change Your Password',
    description = 'You must change your password before continuing.',
}: PropsWithChildren<ForcedPasswordChangeLayoutProps>) {
    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-md">
                <div className="flex flex-col gap-6">
                    {/* Header with Logo and Logout */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-md">
                                <AppLogoIcon className="size-10 fill-current text-[var(--foreground)] dark:text-white" />
                            </div>
                            <span className="text-lg font-semibold">HMS</span>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleLogout}
                            className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <LogOut className="mr-2 h-4 w-4" />
                            Logout
                        </Button>
                    </div>

                    {/* Title and Description */}
                    <div className="space-y-2 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                        <p className="text-sm text-muted-foreground">{description}</p>
                    </div>

                    {/* Content */}
                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        {children}
                    </div>

                    {/* Footer */}
                    <p className="text-center text-xs text-muted-foreground">
                        For security reasons, you must change your password before accessing the system.
                    </p>
                </div>
            </div>
        </div>
    );
}
