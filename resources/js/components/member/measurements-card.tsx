import { Ruler } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { memberApi } from '@/components/member/member-api';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { store } from '@/routes/member/measurements';

export type MeasurementEntry = {
    id: number;
    logged_on: string;
    waist_cm: string | null;
    chest_cm: string | null;
    arm_cm: string | null;
    hip_cm: string | null;
    thigh_cm: string | null;
};

const FIELDS = [
    'waist_cm',
    'chest_cm',
    'arm_cm',
    'hip_cm',
    'thigh_cm',
] as const;

type Field = (typeof FIELDS)[number];

function ChartTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: { value?: number; name?: string }[];
    label?: string;
}) {
    if (!active || !payload?.length) {
        return null;
    }
    return (
        <div className="rounded-md border bg-popover px-3 py-2 text-xs shadow-md">
            <p className="mb-1 font-medium">{label}</p>
            {payload.map((p, i) => (
                <p key={i} className="text-muted-foreground">
                    {p.name}:{' '}
                    <span className="font-medium text-foreground">
                        {Number(p.value).toLocaleString()}
                    </span>
                </p>
            ))}
        </div>
    );
}

/**
 * Body-measurement tracker: per-metric trend chart plus a same-day upsert
 * log form. Empty fields are ignored; at least one is required.
 */
export function MeasurementsCard({
    measurements,
    onLogged,
}: {
    measurements: MeasurementEntry[];
    onLogged: (entry: MeasurementEntry) => void;
}) {
    const { t } = useTranslation();
    const [field, setField] = useState<Field>('waist_cm');
    const [values, setValues] = useState<Record<Field, string>>({
        waist_cm: '',
        chest_cm: '',
        arm_cm: '',
        hip_cm: '',
        thigh_cm: '',
    });
    const [saving, setSaving] = useState(false);

    const latest = measurements[0] ?? null;

    const points = [...measurements]
        .sort((a, b) => a.logged_on.localeCompare(b.logged_on))
        .filter((m) => m[field] !== null)
        .map((m) => ({
            label: new Date(
                `${m.logged_on.slice(0, 10)}T12:00:00`,
            ).toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
            }),
            cm: Number(m[field]),
        }));

    async function submit(e: FormEvent) {
        e.preventDefault();
        if (saving || FIELDS.every((f) => values[f].trim() === '')) {
            return;
        }
        setSaving(true);
        try {
            const body = Object.fromEntries(
                FIELDS.map((f) => [
                    f,
                    values[f].trim() === '' ? null : Number(values[f]),
                ]),
            );
            const { measurement } = await memberApi<{
                measurement: MeasurementEntry;
            }>(store().url, 'POST', body);
            onLogged(measurement);
            setValues({
                waist_cm: '',
                chest_cm: '',
                arm_cm: '',
                hip_cm: '',
                thigh_cm: '',
            });
        } finally {
            setSaving(false);
        }
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="text-base">
                    {t('member.progress.measurements')}
                </CardTitle>
                {latest !== null && latest[field] !== null && (
                    <span className="text-xs font-semibold text-[#AFFA01]">
                        {latest[field]} cm
                    </span>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="-mx-1 flex scrollbar-none gap-2 overflow-x-auto px-1">
                    {FIELDS.map((f) => (
                        <button
                            key={f}
                            type="button"
                            onClick={() => setField(f)}
                            className={cn(
                                'shrink-0 rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors',
                                f === field
                                    ? 'border-[#AFFA01]/60 bg-[#AFFA01]/10 text-[#AFFA01]'
                                    : 'border-[#2E2E2E] text-[#8E8E93] hover:text-[#F5F5F5]',
                            )}
                        >
                            {t(`member.progress.metric.${f}`)}
                        </button>
                    ))}
                </div>

                {points.length > 0 && (
                    <div className="h-32 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart
                                data={points}
                                margin={{
                                    top: 4,
                                    right: 4,
                                    bottom: 0,
                                    left: -18,
                                }}
                            >
                                <CartesianGrid
                                    vertical={false}
                                    stroke="var(--border)"
                                    strokeDasharray="4 4"
                                />
                                <XAxis
                                    dataKey="label"
                                    tickLine={false}
                                    axisLine={false}
                                    tick={{
                                        fontSize: 11,
                                        fill: 'var(--muted-foreground)',
                                    }}
                                />
                                <YAxis
                                    domain={['dataMin - 1', 'dataMax + 1']}
                                    tickLine={false}
                                    axisLine={false}
                                    width={40}
                                    tick={{
                                        fontSize: 11,
                                        fill: 'var(--muted-foreground)',
                                    }}
                                />
                                <Tooltip content={<ChartTooltip />} />
                                <Line
                                    type="monotone"
                                    dataKey="cm"
                                    name={t(`member.progress.metric.${field}`)}
                                    stroke="#AFFA01"
                                    strokeWidth={2}
                                    dot={{ r: 3, fill: '#AFFA01' }}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                )}

                <form onSubmit={submit} className="space-y-2">
                    <div className="grid grid-cols-2 gap-2">
                        {FIELDS.map((f) => (
                            <Input
                                key={f}
                                type="number"
                                inputMode="decimal"
                                step="0.1"
                                min="10"
                                max="400"
                                value={values[f]}
                                onChange={(e) =>
                                    setValues((v) => ({
                                        ...v,
                                        [f]: e.target.value,
                                    }))
                                }
                                placeholder={`${t(`member.progress.metric.${f}`)} (cm)`}
                            />
                        ))}
                    </div>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={
                            saving ||
                            FIELDS.every((f) => values[f].trim() === '')
                        }
                    >
                        <Ruler className="size-4" />
                        {t('member.progress.log_measurements')}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
