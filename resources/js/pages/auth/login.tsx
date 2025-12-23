import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle, Lock, LogIn, User } from 'lucide-react';

interface LoginProps {
    status?: string;
}

export default function Login({ status }: LoginProps) {
    return (
        <AuthLayout
            title="Welcome back"
            description="Sign in to access your dashboard"
        >
            <Head title="Log in" />

            {status && (
                <div className="rounded-lg bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                    {status}
                </div>
            )}

            <Form
                action="/login"
                method="post"
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label
                                    htmlFor="username"
                                    className="text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Username
                                </Label>
                                <div className="relative">
                                    <User className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        id="username"
                                        type="text"
                                        name="username"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="username"
                                        placeholder="Enter your username"
                                        className="h-11 pl-10 transition-shadow focus:ring-2 focus:ring-primary/20"
                                    />
                                </div>
                                <InputError message={errors.username} />
                            </div>

                            <div className="space-y-2">
                                <Label
                                    htmlFor="password"
                                    className="text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Password
                                </Label>
                                <div className="relative">
                                    <Lock className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        id="password"
                                        type="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Enter your password"
                                        className="h-11 pl-10 transition-shadow focus:ring-2 focus:ring-primary/20"
                                    />
                                </div>
                                <InputError message={errors.password} />
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                                className="border-border data-[state=checked]:border-primary data-[state=checked]:bg-primary"
                            />
                            <Label
                                htmlFor="remember"
                                className="cursor-pointer text-sm text-muted-foreground"
                            >
                                Keep me signed in
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 h-11 w-full bg-primary font-medium shadow-lg shadow-primary/25 transition-all hover:bg-primary/90 hover:shadow-primary/30"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing ? (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            ) : (
                                <LogIn className="h-4 w-4" />
                            )}
                            Sign in
                        </Button>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
