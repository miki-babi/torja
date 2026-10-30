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
                        className="w-full justify-start h-12 rounded-2xl border-[#2E2E2E] bg-[#1A1A1A] hover:border-[#AFFA01]/40 hover:bg-[#222222]"
                        onClick={() => start(p.id)}
                        disabled={busy}
                    >
                        <Play className="size-4 text-[#AFFA01] fill-[#AFFA01]" />
                        <span className="font-semibold text-[#F5F5F5]">{p.name}</span>
                        <span className="ml-auto text-xs text-[#8E8E93]">
                            {p.exercise_ids.length} exercise
                            {p.exercise_ids.length === 1 ? '' : 's'}
                        </span>
                    </Button>
                ))}

                <Button
                    variant="outline"
                    className="w-full justify-start h-12 rounded-2xl border-[#2E2E2E] bg-[#1A1A1A] hover:border-[#AFFA01]/40 hover:bg-[#222222]"
                    onClick={() => start(null)}
                    disabled={busy}
                >
                    <Play className="size-4 text-[#AFFA01]" />
                    <span className="font-semibold text-[#F5F5F5]">Free session</span>
                </Button>
            </CardContent>
        </Card>
    );
}
