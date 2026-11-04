import { router, usePage } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { play } from '@/routes/member/sessions';

type ActiveSession = {
    id: number;
    exercise_playlist_id: number | null;
};

/**
 * Floating resume-workout button shown app-wide while the member has a
 * session today. Reopens the workout player — playlist sessions forward
 * to the playlist player, free sessions play the exercises logged so
 * far. Hidden on the player page itself.
 */
export function SessionFab() {
    const { props, component } = usePage<{ activeSession?: ActiveSession }>();
    const session = props.activeSession;

    if (!session || component === 'member/workout-player') {
        return null;
    }

    function open() {
        router.visit(play(session!.id).url);
    }

    return (
        <button
            type="button"
            onClick={open}
            aria-label="Continue exercise"
            className="fixed right-4 bottom-20 z-40 flex items-center gap-2 rounded-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-5 py-3.5 text-sm font-bold text-black transition-transform hover:scale-105 active:scale-95"
        >
            <Play className="size-4 fill-current" />
            Continue exercise
        </button>
    );
}
