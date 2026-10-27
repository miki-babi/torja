import { Play } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { store } from '@/routes/member/sessions';

export function SessionStart({
    playlists,
    todaysSessions,
    onStart,
}: {
    playlists: ExercisePlaylist[];
    todaysSessions: WorkoutSession[];
    onStart: (session: WorkoutSession) => void;
}) {
    const [busy, setBusy] = useState(false);

    async function start(playlistId: number | null) {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            const { session } = await memberApi<{ session: WorkoutSession }>(
                store().url,
                'POST',
                playlistId === null ? {} : { exercise_playlist_id: playlistId },
            );
            onStart(session);
        } catch {
            toast.error('Could not start session');
        } finally {
            setBusy(false);
        }
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Start a session</CardTitle>
                <CardDescription>
                    Pick a playlist or log a free session.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
                {todaysSessions.map((s) => (
                    <Button
                        key={s.id}
                        variant="secondary"
                        className="w-full justify-start"
                        onClick={() => onStart(s)}
                    >
                        Continue{' '}
                        {s.exercise_playlist_id === null
                            ? 'free session'
                            : `“${
                                  playlists.find(
                                      (p) => p.id === s.exercise_playlist_id,
                                  )?.name ?? 'session'
                              }”`}
                        <span className="ml-auto text-xs text-muted-foreground">
                            {s.sets.length} set{s.sets.length === 1 ? '' : 's'}
                        </span>
                    </Button>
                ))}

                {playlists.map((p) => (
                    <Button
                        key={p.id}
                        variant="outline"
                        className="w-full justify-start"
                        onClick={() => start(p.id)}
                        disabled={busy}
                    >
                        <Play className="size-4 text-lime-600" />
                        {p.name}
                        <span className="ml-auto text-xs text-muted-foreground">
                            {p.exercise_ids.length} exercise
                            {p.exercise_ids.length === 1 ? '' : 's'}
                        </span>
                    </Button>
                ))}

                <Button
                    variant="outline"
                    className="w-full justify-start"
                    onClick={() => start(null)}
                    disabled={busy}
                >
                    <Play className="size-4" />
                    Free session
                </Button>
            </CardContent>
        </Card>
    );
}
