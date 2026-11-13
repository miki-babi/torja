import { Head, Link } from '@inertiajs/react';
import { BadgeCheck, PartyPopper } from 'lucide-react';

type Props = {
    trainer: { name: string; slug: string };
    program: { name: string };
    register_url: string;
    login_url: string;
};

export default function ProgramLeadThanks({
    trainer,
    program,
    register_url,
    login_url,
}: Props) {
    return (
        <>
            <Head title="You're in!" />
            <div className="min-h-dvh bg-background text-foreground">
                <div className="mx-auto flex w-full max-w-lg flex-col items-center gap-6 px-4 py-16 text-center">
                    <PartyPopper className="size-12 text-primary" />
                    <div className="grid gap-2">
                        <h1 className="text-2xl font-bold">
                            You're on the list!
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {trainer.name} got your answers for{' '}
                            <span className="font-medium text-foreground">
                                {program.name}
                            </span>
                            . Create a free account to get the program and
                            start training.
                        </p>
                    </div>

                    <Link
                        href={register_url}
                        className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground"
                    >
                        <BadgeCheck className="size-4" />
                        Create my free account
                    </Link>
                    <Link
                        href={login_url}
                        className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Already have an account? Log in
                    </Link>
                </div>
            </div>
        </>
    );
}
