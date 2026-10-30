import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronRight,
    Dumbbell,
    Gauge,
    ListChecks,
    Lock,
    Play,
    Share2,
    Weight,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { ExerciseSummary } from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import type { WorkoutSession } from '@/components/member/types';
import type { PlayerWorkout } from '@/components/member/workout-player';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import { set as setLocale } from '@/routes/locale';
import { exercise } from '@/routes/member';
import { show as exerciseShow } from '@/routes/member/exercises';
import { play } from '@/routes/member/program-workouts';

type ProgramWorkout = PlayerWorkout;

type SharedProgram = {
    name: string;
    owner: string;
    description: string | null;
    image_url: string | null;
    highlights: string[];
    workouts: ProgramWorkout[];
};

function ExerciseRow({ ex, detail }: { ex: ExerciseSummary; detail: string }) {
    return (
        <>
            <img
                src={ex.image_url}
                alt=""
                loading="lazy"
                className="size-10 shrink-0 rounded-lg object-cover"
            />
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium capitalize">{ex.name}</p>
                <p className="text-xs text-muted-foreground">{detail}</p>
            </div>
        </>
    );
}

export default function MemberSharedProgram({
    program,
    isOwner,
    isPro,
    alreadyClaimed,
    saveUrl,
    proUrl,
    homeUrl,
    editUrl,
    todaysSessions: initialTodaysSessions,
}: {
    program: SharedProgram;
    isOwner: boolean;
    isPro: boolean;
    alreadyClaimed: boolean;
    saveUrl: string;
    proUrl: string;
    homeUrl: string;
    editUrl: string | null;
    todaysSessions: Record<string, WorkoutSession>;
}) {
    const { auth } = usePage().props;
    const { t, locale, languages } = useTranslation();
    const [saving, setSaving] = useState(false);
    const [switching, setSwitching] = useState(false);
    const [claimed, setClaimed] = useState(alreadyClaimed);
    const [dayPickerOpen, setDayPickerOpen] = useState(false);
    const isGuest = auth.user === null;
    const todaysSessions = initialTodaysSessions;

    /**
     * Translated exercise data value (equipment/body part/level/target),
     * falling back to the raw catalog value when no line exists.
     */
    function tv(
        kind: 'equipment' | 'body_part' | 'level' | 'target',
        value: string,
    ): string {
        const key = `exercise_values.${kind}.${value}`;
        const text = t(key);
        return text === key ? value : text;
    }

    /**
     * "Barbell · Pectorals" — recomposed from translated parts in Amharic,
     * server-provided English detail otherwise.
     */
    function exerciseDetailText(ex: ExerciseSummary): string {
        return locale === 'am'
            ? `${tv('equipment', ex.equipment)} · ${tv('target', ex.target)}`
            : ex.detail;
    }

    function switchLocale(code: string) {
        if (switching || code === locale) {
            return;
        }
        setSwitching(true);
        router.post(
            setLocale().url,
            { locale: code },
            {
                preserveScroll: true,
                onFinish: () => setSwitching(false),
            },
        );
    }

    async function save() {
        if (saving) {
            return;
        }
        setSaving(true);
        try {
            await memberApi<{ claimed: boolean }>(saveUrl, 'POST');
            setClaimed(true);
            toast.success(t('shared_program.saved_toast'));
        } catch {
            toast.error(t('shared_program.save_failed_toast'));
        } finally {
            setSaving(false);
        }
    }

    const allExercises = program.workouts.flatMap(
        (workout) => workout.exercises,
    );
    const exerciseCount = allExercises.length;
    const equipment = [...new Set(allExercises.map((ex) => ex.equipment))];
    const levels = [...new Set(allExercises.map((ex) => ex.level))];
    const bodyParts = [...new Set(allExercises.map((ex) => ex.body_part))];
    const heroImages = [
        ...new Set(allExercises.map((ex) => ex.image_url)),
    ].slice(0, 4);

    return (
        <MobileShell hideNav>
            <Head title={program.name} />

            <div className="mb-4 flex items-center justify-end">
                {languages.length > 1 && (
                    <div className="flex items-center gap-1 rounded-full border p-1">
                        {languages.map((language) => (
                            <button
                                key={language.code}
                                type="button"
                                disabled={switching}
                                onClick={() => switchLocale(language.code)}
                                className={cn(
                                    'rounded-full px-3 py-1 text-xs transition-colors',
                                    locale === language.code
                                        ? 'bg-lime-400 font-medium text-lime-950'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {language.native_name}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            <div className="relative aspect-[4/3] overflow-hidden rounded-2xl">
                {program.image_url !== null ? (
                    <img
                        src={program.image_url}
                        alt=""
                        className="absolute inset-0 size-full object-cover"
                    />
                ) : heroImages.length === 1 ? (
                    <img
                        src={heroImages[0]}
                        alt=""
                        className="absolute inset-0 size-full object-cover"
                    />
                ) : heroImages.length > 0 ? (
                    <div className="absolute inset-0 grid grid-cols-2">
                        {heroImages.map((src) => (
                            <img
                                key={src}
                                src={src}
                                alt=""
                                className="size-full object-cover"
                            />
                        ))}
                    </div>
                ) : (
                    <div className="absolute inset-0 bg-lime-200/60 dark:bg-lime-900/40" />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-black/10" />
                <div className="absolute inset-x-3 top-3 flex items-start justify-between">
                    <span className="flex items-center gap-1.5 rounded-full bg-black/40 px-3 py-1 text-xs text-white backdrop-blur">
                        <Share2 className="size-3.5" />
                        {t('shared_program.shared')}
                    </span>
                    <Badge className="bg-lime-400 text-lime-950">
                        {t('shared_program.pro_badge')}
                    </Badge>
                </div>
                <div className="absolute inset-x-0 bottom-0 p-4">
                    <h1 className="text-2xl font-bold tracking-tight text-white">
                        {program.name}
                    </h1>
                    <p className="mt-1 text-sm text-white/80">
                        {t('shared_program.shared_by', {
                            owner: program.owner,
                        })}{' '}
                        ·{' '}
                        {t(
                            program.workouts.length === 1
                                ? 'shared_program.workout_count'
                                : 'shared_program.workouts_count',
                            { count: program.workouts.length },
                        )}{' '}
                        ·{' '}
                        {t(
                            exerciseCount === 1
                                ? 'shared_program.exercise_count'
                                : 'shared_program.exercises_count',
                            { count: exerciseCount },
                        )}
                    </p>
                </div>
            </div>

            <Card className="mt-5">
                <CardHeader>
                    <CardTitle className="text-base">
                        {t('shared_program.whats_included')}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-lg border p-3">
                            <Dumbbell className="size-4 text-lime-600 dark:text-lime-400" />
                            <p className="mt-1.5 text-lg leading-none font-semibold">
                                {program.workouts.length}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t('shared_program.guided_workouts')}
                            </p>
                        </div>
                        <div className="rounded-lg border p-3">
                            <ListChecks className="size-4 text-lime-600 dark:text-lime-400" />
                            <p className="mt-1.5 text-lg leading-none font-semibold">
                                {exerciseCount}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t('shared_program.exercises_with_demos')}
                            </p>
                        </div>
                        <div className="rounded-lg border p-3">
                            <Weight className="size-4 text-lime-600 dark:text-lime-400" />
                            <p className="mt-1.5 text-sm leading-snug font-semibold capitalize">
                                {equipment
                                    .map((value) => tv('equipment', value))
                                    .join(' · ') || '—'}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t('shared_program.equipment_needed')}
                            </p>
                        </div>
                        <div className="rounded-lg border p-3">
                            <Gauge className="size-4 text-lime-600 dark:text-lime-400" />
                            <p className="mt-1.5 text-sm leading-snug font-semibold capitalize">
                                {levels
                                    .map((value) => tv('level', value))
                                    .join(' · ') || '—'}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t('shared_program.level')}
                            </p>
                        </div>
                    </div>

                    {bodyParts.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            {bodyParts.map((part) => (
                                <Badge
                                    key={part}
                                    variant="secondary"
                                    className="capitalize"
                                >
                                    {tv('body_part', part)}
                                </Badge>
                            ))}
                        </div>
                    )}

                    {program.highlights.length > 0 && (
                        <ul className="space-y-2">
                            {program.highlights.map((line) => (
                                <li
                                    key={line}
                                    className="flex items-start gap-2 text-sm"
                                >
                                    <Check className="mt-0.5 size-4 shrink-0 text-lime-600 dark:text-lime-400" />
                                    {line}
                                </li>
                            ))}
                        </ul>
                    )}

                    {program.description !== null && (
                        <p className="text-sm text-muted-foreground">
                            {program.description}
                        </p>
                    )}
                </CardContent>
            </Card>

            <Dialog open={dayPickerOpen} onOpenChange={setDayPickerOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>
                            {t('shared_program.choose_day')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('shared_program.start_workout_hint')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="divide-y rounded-xl border">
                        {program.workouts.map((workout, index) => {
                            const existing =
                                todaysSessions[String(workout.id)] ?? null;
                            return (
                                <div
                                    key={workout.id}
                                    className="flex items-center gap-3 px-4 py-3"
                                >
                                    <span className="w-5 text-center text-xs text-muted-foreground tabular-nums">
                                        {index + 1}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {workout.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                workout.exercises.length === 1
                                                    ? 'shared_program.exercise_count'
                                                    : 'shared_program.exercises_count',
                                                {
                                                    count: workout.exercises
                                                        .length,
                                                },
                                            )}
                                        </p>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant={
                                            existing === null
                                                ? 'default'
                                                : 'outline'
                                        }
                                        className={
                                            existing === null
                                                ? 'bg-lime-400 text-lime-950 hover:bg-lime-500'
                                                : undefined
                                        }
                                        asChild
                                    >
                                        <Link href={play(workout.id).url}>
                                            <Play className="size-3.5" />
                                            {existing === null
                                                ? t('shared_program.start')
                                                : t(
                                                      'shared_program.continue_day',
                                                      {
                                                          count: existing.sets
                                                              .length,
                                                      },
                                                  )}
                                        </Link>
                                    </Button>
                                </div>
                            );
                        })}
                    </div>
                </DialogContent>
            </Dialog>

            <h2 className="mt-6 text-base font-semibold">
                {t('shared_program.inside_program')}
            </h2>
            <div className="mt-3 space-y-3">
                {program.workouts.map((workout, index) => (
                    <Collapsible
                        key={index}
                        defaultOpen={index === 0}
                        className="rounded-xl border"
                    >
                        <CollapsibleTrigger className="group flex w-full items-center justify-between gap-2 px-4 py-3 text-left">
                            <div>
                                <p className="text-sm font-medium">
                                    {workout.title}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        workout.exercises.length === 1
                                            ? 'shared_program.exercise_count'
                                            : 'shared_program.exercises_count',
                                        { count: workout.exercises.length },
                                    )}
                                </p>
                            </div>
                            <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="divide-y border-t">
                            {workout.exercises.map((ex) =>
                                isGuest ? (
                                    <div
                                        key={ex.slug}
                                        className="flex items-center gap-3 px-4 py-3"
                                    >
                                        <ExerciseRow
                                            ex={ex}
                                            detail={exerciseDetailText(ex)}
                                        />
                                        <Lock className="size-4 shrink-0 text-muted-foreground" />
                                    </div>
                                ) : (
                                    <Link
                                        key={ex.slug}
                                        href={exerciseShow(ex.slug).url}
                                        className="flex items-center gap-3 px-4 py-3 hover:bg-accent/50"
                                    >
                                        <ExerciseRow
                                            ex={ex}
                                            detail={exerciseDetailText(ex)}
                                        />
                                        <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                    </Link>
                                ),
                            )}
                        </CollapsibleContent>
                    </Collapsible>
                ))}
            </div>
            {isGuest && (
                <p className="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Lock className="size-3.5" />
                    {t('shared_program.login_preview_hint')}
                </p>
            )}

            <div className="h-40" />

            <div className="fixed inset-x-0 bottom-0 z-50 border-t bg-background/95 backdrop-blur">
                <div className="mx-auto flex max-w-md items-center gap-3 px-4 py-3">
                    {claimed && isPro && program.workouts.length > 0 ? (
                        <>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium">
                                    {t('shared_program.start_workout')}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('shared_program.start_workout_hint')}
                                </p>
                            </div>
                            <Button
                                className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                                onClick={() => setDayPickerOpen(true)}
                            >
                                <Play className="size-4" />
                                {t('shared_program.start_workout')}
                            </Button>
                        </>
                    ) : claimed ? (
                        <>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium">
                                    {t('shared_program.in_playlists')}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('shared_program.already_own')}
                                </p>
                            </div>
                            <Button variant="outline" asChild>
                                <Link href={exercise()}>
                                    {t('shared_program.open_my_workouts')}
                                </Link>
                            </Button>
                        </>
                    ) : isOwner && editUrl !== null ? (
                        <>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium">
                                    {t('shared_program.your_shared_program')}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('shared_program.viewing_as_visitor')}
                                </p>
                            </div>
                            <Button variant="outline" asChild>
                                <Link href={editUrl}>
                                    {t('shared_program.edit_program')}
                                </Link>
                            </Button>
                        </>
                    ) : (
                        <div className="min-w-0 flex-1 space-y-2">
                            <p className="text-center text-xs text-muted-foreground">
                                {t('shared_program.pro_badge')} ·{' '}
                                {t('shared_program.pro_included')}
                            </p>
                            {isGuest ? (
                                <Button
                                    className="w-full rounded-full"
                                    asChild
                                >
                                    <Link href={login()}>
                                        {t('shared_program.login_to_save')}
                                    </Link>
                                </Button>
                            ) : isPro ? (
                                <Button
                                    className="w-full rounded-full"
                                    disabled={saving}
                                    onClick={save}
                                >
                                    {saving
                                        ? t('shared_program.saving')
                                        : t('shared_program.claim_program')}
                                </Button>
                            ) : (
                                <Button
                                    className="w-full rounded-full"
                                    asChild
                                >
                                    <Link href={proUrl}>
                                        {t('shared_program.upgrade_to_pro')}
                                    </Link>
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                className="w-full rounded-full"
                                asChild
                            >
                                <Link href={homeUrl}>
                                    {t('shared_program.back_to_app')}
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </MobileShell>
    );
}
