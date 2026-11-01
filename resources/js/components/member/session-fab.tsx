import { router, usePage } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { play } from '@/routes/member/playlists';

type ActiveSession = {
    id: number;
    exercise_playlist_id: number | null;
};

/**
 * Floating resume-workout button shown app-wide while the member has a
 * session today. Playlist sessions reopen the guided player; free
 * sessions open the session card on /today. Hidden on the player page
 * itself.
 */
export function SessionFab() {
    const { props, component } = usePage<{ activeSession?: ActiveSession }>();
    const session = props.activeSession;

    if (!session || component === 'member/workout-player') {
        return null;
    }

    function open() {
        router.visit(
            session!.exercise_playlist_id !== null
                ? play(session!.exercise_playlist_id).url
                : `/today?session=${session!.id}`,
        );
    }

    return (
        <button
            type="button"
            onClick={open}
            aria-label="Continue exercise"
            className="fixed right-4 bottom-20 z-40 flex items-center gap-2 rounded-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-5 py-3.5 text-sm font-bold text-black shadow-[0_8px_24px_rgba(175,250,1,0.35)] transition-transform hover:scale-105 active:scale-95"
        >
            <Play className="size-4 fill-current" />
            Continue exercise
        </button>
    );
}
