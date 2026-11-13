import { Head, Link } from '@inertiajs/react';
import { BadgeCheck, Dumbbell, Languages, Medal } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';

type Program = {
    name: string;
    description: string | null;
    is_free: boolean;
    price_etb: number;
    workouts_count: number;
    url: string;
};

type Plan = {
    name: string;
    description: string | null;
    kind_label: string;
    price_etb: number;
    duration_days: number;
    url: string;
};

type Props = {
    trainer: {
        name: string;
        avatar: string | null;
        bio: string | null;
        specialty: string | null;
        languages: string[];
        years_experience: number | null;
        slug: string;
    };
    programs: Program[];
    plans: Plan[];
    register_url: string;
    login_url: string;
};

export default function TrainerPage({
    trainer,
    programs,
    plans,
    register_url,
    login_url,
}: Props) {
    const freePrograms = programs.filter((program) => program.is_free);
    const paidPrograms = programs.filter((program) => ! program.is_free);

    return (
        <>
            <Head title={trainer.name} />
            <div className="min-h-dvh bg-background text-foreground">
                <div className="mx-auto flex w-full max-w-lg flex-col gap-6 px-4 py-8">
                    <header className="flex flex-col items-center gap-3 text-center">
                        <Avatar className="size-24 border">
                            <AvatarImage src={trainer.avatar ?? undefined} />
                            <AvatarFallback className="text-2xl">
                                {trainer.name.slice(0, 1)}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <h1 className="text-2xl font-bold">
                                {trainer.name}
                            </h1>
                            {trainer.specialty && (
                                <p className="text-sm text-muted-foreground">
                                    {trainer.specialty}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-wrap items-center justify-center gap-2">
                            {trainer.years_experience !== null && (
                                <Badge variant="secondary">
                                    <Medal className="size-3" />
                                    {trainer.years_experience}+ yrs
                                </Badge>
                            )}
                            {trainer.languages.length > 0 && (
                                <Badge variant="secondary">
                                    <Languages className="size-3" />
                                    {trainer.languages.join(' · ')}
                                </Badge>
                            )}
                        </div>
                        {trainer.bio && (
                            <p className="max-w-prose text-sm text-muted-foreground">
                                {trainer.bio}
                            </p>
                        )}
                    </header>

                    {freePrograms.length > 0 && (
                        <section className="grid gap-3">
                            <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                Free programs
                            </h2>
                            {freePrograms.map((program) => (
                                <a
                                    key={program.url}
                                    href={program.url}
                                    className="flex items-center justify-between gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-muted/50"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {program.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {program.workouts_count} workouts
                                        </p>
                                    </div>
                                    <Badge>Free</Badge>
                                </a>
                            ))}
                        </section>
                    )}

                    {paidPrograms.length > 0 && (
                        <section className="grid gap-3">
                            <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                Programs
                            </h2>
                            {paidPrograms.map((program) => (
                                <a
                                    key={program.url}
                                    href={program.url}
                                    className="flex items-center justify-between gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-muted/50"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {program.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {program.workouts_count} workouts
                                        </p>
                                    </div>
                                    <Badge variant="secondary">
                                        {program.price_etb} ETB
                                    </Badge>
                                </a>
                            ))}
                        </section>
                    )}

                    {plans.length > 0 && (
                        <section className="grid gap-3">
                            <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                Coaching plans
                            </h2>
                            {plans.map((plan) => (
                                <a
                                    key={plan.url}
                                    href={plan.url}
                                    className="flex items-center justify-between gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-muted/50"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {plan.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {plan.duration_days} days ·{' '}
                                            {plan.kind_label}
                                        </p>
                                        {plan.description && (
                                            <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                                {plan.description}
                                            </p>
                                        )}
                                    </div>
                                    <Badge variant="secondary">
                                        {plan.price_etb} ETB
                                    </Badge>
                                </a>
                            ))}
                        </section>
                    )}

                    {programs.length === 0 && plans.length === 0 && (
                        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                            <Dumbbell className="size-6" />
                            {trainer.name} hasn't published any programs yet.
                        </div>
                    )}

                    <footer className="grid gap-2 pt-2">
                        <Link
                            href={register_url}
                            className="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground"
                        >
                            <BadgeCheck className="size-4" />
                            Start training with {trainer.name.split(' ')[0]}
                        </Link>
                        <Link
                            href={login_url}
                            className="text-center text-xs text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Already have an account? Log in
                        </Link>
                    </footer>
                </div>
            </div>
        </>
    );
}
