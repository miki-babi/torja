import { Form, Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import {
    IDENTIFIER_META,
    IdentifierToggle,
    type IdentifierType,
} from '@/components/identifier-toggle';
import { memberApi } from '@/components/member/member-api';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import {
    complete,
    init,
    status as telegramStatus,
} from '@/routes/telegram/login';
import PasskeyVerify from '@/components/passkey-verify';

type Props = {
    status?: string;
    canResetPassword: boolean;
    telegramAvailable: boolean;
};

export default function Login({
    status,
    canResetPassword,
    telegramAvailable,
}: Props) {
    const [telegramState, setTelegramState] = useState<
        'idle' | 'waiting' | 'error'
    >('idle');
    const [identifierType, setIdentifierType] =
        useState<IdentifierType>('email');
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        return () => {
            if (pollRef.current !== null) {
                clearInterval(pollRef.current);
            }
        };
    }, []);

    async function startTelegramLogin() {
        if (pollRef.current !== null) {
            clearInterval(pollRef.current);
            pollRef.current = null;
        }

        setTelegramState('waiting');

        try {
            const { url, token } = await memberApi<{
                url: string;
                token: string;
            }>(init().url, 'POST');

            window.open(url, '_blank', 'noopener');

            pollRef.current = setInterval(async () => {
                try {
                    const result = await fetch(
                        telegramStatus.url({ query: { token } }),
                        { credentials: 'same-origin' },
                    );

                    if (result.status === 410) {
                        throw new Error('expired');
                    }

                    const { status: s } = (await result.json()) as {
                        status: string;
                    };

                    if (s === 'ready') {
                        const done = await memberApi<{ redirect: string }>(
                            complete().url,
                            'POST',
                            { token },
                        );

                        if (pollRef.current !== null) {
                            clearInterval(pollRef.current);
                            pollRef.current = null;
                        }

                        window.location.href = done.redirect;
                    }
                } catch {
                    if (pollRef.current !== null) {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                    }
                    setTelegramState('error');
                }
            }, 2000);
        } catch {
            setTelegramState('error');
        }
    }

    return (
        <>
            <Head title="Log in" />

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <IdentifierToggle
                                    value={identifierType}
                                    onChange={setIdentifierType}
                                />
                                <Label htmlFor="login">
                                    {IDENTIFIER_META[identifierType].label}
                                </Label>
                                <Input
                                    id="login"
                                    type={IDENTIFIER_META[identifierType].type}
                                    name="login"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete={
                                        IDENTIFIER_META[identifierType]
                                            .autoComplete
                                    }
                                    inputMode={
                                        IDENTIFIER_META[identifierType]
                                            .inputMode
                                    }
                                    placeholder={
                                        IDENTIFIER_META[identifierType]
                                            .placeholder
                                    }
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
                                            tabIndex={5}
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
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>

                            {telegramAvailable && (
                                <>
                                    <div className="relative">
                                        <div className="absolute inset-0 flex items-center">
                                            <span className="w-full border-t" />
                                        </div>
                                        <div className="relative flex justify-center text-xs uppercase">
                                            <span className="bg-background px-2 text-muted-foreground">
                                                or
                                            </span>
                                        </div>
                                    </div>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full"
                                        tabIndex={5}
                                        disabled={telegramState === 'waiting'}
                                        onClick={startTelegramLogin}
                                    >
                                        {telegramState === 'waiting' && (
                                            <Spinner />
                                        )}
                                        {telegramState === 'waiting'
                                            ? 'Check Telegram…'
                                            : 'Continue with Telegram'}
                                    </Button>

                                    {telegramState === 'error' && (
                                        <p className="text-center text-sm text-destructive">
                                            Telegram sign-in didn't complete.
                                            Try again.
                                        </p>
                                    )}
                                </>
                            )}
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Don't have an account?{' '}
                            <TextLink href={register()} tabIndex={6}>
                                Sign up
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Pick your sign-in method and enter your password below',
};
