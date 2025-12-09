import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import ForcedPasswordChangeLayout from '@/layouts/forced-password-change-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Form, Head, usePage } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useRef, useState, useMemo } from 'react';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { edit } from '@/routes/password';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: edit().url,
    },
];

interface PasswordRequirement {
    label: string;
    test: (password: string) => boolean;
}

const passwordRequirements: PasswordRequirement[] = [
    { label: 'At least 8 characters', test: (p) => p.length >= 8 },
    { label: 'Contains uppercase letter', test: (p) => /[A-Z]/.test(p) },
    { label: 'Contains lowercase letter', test: (p) => /[a-z]/.test(p) },
    { label: 'Contains a number', test: (p) => /\d/.test(p) },
    { label: 'Contains a symbol', test: (p) => /[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\\/`~;']/.test(p) },
];

function getPasswordStrength(password: string): { score: number; label: string; color: string } {
    const passedRequirements = passwordRequirements.filter((req) => req.test(password)).length;
    
    if (password.length === 0) {
        return { score: 0, label: '', color: 'bg-gray-200 dark:bg-gray-700' };
    }
    if (passedRequirements <= 2) {
        return { score: 1, label: 'Weak', color: 'bg-red-500' };
    }
    if (passedRequirements <= 3) {
        return { score: 2, label: 'Fair', color: 'bg-orange-500' };
    }
    if (passedRequirements <= 4) {
        return { score: 3, label: 'Good', color: 'bg-yellow-500' };
    }
    return { score: 4, label: 'Strong', color: 'bg-green-500' };
}

interface Props {
    mustChangePassword?: boolean;
}

function PasswordForm({ isForced = false }: { isForced?: boolean }) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const [newPassword, setNewPassword] = useState('');
    
    const { props } = usePage<{ flash?: { warning?: string; success?: string } }>();
    const flash = props.flash || {};

    const passwordStrength = useMemo(() => getPasswordStrength(newPassword), [newPassword]);

    return (
        <div className="space-y-6">
            {!isForced && (
                <HeadingSmall
                    title="Update password"
                    description="Ensure your account is using a long, random password to stay secure"
                />
            )}

            {flash.warning && (
                <Alert variant="destructive">
                    <AlertDescription>{flash.warning}</AlertDescription>
                </Alert>
            )}

            <Form
                {...PasswordController.update.form()}
                options={{
                    preserveScroll: true,
                }}
                resetOnError={[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]}
                resetOnSuccess
                onError={(errors) => {
                    if (errors.password) {
                        passwordInput.current?.focus();
                    }

                    if (errors.current_password) {
                        currentPasswordInput.current?.focus();
                    }
                }}
                className="space-y-4"
            >
                {({ errors, processing, recentlySuccessful }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">
                                Current password
                            </Label>

                            <Input
                                id="current_password"
                                ref={currentPasswordInput}
                                name="current_password"
                                type="password"
                                className="mt-1 block w-full"
                                autoComplete="current-password"
                                placeholder="Enter your current password"
                            />

                            <InputError
                                message={errors.current_password}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                New password
                            </Label>

                            <Input
                                id="password"
                                ref={passwordInput}
                                name="password"
                                type="password"
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                placeholder="Enter your new password"
                                value={newPassword}
                                onChange={(e) => setNewPassword(e.target.value)}
                            />

                            <InputError message={errors.password} />

                            {/* Password Strength Indicator */}
                            {newPassword.length > 0 && (
                                <div className="space-y-3 mt-2">
                                    {/* Strength Bar */}
                                    <div className="space-y-1">
                                        <div className="flex justify-between text-xs">
                                            <span className="text-gray-500 dark:text-gray-400">Password strength</span>
                                            <span className={`font-medium ${
                                                passwordStrength.score <= 1 ? 'text-red-600 dark:text-red-400' :
                                                passwordStrength.score === 2 ? 'text-orange-600 dark:text-orange-400' :
                                                passwordStrength.score === 3 ? 'text-yellow-600 dark:text-yellow-400' :
                                                'text-green-600 dark:text-green-400'
                                            }`}>
                                                {passwordStrength.label}
                                            </span>
                                        </div>
                                        <div className="flex gap-1">
                                            {[1, 2, 3, 4].map((level) => (
                                                <div
                                                    key={level}
                                                    className={`h-1.5 flex-1 rounded-full transition-colors ${
                                                        level <= passwordStrength.score
                                                            ? passwordStrength.color
                                                            : 'bg-gray-200 dark:bg-gray-700'
                                                    }`}
                                                />
                                            ))}
                                        </div>
                                    </div>

                                    {/* Requirements Checklist */}
                                    <div className="grid grid-cols-1 gap-1.5 text-xs">
                                        {passwordRequirements.map((req, index) => {
                                            const passed = req.test(newPassword);
                                            return (
                                                <div
                                                    key={index}
                                                    className={`flex items-center gap-1.5 ${
                                                        passed
                                                            ? 'text-green-600 dark:text-green-400'
                                                            : 'text-gray-500 dark:text-gray-400'
                                                    }`}
                                                >
                                                    {passed ? (
                                                        <Check className="h-3.5 w-3.5" />
                                                    ) : (
                                                        <X className="h-3.5 w-3.5" />
                                                    )}
                                                    <span>{req.label}</span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>

                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                placeholder="Confirm your new password"
                            />

                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <div className={`flex items-center gap-4 ${isForced ? 'pt-2' : ''}`}>
                            <Button
                                disabled={processing}
                                data-test="update-password-button"
                                className={isForced ? 'w-full' : ''}
                            >
                                {processing ? 'Saving...' : 'Save password'}
                            </Button>

                            {!isForced && (
                                <Transition
                                    show={recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-neutral-600">
                                        Saved
                                    </p>
                                </Transition>
                            )}
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}

export default function Password({ mustChangePassword = false }: Props) {
    // If user must change password, show minimal forced layout
    if (mustChangePassword) {
        return (
            <>
                <Head title="Change Password Required" />
                <ForcedPasswordChangeLayout
                    title="Change Your Password"
                    description="Your password has been reset. Please create a new password to continue."
                >
                    <PasswordForm isForced />
                </ForcedPasswordChangeLayout>
            </>
        );
    }

    // Normal settings page layout
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Password settings" />

            <SettingsLayout>
                <PasswordForm />
            </SettingsLayout>
        </AppLayout>
    );
}
