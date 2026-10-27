import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    IDENTIFIER_META,
    IdentifierToggle,
    type IdentifierType,
} from '@/components/identifier-toggle';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    const [identifierType, setIdentifierType] =
        useState<IdentifierType>('email');

    return (
        <>
            <Head title="Register" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Full name"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <IdentifierToggle
                                    value={identifierType}
                                    onChange={setIdentifierType}
                                />
                                <Label htmlFor={identifierType}>
                                    {IDENTIFIER_META[identifierType].label}
                                </Label>
                                <Input
                                    key={identifierType}
                                    id={identifierType}
                                    type={IDENTIFIER_META[identifierType].type}
                                    tabIndex={2}
                                    autoComplete={
                                        IDENTIFIER_META[identifierType]
                                            .autoComplete
                                    }
                                    inputMode={
                                        IDENTIFIER_META[identifierType]
                                            .inputMode
                                    }
                                    name={identifierType}
                                    required
                                    placeholder={
                                        IDENTIFIER_META[identifierType]
                                            .placeholder
                                    }
                                />
                                <InputError message={errors[identifierType]} />
                            </div>

                            {identifierType !== 'email' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="email">
                                        Email address{' '}
                                        <span className="text-muted-foreground">
                                            (optional — for verification and
                                            recovery)
                                        </span>
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        tabIndex={3}
                                        autoComplete="email"
                                        name="email"
                                        placeholder="email@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={6}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
                                tabIndex={7}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={8}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description: 'Enter your details below to create your account',
};
