import { Check, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import type { ExercisePlaylist } from '@/components/member/types';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { destroy, store } from '@/routes/member/playlists/exercises';

export function AddToPlaylist({
    playlists,
    exerciseId,
    exerciseName,
    onChange,
    onCreateNew,
}: {
    playlists: ExercisePlaylist[];
    exerciseId: string;
    exerciseName: string;
    onChange: (playlist: ExercisePlaylist) => void;
    onCreateNew: () => void;
}) {
    const [busy, setBusy] = useState(false);
    const inAny = playlists.some((p) => p.exercise_ids.includes(exerciseId));

    async function toggle(playlist: ExercisePlaylist) {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            const { playlist: updated } = await memberApi<{
                playlist: ExercisePlaylist;
            }>(
                playlist.exercise_ids.includes(exerciseId)
                    ? destroy({
                          playlist: playlist.id,
                          exercise: exerciseId,
                      }).url
                    : store(playlist.id).url,
                playlist.exercise_ids.includes(exerciseId) ? 'DELETE' : 'POST',
                playlist.exercise_ids.includes(exerciseId)
                    ? undefined
                    : { exercise_id: exerciseId },
            );
            onChange(updated);
        } catch {
            toast.error('Could not update playlist');
        } finally {
            setBusy(false);
        }
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                aria-label={`Add ${exerciseName} to a playlist`}
                className={cn(
                    'flex size-5 shrink-0 items-center justify-center rounded border',
                    inAny
                        ? 'border-lime-500 bg-lime-400 text-lime-950'
                        : 'border-input',
                )}
            >
                {inAny ? (
                    <Check className="size-3.5" />
                ) : (
                    <Plus className="size-3.5 text-muted-foreground" />
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuLabel>Add to playlist</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {playlists.length === 0 && (
                    <DropdownMenuItem disabled>
                        No playlists yet
                    </DropdownMenuItem>
                )}
                {playlists.map((p) => (
                    <DropdownMenuCheckboxItem
                        key={p.id}
                        checked={p.exercise_ids.includes(exerciseId)}
                        onCheckedChange={() => toggle(p)}
                        onSelect={(e) => e.preventDefault()}
                    >
                        {p.name}
                    </DropdownMenuCheckboxItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={onCreateNew}>
                    <Plus className="size-4" />
                    New playlist…
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
