import { Link } from '@inertiajs/react';
import { ChevronDown, Pencil, Play, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { destroy, show, store, update } from '@/routes/member/playlists';
import { store as storeSession } from '@/routes/member/sessions';

type PlaylistRow =
    | { kind: 'playlist'; playlist: ExercisePlaylist }
    | {
          kind: 'program';
          programId: number;
          name: string;
          workouts: ExercisePlaylist[];
      };

function groupRows(playlists: ExercisePlaylist[]): PlaylistRow[] {
    const rows: PlaylistRow[] = [];
    const seen = new Set<number>();

    for (const playlist of playlists) {
        if (playlist.program_id == null) {
            rows.push({ kind: 'playlist', playlist });
            continue;
        }
        if (seen.has(playlist.program_id)) {
            continue;
        }
        seen.add(playlist.program_id);
        rows.push({
            kind: 'program',
            programId: playlist.program_id,
            name: playlist.program_name ?? playlist.name,
            workouts: playlists.filter(
                (p) => p.program_id === playlist.program_id,
            ),
        });
    }

    return rows;
}

function PlaylistActions({
    playlist,
    onRename,
    onRemove,
}: {
    playlist: ExercisePlaylist;
    onRename: (playlist: ExercisePlaylist) => void;
    onRemove: (playlist: ExercisePlaylist) => void;
}) {
    return (
        <>
            <Button
                variant="ghost"
                size="icon"
                className="size-7 shrink-0"
                onClick={() => onRename(playlist)}
                aria-label={`Rename ${playlist.name}`}
            >
                <Pencil className="size-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                className="size-7 shrink-0"
                onClick={() => onRemove(playlist)}
                aria-label={`Delete ${playlist.name}`}
            >
                <Trash2 className="size-4" />
            </Button>
        </>
    );
}

function ProgramGroup({
    name,
    workouts,
    onRename,
    onRemove,
    onStartSession,
}: {
    name: string;
    workouts: ExercisePlaylist[];
    onRename: (playlist: ExercisePlaylist) => void;
    onRemove: (playlist: ExercisePlaylist) => void;
    onStartSession: (session: WorkoutSession) => void;
}) {
    const [open, setOpen] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);

    async function startSession(playlist: ExercisePlaylist) {
        if (busyId !== null) {
            return;
        }
        setBusyId(playlist.id);
        try {
            const { session } = await memberApi<{ session: WorkoutSession }>(
                storeSession().url,
                'POST',
                { exercise_playlist_id: playlist.id },
            );
            onStartSession(session);
        } catch {
            toast.error('Could not start session');
        } finally {
            setBusyId(null);
        }
    }

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <div className="flex items-center gap-3 py-2.5">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{name}</p>
                    <p className="text-xs text-muted-foreground">
                        {workouts.length} workout
                        {workouts.length === 1 ? '' : 's'}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    className="shrink-0"
                    onClick={() => setOpen((value) => !value)}
                >
                    <ChevronDown
                        className={cn(
                            'size-4 transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                    View
                </Button>
            </div>
            <CollapsibleContent>
                <div className="mb-2 ml-2 divide-y border-l pl-4">
                    {workouts.map((playlist) => (
                        <div
                            key={playlist.id}
                            className="flex items-center gap-3 py-2"
                        >
                            <div className="min-w-0 flex-1">
                                <Link
                                    href={show(playlist.id).url}
                                    className="block truncate text-sm hover:underline"
                                >
                                    {playlist.workout_title ?? playlist.name}
                                </Link>
                                <p className="text-xs text-muted-foreground">
                                    {playlist.exercise_ids.length} exercise
                                    {playlist.exercise_ids.length === 1
                                        ? ''
                                        : 's'}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 shrink-0 text-lime-600 dark:text-lime-400"
                                onClick={() => startSession(playlist)}
                                disabled={busyId !== null}
                                aria-label={`Start ${playlist.workout_title ?? playlist.name}`}
                            >
                                <Play className="size-4" />
                            </Button>
                            <PlaylistActions
                                playlist={playlist}
                                onRename={onRename}
                                onRemove={onRemove}
                            />
                        </div>
                    ))}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

export function PlaylistManager({
    playlists,
    onChange,
    createOpen,
    onCreateOpenChange,
    onStartSession,
}: {
    playlists: ExercisePlaylist[];
    onChange: (playlists: ExercisePlaylist[]) => void;
    createOpen: boolean;
    onCreateOpenChange: (open: boolean) => void;
    onStartSession: (session: WorkoutSession) => void;
}) {
    const [editing, setEditing] = useState<ExercisePlaylist | null>(null);
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);

    function openCreate() {
        setEditing(null);
        setName('');
        onCreateOpenChange(true);
    }

    function openRename(playlist: ExercisePlaylist) {
        setEditing(playlist);
        setName(playlist.name);
        onCreateOpenChange(true);
    }

    async function save() {
        const trimmed = name.trim();
        if (trimmed === '' || saving) {
            return;
        }
        setSaving(true);
        try {
            if (editing === null) {
                const { playlist } = await memberApi<{
                    playlist: ExercisePlaylist;
                }>(store().url, 'POST', { name: trimmed });
                onChange([...playlists, playlist]);
            } else {
                const { playlist } = await memberApi<{
                    playlist: ExercisePlaylist;
                }>(update(editing.id).url, 'PATCH', { name: trimmed });
                onChange(
                    playlists.map((p) => (p.id === playlist.id ? playlist : p)),
                );
            }
            onCreateOpenChange(false);
        } catch {
            toast.error('Could not save playlist');
        } finally {
            setSaving(false);
        }
    }

    async function remove(playlist: ExercisePlaylist) {
        if (!window.confirm(`Delete "${playlist.name}"?`)) {
            return;
        }
        try {
            await memberApi(destroy(playlist.id).url, 'DELETE');
            onChange(playlists.filter((p) => p.id !== playlist.id));
        } catch {
            toast.error('Could not delete playlist');
        }
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="text-base">My playlists</CardTitle>
                <Button variant="ghost" size="sm" onClick={openCreate}>
                    <Plus className="size-4" />
                    New
                </Button>
            </CardHeader>
            <CardContent>
                {playlists.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No playlists yet — create one and add exercises from the
                        list below.
                    </p>
                ) : (
                    <div className="divide-y">
                        {groupRows(playlists).map((row) =>
                            row.kind === 'program' ? (
                                <ProgramGroup
                                    key={`program-${row.programId}`}
                                    name={row.name}
                                    workouts={row.workouts}
                                    onRename={openRename}
                                    onRemove={remove}
                                    onStartSession={onStartSession}
                                />
                            ) : (
                                <div
                                    key={row.playlist.id}
                                    className="flex items-center gap-3 py-2.5"
                                >
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={show(row.playlist.id).url}
                                            className="block truncate text-sm font-medium hover:underline"
                                        >
                                            {row.playlist.name}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {row.playlist.exercise_ids.length}{' '}
                                            exercise
                                            {row.playlist.exercise_ids
                                                .length === 1
                                                ? ''
                                                : 's'}
                                        </p>
                                    </div>
                                    <PlaylistActions
                                        playlist={row.playlist}
                                        onRename={openRename}
                                        onRemove={remove}
                                    />
                                </div>
                            ),
                        )}
                    </div>
                )}
            </CardContent>

            <Dialog open={createOpen} onOpenChange={onCreateOpenChange}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>
                            {editing === null
                                ? 'New playlist'
                                : 'Rename playlist'}
                        </DialogTitle>
                        <DialogDescription>
                            Group exercises you train together.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="playlist-name">Name</Label>
                        <Input
                            id="playlist-name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Push day"
                            onKeyDown={(e) => e.key === 'Enter' && save()}
                        />
                    </div>
                    <div className="flex justify-end">
                        <Button
                            onClick={save}
                            disabled={saving || name.trim() === ''}
                        >
                            Save
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
