import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { MobileShell } from '@/components/member/mobile-shell';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { login, register } from '@/routes';
import type { Auth } from '@/types';

export function CalculatorShell({
    headingTitle,
    headingDescription,
    stepCount,
    stepIndex,
    stepLabel,
    cardTitle,
    cardDescription,
    children,
}: {
    headingTitle: string;
    headingDescription: string;
    stepCount: number;
    stepIndex: number;
    stepLabel: string;
    cardTitle: string;
    cardDescription: string;
    children: ReactNode;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <MobileShell>
            <div className="flex w-full flex-col items-center">
                {!auth.user && (
                    <header className="mb-6 flex w-full items-center justify-end text-sm">
                        <nav className="flex items-center gap-4">
                            <Link
                                href={login()}
                                className="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal hover:border-border"
                            >
                                Log in
                            </Link>
                            <Link
                                href={register()}
                                className="inline-block rounded-sm border border-border px-5 py-1.5 text-sm leading-normal hover:border-foreground/40"
                            >
                                Register
                            </Link>
                        </nav>
                    </header>
                )}
                <main className="flex w-full flex-1 flex-col gap-4">
                    <Heading
                        title={headingTitle}
                        description={headingDescription}
                    />

                    <div className="flex gap-2">
                        {Array.from({ length: stepCount }, (_, i) => (
                            <span
                                key={i}
                                className={cn(
                                    'h-1.5 flex-1 rounded-full',
                                    i <= stepIndex
                                        ? 'bg-foreground'
                                        : 'bg-muted',
                                )}
                            />
                        ))}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Step {stepIndex + 1} of {stepCount} — {stepLabel}
                    </p>

                    <Card>
                        <CardHeader>
                            <CardTitle>{cardTitle}</CardTitle>
                            <CardDescription>{cardDescription}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {children}
                        </CardContent>
                    </Card>
                </main>
            </div>
        </MobileShell>
    );
}
