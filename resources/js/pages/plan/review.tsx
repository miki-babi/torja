import Heading from '@/components/heading';
import { MobileShell } from '@/components/member/mobile-shell';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    SECTION_META,
    describeSection,
    type SectionKey,
} from '@/lib/plan-options';
import { dashboard, home, login, register } from '@/routes';
import { preview, wizard } from '@/routes/plan';
import type { Auth } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';

const INCLUDES = [
    'A calorie and protein target calculated from your body and activity',
    'A sample meal day matched to your goal',
    'Automatic swaps for foods you avoid or dislike',
];

const SECTION_ORDER: SectionKey[] = [
    'goal',
    'body',
    'activity',
    'pace',
    'diet',
    'liked_foods',
    'avoidances',
    'habits',
    'schedule',
    'budget',
    'cooking',
    'obstacles',
];

type Props = {
    status: string;
    answers: Partial<Record<SectionKey, Record<string, unknown>>>;
};

export default function PlanReview({ status, answers }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;

    const answered = SECTION_ORDER.filter(
        (key) => Object.keys(answers[key] ?? {}).length > 0,
    );

    return (
        <>
            <Head title="Your nutrition profile" />
            <MobileShell>
                <div className="flex w-full flex-col items-center">
                    <header className="mb-6 flex w-full max-w-5xl items-center justify-between text-sm">
                        <Link href={home()} className="font-medium">
                            Personalize my plan
                        </Link>
                        <nav className="flex items-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-block rounded-sm border border-border px-5 py-1.5 text-sm leading-normal hover:border-foreground/40"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
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
                                </>
                            )}
                        </nav>
                    </header>

                    <main className="flex w-full flex-1 flex-col gap-4">
                        <Heading
                            title="Your nutrition profile"
                            description="Review your answers, then see your plan preview."
                        />

                        {status !== 'completed' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        You're not finished yet
                                    </CardTitle>
                                    <CardDescription>
                                        {answered.length} of 12 sections
                                        answered. You can keep going — your
                                        answers are saved.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Button variant="outline" asChild>
                                        <Link href={wizard()}>
                                            Resume the wizard
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    What your plan includes
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-2 text-sm">
                                    {INCLUDES.map((item) => (
                                        <li
                                            key={item}
                                            className="flex items-start gap-2"
                                        >
                                            <Check className="mt-0.5 size-4 shrink-0" />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>

                        {answered.map((key) => (
                            <Card key={key}>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {SECTION_META[key].title}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <dl className="space-y-1.5 text-sm">
                                        {describeSection(key, answers[key]).map(
                                            (row) => (
                                                <div
                                                    key={row.label}
                                                    className="flex justify-between gap-4"
                                                >
                                                    <dt className="text-muted-foreground">
                                                        {row.label}
                                                    </dt>
                                                    <dd className="text-right">
                                                        {row.value}
                                                    </dd>
                                                </div>
                                            ),
                                        )}
                                    </dl>
                                </CardContent>
                            </Card>
                        ))}

                        <div className="flex items-center justify-between gap-4">
                            <Button variant="outline" asChild>
                                <Link href={wizard()}>Edit answers</Link>
                            </Button>
                            <Button
                                asChild
                                className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                            >
                                <Link href={preview()}>
                                    See my plan preview
                                </Link>
                            </Button>
                        </div>

                        {!auth.user && (
                            <p className="text-center text-xs text-muted-foreground">
                                You'll be asked to create a free account to see
                                your preview.
                            </p>
                        )}
                    </main>
                </div>
            </MobileShell>
        </>
    );
}
