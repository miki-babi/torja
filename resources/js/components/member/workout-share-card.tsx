import { forwardRef } from 'react';
import type { WorkoutSession } from '@/components/member/types';
import {
    sessionExerciseCount,
    sessionVolume,
} from '@/components/member/workout-progress';

export type ShareCardLabels = {
    duration: string;
    exercises: string;
    sets: string;
    volume: string;
};

export const WorkoutShareCard = forwardRef<
    HTMLDivElement,
    {
        appName: string;
        workoutTitle: string;
        durationMinutes: number;
        session: WorkoutSession;
        labels: ShareCardLabels;
    }
>(function WorkoutShareCard(
    { appName, workoutTitle, durationMinutes, session, labels },
    ref,
) {
    const stats: { label: string; value: string }[] = [
        { label: labels.duration, value: `${durationMinutes} min` },
        {
            label: labels.exercises,
            value: String(sessionExerciseCount(session)),
        },
        { label: labels.sets, value: String(session.sets.length) },
        {
            label: labels.volume,
            value: `${Math.round(sessionVolume(session)).toLocaleString()} kg`,
        },
    ];

    return (
        <div
            ref={ref}
            className="relative flex h-full min-h-dvh w-full flex-col overflow-hidden bg-[#0B1F0B]"
            style={{
                backgroundImage: "url('/images/share/image.png')",
                backgroundSize: 'cover',
                backgroundPosition: 'center',
            }}
        >
            <div className="absolute inset-0 bg-gradient-to-b from-black/40 via-black/15 to-black/50" />
            <div className="relative flex flex-1 flex-col px-7 pt-10">
                <p className="text-xs font-bold tracking-[0.25em] text-[#AFFA01] uppercase">
                    {appName}
                </p>
                <h2 className="mt-2 text-4xl font-bold tracking-tight text-[#F5F5F5]">
                    {workoutTitle}
                </h2>

                <dl className="mt-8 space-y-5">
                    {stats.map((stat) => (
                        <div key={stat.label}>
                            <dt className="text-sm font-medium text-[#8E8E93]">
                                {stat.label}
                            </dt>
                            <dd className="mt-0.5 text-4xl font-semibold tracking-tight text-[#F5F5F5]">
                                {stat.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>

            <div className="relative overflow-hidden bg-[#AFFA01] py-2.5">
                <p className="text-center text-sm font-black tracking-[0.3em] whitespace-nowrap text-[#0B1F0B] uppercase italic">
                    {Array(6).fill(appName).join(' · ')}
                </p>
            </div>
        </div>
    );
});
