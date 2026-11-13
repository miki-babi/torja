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

type Field = {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'select';
    options: { value: string; label: string }[];
    required: boolean;
};

type Props = {
    program: { name: string; share_token: string };
    trainer: { name: string };
    fields: Field[];
    optional: boolean;
    skip_url: string | null;
    program_url: string;
};

export default function ProgramLead({
    program,
    trainer,
    fields,
    optional,
    skip_url,
    program_url,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        phone: '',
        answers: {} as Record<string, string>,
    });

    const answer = (key: string, value: string) =>
        setData('answers', { ...data.answers, [key]: value });

    const answeredAll = fields.every(
        (field) => !field.required || data.answers[field.key],
    );

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

                        {fields.map((field) => (
                            <Card key={field.key}>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {field.label}
                                        {!field.required && (
                                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                Optional
                                            </span>
                                        )}
                                    </CardTitle>
                                    {field.type === 'select' && (
                                        <CardDescription>
                                            Pick one
                                        </CardDescription>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    {field.type === 'select' ? (
                                        <div className="flex flex-wrap gap-2">
                                            {field.options.map((option) => (
                                                <button
                                                    key={option.value}
                                                    type="button"
                                                    onClick={() =>
                                                        answer(
                                                            field.key,
                                                            option.value,
                                                        )
                                                    }
                                                    className={cn(
                                                        'rounded-full border px-4 py-2 text-sm transition-colors',
                                                        data.answers[
                                                            field.key
                                                        ] === option.value
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'bg-card hover:bg-muted/50',
                                                    )}
                                                >
                                                    {option.label}
                                                </button>
                                            ))}
                                        </div>
                                    ) : field.type === 'textarea' ? (
                                        <textarea
                                            value={
                                                data.answers[field.key] ?? ''
                                            }
                                            onChange={(event) =>
                                                answer(
                                                    field.key,
                                                    event.target.value,
                                                )
                                            }
                                            rows={3}
                                            className="border-input bg-transparent shadow-xs w-full rounded-md border px-3 py-2 text-sm outline-none focus-visible:border-ring"
                                        />
                                    ) : (
                                        <Input
                                            value={
                                                data.answers[field.key] ?? ''
                                            }
                                            onChange={(event) =>
                                                answer(
                                                    field.key,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                    <InputError
                                        message={
                                            errors[
                                                `answers.${field.key}` as keyof typeof errors
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

                        {optional && skip_url !== null && (
                            <Link
                                href={skip_url}
                                className="text-center text-sm font-medium text-muted-foreground underline-offset-4 hover:underline"
                            >
                                Skip — take me to the program
                            </Link>
                        )}

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
