import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { store } from '@/routes/programs/shared/lead';

type Questions = Record<string, string[]>;

const QUESTION_LABELS: Record<string, string> = {
    goal: "What's your main goal?",
    experience: 'Training experience',
    days_per_week: 'Days you can train per week',
    health: 'Any injuries or health concerns?',
};

const OPTION_LABELS: Record<string, string> = {
    lose_fat: 'Lose fat',
    build_muscle: 'Build muscle',
    get_fitter: 'Get fitter',
    sport: 'Sport performance',
    beginner: 'Beginner',
    intermediate: 'Intermediate',
    advanced: 'Advanced',
    '1-2': '1–2 days',
    '3-4': '3–4 days',
    '5+': '5+ days',
    none: 'None',
    injury: 'Recovering from injury',
    medical: 'Medical condition',
};

type Props = {
    program: { name: string; share_token: string };
    trainer: { name: string };
    questions: Questions;
    program_url: string;
};

export default function ProgramLead({
    program,
    trainer,
    questions,
    program_url,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        phone: '',
        answers: {} as Record<string, string>,
    });

    const answer = (key: string, value: string) =>
        setData('answers', { ...data.answers, [key]: value });

    const answeredAll =
        Object.keys(questions).every((key) => data.answers[key]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(store(program.share_token).url);
    };

    return (
        <>
            <Head title={`${program.name} — ${trainer.name}`} />
            <div className="min-h-dvh bg-background text-foreground">
                <div className="mx-auto flex w-full max-w-lg flex-col gap-6 px-4 py-8">
                    <header className="grid gap-1 text-center">
                        <h1 className="text-xl font-bold">{program.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            A few quick questions so {trainer.name} can tailor
                            this program for you.
                        </p>
                    </header>

                    <form onSubmit={submit} className="grid gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    About you
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="name">Your name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(event) =>
                                            setData('name', event.target.value)
                                        }
                                        placeholder="Full name"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="phone">
                                        Phone (optional)
                                    </Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(event) =>
                                            setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="09…"
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                            </CardContent>
                        </Card>

                        {Object.entries(questions).map(([key, options]) => (
                            <Card key={key}>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {QUESTION_LABELS[key] ?? key}
                                    </CardTitle>
                                    <CardDescription>
                                        Pick one
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-wrap gap-2">
                                    {options.map((option) => (
                                        <button
                                            key={option}
                                            type="button"
                                            onClick={() => answer(key, option)}
                                            className={cn(
                                                'rounded-full border px-4 py-2 text-sm transition-colors',
                                                data.answers[key] === option
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'bg-card hover:bg-muted/50',
                                            )}
                                        >
                                            {OPTION_LABELS[option] ?? option}
                                        </button>
                                    ))}
                                    <InputError
                                        message={
                                            errors[
                                                `answers.${key}` as keyof typeof errors
                                            ]
                                        }
                                    />
                                </CardContent>
                            </Card>
                        ))}

                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing || !answeredAll}
                        >
                            {processing && <Spinner />}
                            Get my program
                        </Button>

                        <Link
                            href={program_url}
                            className="text-center text-xs text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Back to the program
                        </Link>
                    </form>
                </div>
            </div>
        </>
    );
}
