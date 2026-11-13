import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CheckCircle2,
    ChevronRight,
    Dumbbell,
    Flame,
    Play,
} from 'lucide-react';
import { MobileShell } from '@/components/member/mobile-shell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';

type Workout = {
    id: number;
    title: string;
    exercises_count: number;
    done: boolean;
};

type Props = {
    userName: string;
    streak: number;
    program: {
        id: number;
        name: string;
        image_url: string | null;
        url: string;
        workouts_count: number;
    } | null;
    workouts: Workout[];
    nextWorkoutId: number | null;
    nextWorkoutUrl: string | null;
    programs: { id: number; name: string; url: string }[];
    checkinDue: boolean;
    checkinUrl: string;
    weekStats: { sessions: number };
};

export default function MemberToday({
    userName,
    streak,
    program,
    workouts,
    nextWorkoutId,
    nextWorkoutUrl,
    programs,
    checkinDue,
    checkinUrl,
    weekStats,
}: Props) {
    const { t } = useTranslation();
    const { coach } = usePage().props;
    const nextWorkout = workouts.find((w) => w.id === nextWorkoutId) ?? null;
    const allDone =
        workouts.length > 0 && workouts.every((workout) => workout.done);

    return (
        <MobileShell>
            <Head title={t('member.today.title', "Today's workout")} />

            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {t('member.today.hello', 'Hello')}, {userName}
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('member.today.title', "Today's workout")}
                    </h1>
                </div>
                {streak > 0 && (
                    <span className="flex items-center gap-1 rounded-full border border-[#AFFA01]/30 bg-[#AFFA01]/10 px-3 py-1 text-xs font-semibold text-[#AFFA01]">
                        <Flame className="size-3.5" />
                        {streak}
                    </span>
                )}
            </div>

            {coach !== null && (
                <div className="mt-4 flex items-center gap-3 rounded-2xl border border-[#2E2E2E] bg-[#1A1A1A] p-4">
                    <Avatar className="size-10">
                        <AvatarImage src={coach.avatar ?? undefined} />
                        <AvatarFallback>
                            {coach.name.slice(0, 1)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">
                            {coach.name}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('member.today.your_coach', 'Your coach')}
                        </p>
                    </div>
                </div>
            )}

            {program === null ? (
                <div className="mt-4 rounded-2xl border border-dashed border-[#2E2E2E] p-6 text-center">
                    <Dumbbell className="mx-auto size-8 text-muted-foreground" />
                    <p className="mt-3 text-sm font-medium">
                        {t(
                            'member.today.no_program',
                            'No workout program yet',
                        )}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {t(
                            'member.today.no_program_hint',
                            'Your coach will assign one — or open a shared program link they sent you.',
                        )}
                    </p>
                </div>
            ) : (
                <>
                    <div className="mt-4 overflow-hidden rounded-2xl border border-[#2E2E2E] bg-[#1A1A1A]">
                        {program.image_url !== null && (
                            <img
                                src={program.image_url}
                                alt={program.name}
                                className="h-36 w-full object-cover"
                            />
                        )}
                        <div className="p-4">
                            <Link
                                href={program.url}
                                className="text-base font-semibold hover:text-[#AFFA01]"
                            >
                                {program.name}
                            </Link>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {program.workouts_count}{' '}
                                {t('member.today.workouts', 'workouts')}
                            </p>

                            {nextWorkout !== null &&
                                nextWorkoutUrl !== null && (
                                    <Button
                                        asChild
                                        className="mt-4 w-full bg-[#AFFA01] font-semibold text-black hover:bg-[#9BE801]"
                                    >
                                        <Link href={nextWorkoutUrl}>
                                            <Play className="size-4 fill-current" />
                                            {allDone
                                                ? t(
                                                      'member.today.restart',
                                                      'Restart',
                                                  )
                                                : t(
                                                      'member.today.start',
                                                      'Start',
                                                  )}{' '}
                                            · {nextWorkout.title}
                                        </Link>
                                    </Button>
                                )}
                        </div>
                    </div>

                    {workouts.length > 0 && (
                        <div className="mt-4 rounded-2xl border border-[#2E2E2E] bg-[#1A1A1A]">
                            <p className="border-b border-[#2E2E2E] px-4 py-3 text-sm font-semibold">
                                {t('member.today.this_week', 'This week')}
                            </p>
                            <div className="divide-y divide-[#2E2E2E]">
                                {workouts.map((workout) => (
                                    <div
                                        key={workout.id}
                                        className="flex items-center gap-3 px-4 py-3"
                                    >
                                        {workout.done ? (
                                            <CheckCircle2 className="size-5 shrink-0 text-[#AFFA01]" />
                                        ) : (
                                            <span className="size-5 shrink-0 rounded-full border border-[#3A3A3A]" />
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {workout.title}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {workout.exercises_count}{' '}
                                                {t(
                                                    'member.today.exercises',
                                                    'exercises',
                                                )}
                                            </p>
                                        </div>
                                        {!workout.done &&
                                            nextWorkoutId ===
                                                workout.id && (
                                                <Link
                                                    href={
                                                        nextWorkoutUrl ?? '#'
                                                    }
                                                    aria-label={`Play ${workout.title}`}
                                                    className="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#AFFA01] text-black"
                                                >
                                                    <Play className="size-4 fill-current" />
                                                </Link>
                                            )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </>
            )}

            {checkinDue && (
                <Link
                    href={checkinUrl}
                    className="mt-4 flex items-center gap-3 rounded-2xl border border-[#AFFA01]/30 bg-[#AFFA01]/10 p-4"
                >
                    <CalendarCheck className="size-5 shrink-0 text-[#AFFA01]" />
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-[#AFFA01]">
                            {t(
                                'member.today.checkin_due',
                                'Weekly check-in is due',
                            )}
                        </p>
                        <p className="text-xs text-[#AFFA01]/70">
                            {t(
                                'member.today.checkin_hint',
                                'Send your weight and a quick update to your coach.',
                            )}
                        </p>
                    </div>
                    <ChevronRight className="size-4 text-[#AFFA01]" />
                </Link>
            )}

            {programs.length > 0 && (
                <div className="mt-4 rounded-2xl border border-[#2E2E2E] bg-[#1A1A1A]">
                    {programs.map((other) => (
                        <Link
                            key={other.id}
                            href={other.url}
                            className="flex items-center justify-between px-4 py-3 text-sm font-medium hover:text-[#AFFA01]"
                        >
                            {other.name}
                            <ChevronRight className="size-4 text-muted-foreground" />
                        </Link>
                    ))}
                </div>
            )}

            {weekStats.sessions > 0 && (
                <p className="mt-4 text-center text-xs text-muted-foreground">
                    {t('member.today.week_stats', ':count session(s) this week', {
                        count: weekStats.sessions,
                    })}
                </p>
            )}
        </MobileShell>
    );
}
