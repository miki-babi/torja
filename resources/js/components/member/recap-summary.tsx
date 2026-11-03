import { Dumbbell, Layers, Timer, TrendingUp } from 'lucide-react';
import type { RecapSummary } from '@/components/member/types';

type Translate = (
    key: string,
    params?: Record<string, string | number>,
) => string;

/**
 * Stat chips + per-exercise rows for a workout recap. Used both inside the
 * feed card and as the preview in the share composer (where duration is
 * unknown until the session is finished).
 */
export function RecapSummaryBlock({
    summary,
    t,
    maxExercises = 4,
}: {
    summary: RecapSummary;
    t: Translate;
    maxExercises?: number;
}) {
    const stats = [
        ...(summary.duration_minutes !== null
            ? [
                  {
                      icon: Timer,
                      value: t('member.recap.duration_min', {
                          count: summary.duration_minutes,
                      }),
                      label: t('workout_share.duration'),
                  },
              ]
            : []),
        {
            icon: Dumbbell,
            value: String(summary.exercise_count),
            label: t('workout_share.exercises'),
        },
        {
            icon: Layers,
            value: String(summary.set_count),
            label: t('workout_share.sets'),
        },
        {
            icon: TrendingUp,
            value: `${Math.round(summary.volume_kg).toLocaleString()} kg`,
            label: t('workout_share.volume'),
        },
    ];

    const visible = summary.exercises.slice(0, maxExercises);
    const hidden = summary.exercises.length - visible.length;

    return (
        <div className="rounded-2xl border border-[#2E2E2E] bg-[#262626] p-3.5">
            <div className="flex items-center gap-2">
                <Dumbbell className="size-4 shrink-0 text-[#AFFA01]" />
                <p className="truncate text-sm font-bold text-[#F5F5F5]">
                    {summary.title ?? t('member.recap.free_session')}
                </p>
            </div>

            <div className="mt-3 flex gap-2">
                {stats.map((stat) => (
                    <div
                        key={stat.label}
                        className="min-w-0 flex-1 rounded-xl bg-[#1F1F1F] px-2 py-2 text-center"
                    >
                        <stat.icon className="mx-auto size-3.5 text-[#8E8E93]" />
                        <p className="mt-1 truncate text-sm font-bold text-[#F5F5F5]">
                            {stat.value}
                        </p>
                        <p className="truncate text-[10px] font-medium text-[#8E8E93] uppercase">
                            {stat.label}
                        </p>
                    </div>
                ))}
            </div>

            {visible.length > 0 && (
                <div className="mt-3 space-y-1.5 border-t border-[#2E2E2E] pt-3">
                    {visible.map((exercise, i) => (
                        <div
                            key={`${exercise.name}-${i}`}
                            className="flex items-baseline justify-between gap-3 text-xs"
                        >
                            <span className="truncate font-medium text-[#F5F5F5]">
                                {exercise.name}
                            </span>
                            <span className="shrink-0 text-[#A1A1AA]">
                                {t('member.progress.sets_count', {
                                    count: exercise.sets,
                                })}
                                {' · '}
                                {exercise.weight_kg !== null
                                    ? `${exercise.weight_kg} kg × ${exercise.reps ?? 0}`
                                    : `${exercise.reps ?? 0} ${t('member.progress.reps_chart').toLowerCase()}`}
                            </span>
                        </div>
                    ))}
                    {hidden > 0 && (
                        <p className="text-[11px] font-medium text-[#8E8E93]">
                            +{hidden} {t('workout_share.exercises').toLowerCase()}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
