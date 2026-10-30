import { Head, Link, router } from '@inertiajs/react';
import { NotebookPen } from 'lucide-react';
import { useState } from 'react';
import { AddToPlaylist } from '@/components/member/add-to-playlist';
import {
    bodyPartIcon,
    bodyPartImage,
    type ExerciseDetail,
    type ExerciseSummary,
} from '@/components/member/exercise-data';
import { MobileShell } from '@/components/member/mobile-shell';
import { QuickLogSet } from '@/components/member/quick-log-set';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import { prByExercise } from '@/components/member/workout-progress';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';
import { exercise } from '@/routes/member';
import { show as exerciseShow } from '@/routes/member/exercises';
import { show as playlistShow } from '@/routes/member/playlists';

export default function MemberExerciseShow({
    exercise: ex,
    playlists: initialPlaylists,
    sessions: initialSessions,
    similarMuscle,
    similarEquipment,
}: {
    exercise: ExerciseDetail;
    playlists: ExercisePlaylist[];
    sessions: WorkoutSession[];
    similarMuscle: ExerciseSummary[];
    similarEquipment: ExerciseSummary[];
}) {
    const { t } = useTranslation();
    const [playlists, setPlaylists] =
        useState<ExercisePlaylist[]>(initialPlaylists);
    const [sessions, setSessions] = useState<WorkoutSession[]>(initialSessions);
    const [logOpen, setLogOpen] = useState(false);

    function mergeSession(next: WorkoutSession) {
        setSessions((list) =>
            list.some((s) => s.id === next.id)
                ? list.map((s) => (s.id === next.id ? next : s))
                : [next, ...list],
        );
    }

    function updatePlaylist(updated: ExercisePlaylist) {
        setPlaylists((list) =>
            list.some((p) => p.id === updated.id)
                ? list.map((p) => (p.id === updated.id ? updated : p))
                : [...list, updated],
        );
    }

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

    const MuscleIcon = bodyPartIcon(ex.body_part);
    const muscleImage = bodyPartImage(ex.body_part);
    const containing = playlists.filter((p) =>
        p.exercise_ids.includes(ex.slug),
    );
    const pr = prByExercise(sessions)[ex.slug];

    function similarCard(title: string, items: ExerciseSummary[]) {
        if (items.length === 0) {
            return null;
        }
        return (
            <Card className="mt-4">
                <CardHeader>
                    <CardTitle className="text-base text-[#F5F5F5]">
                        {title}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {items.map((item) => (
                        <Link
                            key={item.slug}
                            href={exerciseShow(item.slug).url}
                            prefetch
                            className="flex items-center gap-3.5 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] p-3.5 transition-all hover:border-[#AFFA01]/40"
                        >
                            <span className="shrink-0 overflow-hidden rounded-xl border border-[#2E2E2E]">
                                <img
                                    src={item.image_url}
                                    alt=""
                                    loading="lazy"
                                    className="size-14 object-cover"
                                />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="line-clamp-1 block text-sm font-bold text-[#F5F5F5]">
                                    {item.name}
                                </span>
                                <span className="mt-0.5 line-clamp-1 block text-xs text-[#8E8E93]">
                                    {item.detail}
                                </span>
                            </span>
                        </Link>
                    ))}
                </CardContent>
            </Card>
        );
    }

    return (
        <MobileShell>
            <Head title={ex.name} />
            <Card className="overflow-hidden rounded-[24px] border-[#E4E4E7] bg-white">
                <img
                    src={ex.gif_url}
                    alt={`${ex.name} demo`}
                    className="aspect-square w-full bg-white object-contain p-4"
                />
            </Card>

            <div className="mt-4 flex items-start gap-3">
                <div className="min-w-0 flex-1">
                    <h1 className="text-xl font-semibold tracking-tight capitalize">
                        {ex.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">{ex.detail}</p>
                </div>
                <span className="flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs text-muted-foreground">
                    {muscleImage !== null ? (
                        <img
                            src={muscleImage}
                            alt=""
                            className="size-5 object-contain"
                        />
                    ) : (
                        <MuscleIcon className="size-3.5" />
                    )}
                    {ex.body_part}
                </span>
            </div>

            <div className="mt-3 flex flex-wrap gap-1.5">
                {[
                    tv('level', ex.level),
                    ex.location,
                    ex.exercise_type,
                    ex.muscle_group,
                ].map((label) => (
                    <span
                        key={label}
                        className="inline-flex items-center rounded-full border border-[#AFFA01]/30 bg-[#AFFA01]/10 px-3 py-1 text-xs font-semibold text-[#AFFA01]"
                    >
                        {label}
                    </span>
                ))}
            </div>

            <button
                type="button"
                onClick={() => setLogOpen(true)}
                className="mt-4 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black shadow-[0_4px_16px_rgba(175,250,1,0.3)] transition-all hover:brightness-105 active:scale-[0.98]"
            >
                <NotebookPen className="size-5" />
                <span className="text-sm font-bold tracking-wide uppercase">
                    Log a set
                </span>
            </button>

            {ex.secondary_muscles.length > 0 && (
                <Card className="mt-4">
                    <CardContent className="p-4">
                        <p className="text-xs font-medium text-[#A1A1AA]">
                            Secondary muscles
                        </p>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {ex.secondary_muscles.map((muscle) => (
                                <span
                                    key={muscle}
                                    className="inline-flex items-center rounded-full border border-[#2E2E2E] bg-[#262626] px-2.5 py-0.5 text-xs font-medium text-[#F5F5F5]"
                                >
                                    {muscle}
                                </span>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle className="text-base text-[#F5F5F5]">
                        Instructions
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ol className="space-y-3 pl-1 text-sm">
                        {ex.instruction_steps.map((step, i) => (
                            <li
                                key={i}
                                className="flex items-start gap-3 text-[#E5E5E5]"
                            >
                                <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-[#AFFA01]/15 text-xs font-bold text-[#AFFA01]">
                                    {i + 1}
                                </span>
                                <span className="leading-relaxed">{step}</span>
                            </li>
                        ))}
                    </ol>
                </CardContent>
            </Card>

            {pr !== undefined && (
                <Card className="mt-4 border-[#AFFA01]/30 bg-gradient-to-br from-[#1F1F1F] to-[#1F1F1F]">
                    <CardContent className="flex items-center justify-between p-4">
                        <div>
                            <p className="text-xs font-semibold tracking-wider text-[#AFFA01] uppercase">
                                Personal record 🏆
                            </p>
                            <p className="mt-0.5 text-xs text-[#A1A1AA]">
                                Best recorded set
                            </p>
                        </div>
                        <p className="text-base font-black text-[#F5F5F5]">
                            {pr.weight_kg > 0 ? (
                                <>
                                    <span className="text-[#AFFA01]">
                                        {pr.weight_kg}
                                    </span>{' '}
                                    kg ×{' '}
                                    <span className="text-[#AFFA01]">
                                        {pr.reps}
                                    </span>{' '}
                                    reps
                                </>
                            ) : (
                                <>
                                    <span className="text-[#AFFA01]">
                                        {pr.reps}
                                    </span>{' '}
                                    reps
                                </>
                            )}
                        </p>
                    </CardContent>
                </Card>
            )}

            <Card className="mt-4">
                <CardHeader className="flex-row items-center justify-between">
                    <CardTitle className="text-base">Playlists</CardTitle>
                    <AddToPlaylist
                        playlists={playlists}
                        exerciseId={ex.slug}
                        exerciseName={ex.name}
                        onChange={updatePlaylist}
                        onCreateNew={() => router.visit(exercise().url)}
                    />
                </CardHeader>
                <CardContent>
                    {containing.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Not in any playlist yet.
                        </p>
                    ) : (
                        <div className="divide-y divide-[#2E2E2E]">
                            {containing.map((p) => (
                                <Link
                                    key={p.id}
                                    href={playlistShow(p.id).url}
                                    className="block py-2.5 text-sm font-medium text-[#F5F5F5] transition-colors hover:text-[#AFFA01]"
                                >
                                    {p.name}
                                </Link>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {similarCard(t('member.exercise.similar_muscle'), similarMuscle)}
            {similarCard(
                t('member.exercise.similar_equipment'),
                similarEquipment,
            )}

            <QuickLogSet
                slug={ex.slug}
                name={ex.name}
                equipment={ex.equipment}
                sessions={sessions}
                open={logOpen}
                onOpenChange={setLogOpen}
                onLogged={mergeSession}
            />
        </MobileShell>
    );
}
