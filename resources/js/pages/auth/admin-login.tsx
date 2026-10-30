import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    canResetPassword: boolean;
};

export default function AdminLogin({ canResetPassword }: Props) {
    return (
        <>
            <Head title="Admin log in" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="login">Email address</Label>
                            <Input
                                id="login"
                                type="email"
                                name="login"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                inputMode="email"
                                placeholder="admin@example.com"
                            />
                            <InputError message={errors.login} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">Password</Label>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="ml-auto text-sm"
                                        tabIndex={4}
                                    >
                                        Forgot your password?
                                    </TextLink>
                                )}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="Password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                            />
                            <Label htmlFor="remember">Remember me</Label>
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
                            tabIndex={4}
                            disabled={processing}
                            data-test="admin-login-button"
                        >
                            {processing && <Spinner />}
                            Log in
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

AdminLogin.layout = {
    title: 'Admin sign in',
    description: 'Use your admin email and password below',
    skipTelegram: true,
};
