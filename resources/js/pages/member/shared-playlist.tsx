import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, Share2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { ExerciseSummary } from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { login } from '@/routes';
import { show as exerciseShow } from '@/routes/member/exercises';
import { show as playlistShow } from '@/routes/member/playlists';

type SharedPlaylist = {
    name: string;
    owner: string;
    exercises: ExerciseSummary[];
};

export default function MemberSharedPlaylist({
    playlist,
    isOwner,
    saveUrl,
    playlistUrl,
}: {
    playlist: SharedPlaylist;
    isOwner: boolean;
    saveUrl: string;
    playlistUrl: string;
}) {
    const { auth } = usePage().props;
    const [saving, setSaving] = useState(false);

    async function save() {
        if (saving) {
            return;
        }
        setSaving(true);
        try {
            const { playlist_id } = await memberApi<{ playlist_id: number }>(
                saveUrl,
                'POST',
            );
            router.visit(playlistShow(playlist_id).url);
        } catch {
            toast.error('Could not save playlist');
            setSaving(false);
        }
    }

    return (
        <MobileShell>
            <Head title={playlist.name} />

            <div className="mt-4 flex items-start gap-3">
                <div className="min-w-0 flex-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {playlist.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Shared by {playlist.owner} · {playlist.exercises.length}{' '}
                        exercise
                        {playlist.exercises.length === 1 ? '' : 's'}
                    </p>
                </div>
                <span className="flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs text-muted-foreground">
                    <Share2 className="size-3.5" />
                    Shared
                </span>
            </div>

            <Card className="mt-4">
                <CardContent className="divide-y p-0">
                    {playlist.exercises.map((ex) => (
                        <Link
                            key={ex.slug}
                            href={exerciseShow(ex.slug).url}
                            className="flex items-center gap-3 px-4 py-3 hover:bg-accent/50"
                        >
                            <img
                                src={ex.image_url}
                                alt=""
                                loading="lazy"
                                className="size-10 shrink-0 rounded-lg object-cover"
                            />
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium capitalize">
                                    {ex.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {ex.detail}
                                </p>
                            </div>
                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                        </Link>
                    ))}
                </CardContent>
            </Card>

            <div className="mt-4">
                {isOwner ? (
                    <div className="text-center">
                        <p className="text-sm text-muted-foreground">
                            This is your shared playlist.
                        </p>
                        <Button variant="outline" className="mt-2" asChild>
                            <Link href={playlistUrl}>Open playlist</Link>
                        </Button>
                    </div>
                ) : auth.user === null ? (
                    <Button
                        className="w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
                        asChild
                    >
                        <Link href={login()}>Log in to save a copy</Link>
                    </Button>
                ) : (
                    <Button
                        className="w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
                        disabled={saving}
                        onClick={save}
                    >
                        {saving ? 'Saving…' : 'Save to my playlists'}
                    </Button>
                )}
            </div>
        </MobileShell>
    );
}
