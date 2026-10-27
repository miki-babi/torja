import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bookmark, Check, Lock, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import { index as programsIndex } from '@/routes/programs';

type CatalogProgram = {
    key: string;
    kind: 'exercise' | 'meal';
    name: string;
    description: string | null;
    image_url: string | null;
    meta_count: number;
    view_url: string;
    save_url: string;
    claimed: boolean;
    member_url: string | null;
};

const COUNT_KEYS = {
    exercise: {
        one: 'programs.workout_count',
        many: 'programs.workouts_count',
    },
    meal: { one: 'programs.day_count', many: 'programs.days_count' },
} as const;

function ProgramRow({
    program,
    isPro,
    isGuest,
    proUrl,
    onSaved,
}: {
    program: CatalogProgram;
    isPro: boolean;
    isGuest: boolean;
    proUrl: string;
    onSaved: (key: string) => void;
}) {
    const { t } = useTranslation();
    const [saving, setSaving] = useState(false);
    const [claimed, setClaimed] = useState(program.claimed);
    const countKeys = COUNT_KEYS[program.kind];

    async function save() {
        if (saving) {
            return;
        }
        setSaving(true);
        try {
            await memberApi<{ claimed: boolean }>(program.save_url, 'POST');
            setClaimed(true);
            onSaved(program.key);
            toast.success(t('programs.saved_toast'));
        } catch {
            toast.error(t('programs.save_failed_toast'));
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="flex items-center gap-3 py-2.5">
            {program.image_url !== null ? (
                <img
                    src={program.image_url}
                    alt=""
                    loading="lazy"
                    className="size-10 shrink-0 rounded-lg object-cover"
                />
            ) : (
                <div className="size-10 shrink-0 rounded-lg bg-lime-200/60 dark:bg-lime-900/40" />
            )}
            <div className="min-w-0 flex-1">
                <Link
                    href={program.view_url}
                    prefetch
                    className="block truncate text-sm font-medium hover:underline"
                >
                    {program.name}
                </Link>
                <p className="text-xs text-muted-foreground">
                    {t(
                        program.meta_count === 1
                            ? countKeys.one
                            : countKeys.many,
                        { count: program.meta_count },
                    )}
                </p>
            </div>
            {claimed ? (
                program.member_url !== null ? (
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="shrink-0"
                    >
                        <Link href={program.member_url}>
                            {t(
                                program.kind === 'meal'
                                    ? 'programs.open_plan'
                                    : 'programs.view',
                            )}
                        </Link>
                    </Button>
                ) : (
                    <Badge className="shrink-0 bg-lime-400 text-lime-950">
                        <Check className="size-3" />
                        {t('programs.saved')}
                    </Badge>
                )
            ) : isGuest ? (
                <Button
                    asChild
                    size="sm"
                    variant="outline"
                    className="shrink-0"
                >
                    <Link href={login()}>
                        <Bookmark className="size-4" />
                        {t('programs.save')}
                    </Link>
                </Button>
            ) : !isPro ? (
                <Button
                    size="sm"
                    variant="outline"
                    className="shrink-0"
                    onClick={() => router.visit(proUrl)}
                >
                    <Lock className="size-4" />
                    {t('programs.save')}
                </Button>
            ) : (
                <Button
                    size="sm"
                    className="shrink-0 bg-lime-400 text-lime-950 hover:bg-lime-500"
                    disabled={saving}
                    onClick={save}
                >
                    <Bookmark className="size-4" />
                    {t('programs.save')}
                </Button>
            )}
        </div>
    );
}

function Section({
    title,
    programs,
    isPro,
    isGuest,
    proUrl,
    onSaved,
}: {
    title: string;
    programs: CatalogProgram[];
    isPro: boolean;
    isGuest: boolean;
    proUrl: string;
    onSaved: (key: string) => void;
}) {
    if (programs.length === 0) {
        return null;
    }

    return (
        <section className="mt-6">
            <h2 className="text-sm font-medium text-muted-foreground">
                {title}
            </h2>
            <div className="mt-1 divide-y">
                {programs.map((program) => (
                    <ProgramRow
                        key={program.key}
                        program={program}
                        isPro={isPro}
                        isGuest={isGuest}
                        proUrl={proUrl}
                        onSaved={onSaved}
                    />
                ))}
            </div>
        </section>
    );
}

export default function MemberPrograms({
    exercisePrograms,
    mealPrograms,
    isPro,
    proUrl,
    filters,
}: {
    exercisePrograms: CatalogProgram[];
    mealPrograms: CatalogProgram[];
    isPro: boolean;
    proUrl: string;
    filters: { q: string };
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const isGuest = auth.user === null;

    const [q, setQ] = useState(filters.q);
    const [tab, setTab] = useState<'exercise' | 'nutrition'>('exercise');
    const [savedKeys, setSavedKeys] = useState<ReadonlySet<string>>(
        () =>
            new Set(
                [...exercisePrograms, ...mealPrograms]
                    .filter((program) => program.claimed)
                    .map((program) => program.key),
            ),
    );
    const isFirstSearch = useRef(true);

    function onSaved(key: string) {
        setSavedKeys((keys) => new Set(keys).add(key));
    }

    useEffect(() => {
        if (isFirstSearch.current) {
            isFirstSearch.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                programsIndex.url({ query: { q: q === '' ? undefined : q } }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['exercisePrograms', 'mealPrograms', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [q]);

    const savedPrograms = [...exercisePrograms, ...mealPrograms].filter(
        (program) => savedKeys.has(program.key),
    );
    const tabPrograms = tab === 'exercise' ? exercisePrograms : mealPrograms;

    return (
        <MobileShell>
            <Head title={t('programs.title')} />

            <h1 className="text-xl font-semibold tracking-tight">
                {t('programs.title')}
            </h1>

            <div className="relative mt-4">
                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={q}
                    onChange={(event) => setQ(event.target.value)}
                    placeholder={t('programs.search_placeholder')}
                    className="pl-9"
                />
            </div>

            {!isGuest && (
                <Section
                    title={t('programs.saved_section')}
                    programs={savedPrograms}
                    isPro={isPro}
                    isGuest={isGuest}
                    proUrl={proUrl}
                    onSaved={onSaved}
                />
            )}

            <div className="mt-5 grid grid-cols-2 gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                {(
                    [
                        {
                            value: 'exercise',
                            label: t('programs.tab_exercise'),
                        },
                        {
                            value: 'nutrition',
                            label: t('programs.tab_nutrition'),
                        },
                    ] as const
                ).map(({ value, label }) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => setTab(value)}
                        className={cn(
                            'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            tab === value
                                ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                        )}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tabPrograms.length === 0 ? (
                <p className="mt-10 text-center text-sm text-muted-foreground">
                    {t('programs.empty')}
                </p>
            ) : (
                <div className="mt-2 divide-y">
                    {tabPrograms.map((program) => (
                        <ProgramRow
                            key={program.key}
                            program={program}
                            isPro={isPro}
                            isGuest={isGuest}
                            proUrl={proUrl}
                            onSaved={onSaved}
                        />
                    ))}
                </div>
            )}
        </MobileShell>
    );
}
