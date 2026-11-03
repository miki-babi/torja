import { Dumbbell } from 'lucide-react';
import {
    PolarAngleAxis,
    PolarGrid,
    PolarRadiusAxis,
    Radar,
    RadarChart,
    ResponsiveContainer,
    Tooltip,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';

export type MuscleVolume = { key: string; volume: number };

/**
 * "Muscle balance" radar: last-7-day volume (weight × reps) per body
 * part, normalized against the strongest axis so the polygon shows
 * balance, while the tooltip reveals the real kilograms moved.
 */
export function MuscleRadarCard({ volumes }: { volumes: MuscleVolume[] }) {
    const { t } = useTranslation();
    const max = Math.max(...volumes.map((v) => v.volume), 0);
    const data = volumes.map((v) => ({
        part: t(`exercise_values.body_part.${v.key}`),
        value: max > 0 ? Math.round((v.volume / max) * 100) : 0,
        kg: v.volume,
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Dumbbell className="size-4 text-[#AFFA01]" />
                    {t('member.progress.muscle_balance')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {max === 0 ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">
                        {t('member.progress.muscle_empty')}
                    </p>
                ) : (
                    <ResponsiveContainer width="100%" height={280}>
                        <RadarChart data={data} outerRadius="72%">
                            <PolarGrid stroke="#2E2E2E" />
                            <PolarAngleAxis
                                dataKey="part"
                                tick={{ fill: '#8E8E93', fontSize: 11 }}
                            />
                            <PolarRadiusAxis
                                domain={[0, 100]}
                                tick={false}
                                axisLine={false}
                            />
                            <Radar
                                dataKey="value"
                                stroke="#AFFA01"
                                fill="#AFFA01"
                                fillOpacity={0.28}
                                strokeWidth={2}
                            />
                            <Tooltip
                                content={({ label }) => {
                                    const row = data.find(
                                        (d) => d.part === label,
                                    );
                                    if (row === undefined) {
                                        return null;
                                    }
                                    return (
                                        <div className="rounded-md border bg-popover px-3 py-1.5 text-xs shadow-md">
                                            <span className="font-medium">
                                                {row.kg.toLocaleString()} kg
                                            </span>
                                        </div>
                                    );
                                }}
                            />
                        </RadarChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
