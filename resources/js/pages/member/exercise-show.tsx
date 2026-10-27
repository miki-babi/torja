import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import { AddToPlaylist } from '@/components/member/add-to-playlist';
import {
    bodyPartIcon,
    type ExerciseDetail,
} from '@/components/member/exercise-data';
import { MobileShell } from '@/components/member/mobile-shell';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import {
    exerciseHistory,
    prByExercise,
    setsForExercise,
    setSummary,
} from '@/components/member/workout-progress';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { exercise } from '@/routes/member';
import { show as playlistShow } from '@/routes/member/playlists';

export default function MemberExerciseShow({
    exercise: ex,
    playlists: initialPlaylists,
    sessions,
}: {
    exercise: ExerciseDetail;
    playlists: ExercisePlaylist[];
    sessions: WorkoutSession[];
}) {
    const [playlists, setPlaylists] =
        useState<ExercisePlaylist[]>(initialPlaylists);

    function updatePlaylist(updated: ExercisePlaylist) {
        setPlaylists((list) =>
            list.some((p) => p.id === updated.id)
                ? list.map((p) => (p.id === updated.id ? updated : p))
                : [...list, updated],
        );
    }

    const MuscleIcon = bodyPartIcon(ex.body_part);
    const containing = playlists.filter((p) =>
        p.exercise_ids.includes(ex.slug),
    );
    const history = exerciseHistory(sessions, ex.slug, undefined, 10);
    const pr = prByExercise(sessions)[ex.slug];

    return (
        <MobileShell>
            <Head title={ex.name} />
            <Button variant="ghost" size="sm" asChild className="-ml-2">
                <Link href={exercise()}>
                    <ChevronLeft className="size-4" />
                    Exercise
                </Link>
            </Button>

            <Card className="mt-4 overflow-hidden">
                <img
                    src={ex.gif_url}
                    alt={`${ex.name} demo`}
                    className="aspect-square w-full bg-muted object-cover"
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
                    <MuscleIcon className="size-3.5" />
                    {ex.body_part}
                </span>
            </div>

            <div className="mt-3 flex flex-wrap gap-1.5">
                {[ex.level, ex.location, ex.exercise_type, ex.muscle_group].map(
                    (label) => (
                        <Badge key={label} variant="secondary">
                            {label}
                        </Badge>
                    ),
                )}
            </div>

            {ex.secondary_muscles.length > 0 && (
                <Card className="mt-4">
                    <CardContent className="p-4">
                        <p className="text-xs font-medium text-muted-foreground">
                            Secondary muscles
                        </p>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {ex.secondary_muscles.map((muscle) => (
                                <Badge key={muscle} variant="outline">
                                    {muscle}
                                </Badge>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle className="text-base">Instructions</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol className="list-decimal space-y-2 pl-5 text-sm">
                        {ex.instruction_steps.map((step, i) => (
                            <li key={i}>{step}</li>
                        ))}
                    </ol>
                </CardContent>
            </Card>

            {pr !== undefined && (
                <Card className="mt-4">
                    <CardContent className="flex items-center justify-between p-4">
                        <p className="text-xs font-medium text-muted-foreground">
                            Personal record
                        </p>
                        <p className="text-sm font-medium">
                            {pr.weight_kg}kg × {pr.reps}
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
                        <div className="divide-y">
                            {containing.map((p) => (
                                <Link
                                    key={p.id}
                                    href={playlistShow(p.id).url}
                                    className="block py-2 text-sm hover:underline"
                                >
                                    {p.name}
                                </Link>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {history.length > 0 && (
                <Card className="mt-4">
                    <CardHeader>
                        <CardTitle className="text-base">History</CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {history.map((h) => (
                            <div
                                key={h.id}
                                className="flex justify-between py-2 text-sm"
                            >
                                <span className="text-muted-foreground">
                                    {new Date(
                                        `${h.date}T12:00:00`,
                                    ).toLocaleDateString(undefined, {
                                        month: 'short',
                                        day: 'numeric',
                                    })}
                                </span>
                                <span>
                                    {setSummary(setsForExercise(h, ex.slug))}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            <p className="mt-4 text-center text-xs text-muted-foreground">
                {ex.attribution}
            </p>
        </MobileShell>
    );
}
