import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    Pencil,
    Play,
    Share2,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    share,
    unshare,
} from '@/actions/App/Http/Controllers/Member/PlaylistController';
import type { ExerciseSummaries } from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import { WorkoutSessionCard } from '@/components/member/workout-session';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { exercise } from '@/routes/member';
import { show as exerciseShow } from '@/routes/member/exercises';
import { destroy, update } from '@/routes/member/playlists';
import { destroy as removeExercise } from '@/routes/member/playlists/exercises';
import { store as storeSession } from '@/routes/member/sessions';

export default function MemberPlaylistShow({
    playlist: initialPlaylist,
    sessions: initialSessions,
    exerciseSummaries,
}: {
    playlist: ExercisePlaylist;
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
}) {
    const [playlist, setPlaylist] = useState(initialPlaylist);
    const [sessions, setSessions] = useState<WorkoutSession[]>(initialSessions);
    const [renameOpen, setRenameOpen] = useState(false);
    const [name, setName] = useState(playlist.name);
    const [saving, setSaving] = useState(false);
    const [busy, setBusy] = useState(false);
    const [sessionDismissed, setSessionDismissed] = useState(false);
    const [shareOpen, setShareOpen] = useState(false);
    const [sharing, setSharing] = useState(false);
    const [, copy] = useClipboard();

    const todayKey = new Date().toISOString().slice(0, 10);
    const todaysSession =
        sessions.find(
            (s) =>
                s.date === todayKey && s.exercise_playlist_id === playlist.id,
        ) ?? null;
    const activeSession = sessionDismissed ? null : todaysSession;

    function syncPlaylist(updated: ExercisePlaylist) {
        setPlaylist(updated);
    }

    function upsertSession(next: WorkoutSession) {
        setSessions((list) =>
            list.some((s) => s.id === next.id)
                ? list.map((s) => (s.id === next.id ? next : s))
                : [next, ...list],
        );
    }

    async function rename() {
        const trimmed = name.trim();
        if (trimmed === '' || saving) {
            return;
        }
        setSaving(true);
        try {
            const { playlist: updated } = await memberApi<{
                playlist: ExercisePlaylist;
            }>(update(playlist.id).url, 'PATCH', { name: trimmed });
            syncPlaylist(updated);
            setRenameOpen(false);
        } catch {
            toast.error('Could not rename playlist');
        } finally {
            setSaving(false);
        }
    }

    async function remove() {
        if (!window.confirm(`Delete "${playlist.name}"?`)) {
            return;
        }
        try {
            await memberApi(destroy(playlist.id).url, 'DELETE');
            router.visit(exercise().url);
        } catch {
            toast.error('Could not delete playlist');
        }
    }

    async function removeItem(exerciseId: string) {
        try {
            const { playlist: updated } = await memberApi<{
                playlist: ExercisePlaylist;
            }>(
                removeExercise({
                    playlist: playlist.id,
                    exercise: exerciseId,
                }).url,
                'DELETE',
            );
            syncPlaylist(updated);
        } catch {
            toast.error('Could not remove exercise');
        }
    }

    async function openShare() {
        if (playlist.share_url) {
            setShareOpen(true);
            return;
        }
        setSharing(true);
        try {
            const { share_url } = await memberApi<{ share_url: string }>(
                share(playlist.id).url,
                'POST',
            );
            setPlaylist({ ...playlist, share_url });
            setShareOpen(true);
        } catch {
            toast.error('Could not create share link');
        } finally {
            setSharing(false);
        }
    }

    async function revokeShare() {
        try {
            await memberApi(unshare(playlist.id).url, 'DELETE');
            setPlaylist({ ...playlist, share_url: null });
            setShareOpen(false);
            toast.success('Share link revoked');
        } catch {
            toast.error('Could not revoke link');
        }
    }

    async function copyLink() {
        if (playlist.share_url && (await copy(playlist.share_url))) {
            toast.success('Link copied');
        }
    }

    async function startSession() {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            const { session } = await memberApi<{ session: WorkoutSession }>(
                storeSession().url,
                'POST',
                { exercise_playlist_id: playlist.id },
            );
            upsertSession(session);
            setSessionDismissed(false);
        } catch {
            toast.error('Could not start session');
        } finally {
            setBusy(false);
        }
    }

    return (
        <MobileShell>
            <Head title={playlist.name} />
            <div className="flex items-center justify-between">
                <Button variant="ghost" size="sm" asChild className="-ml-2">
                    <Link href={exercise()}>
                        <ChevronLeft className="size-4" />
                        Exercise
                    </Link>
                </Button>
                <div className="flex items-center gap-1">
                    {playlist.can_share && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            disabled={sharing}
                            onClick={openShare}
                            aria-label={`Share ${playlist.name}`}
                        >
                            <Share2 className="size-4" />
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => {
                            setName(playlist.name);
                            setRenameOpen(true);
                        }}
                        aria-label={`Rename ${playlist.name}`}
                    >
                        <Pencil className="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={remove}
                        aria-label={`Delete ${playlist.name}`}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </div>

            <div className="mt-4 flex items-center gap-2">
                <h1 className="text-xl font-semibold tracking-tight">
                    {playlist.name}
                </h1>
                {playlist.generated_for != null && (
                    <Badge variant="secondary">daily</Badge>
                )}
            </div>
            <p className="text-sm text-muted-foreground">
                {playlist.exercise_ids.length} exercise
                {playlist.exercise_ids.length === 1 ? '' : 's'}
            </p>

            {activeSession !== null ? (
                <div className="mt-4">
                    <WorkoutSessionCard
                        session={activeSession}
                        playlist={playlist}
                        sessions={sessions}
                        exerciseSummaries={exerciseSummaries}
                        onSessionChange={upsertSession}
                        onBack={() => setSessionDismissed(true)}
                    />
                </div>
            ) : (
                <>
                    <Card className="mt-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Exercises
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y p-0">
                            {playlist.exercise_ids.length === 0 && (
                                <p className="px-6 py-3 text-sm text-muted-foreground">
                                    No exercises yet — add some from the
                                    exercise list.
                                </p>
                            )}
                            {playlist.exercise_ids.map((id) => {
                                const ex = exerciseSummaries[id];
                                return (
                                    <div
                                        key={id}
                                        className="flex items-center gap-3 px-6 py-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">
                                                {ex?.name ?? id}
                                            </p>
                                            {ex !== undefined && (
                                                <p className="text-xs text-muted-foreground">
                                                    {ex.detail}
                                                </p>
                                            )}
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-7 shrink-0"
                                            onClick={() => removeItem(id)}
                                            aria-label={`Remove ${ex?.name ?? id}`}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                        <Link
                                            href={exerciseShow(id).url}
                                            aria-label={`View ${ex?.name ?? id}`}
                                            className="flex size-7 shrink-0 items-center justify-center rounded-full border text-muted-foreground"
                                        >
                                            <ChevronRight className="size-4" />
                                        </Link>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <Button
                        onClick={startSession}
                        disabled={busy}
                        className="mt-4 w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
                    >
                        <Play className="size-4" />
                        Start session
                    </Button>
                </>
            )}

            <Dialog open={shareOpen} onOpenChange={setShareOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Share playlist</DialogTitle>
                        <DialogDescription>
                            Anyone with this link can view the playlist and save
                            a copy.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex gap-2">
                        <Input
                            readOnly
                            value={playlist.share_url ?? ''}
                            onFocus={(e) => e.target.select()}
                        />
                        <Button
                            variant="outline"
                            size="icon"
                            className="shrink-0"
                            onClick={copyLink}
                            aria-label="Copy link"
                        >
                            <Copy className="size-4" />
                        </Button>
                    </div>
                    <Button
                        variant="ghost"
                        className="w-fit text-destructive"
                        onClick={revokeShare}
                    >
                        Revoke link
                    </Button>
                </DialogContent>
            </Dialog>

            <Dialog open={renameOpen} onOpenChange={setRenameOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Rename playlist</DialogTitle>
                        <DialogDescription>
                            Group exercises you train together.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="playlist-rename">Name</Label>
                        <Input
                            id="playlist-rename"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Push day"
                            onKeyDown={(e) => e.key === 'Enter' && rename()}
                        />
                    </div>
                    <div className="flex justify-end">
                        <Button
                            onClick={rename}
                            disabled={saving || name.trim() === ''}
                        >
                            Save
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </MobileShell>
    );
}
