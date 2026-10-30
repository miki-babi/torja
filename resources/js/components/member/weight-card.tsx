import { Scale } from 'lucide-react';
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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/member/weight-logs';

export type WeightLogEntry = {
    id: number;
    logged_on: string;
    weight_kg: string;
};

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

export function WeightCard({
    logs,
    onLogged,
}: {
    logs: WeightLogEntry[];
    onLogged: (log: WeightLogEntry) => void;
}) {
    const [value, setValue] = useState('');
    const [saving, setSaving] = useState(false);

    const latest = logs[0] ?? null;
    const previous = logs[1] ?? null;
    const delta =
        latest && previous
            ? Number(latest.weight_kg) - Number(previous.weight_kg)
            : null;

    // Logs arrive newest-first; the chart wants them oldest-first.
    const points = [...logs]
        .sort((a, b) => a.logged_on.localeCompare(b.logged_on))
        .map((l) => ({
            label: new Date(
                `${l.logged_on.slice(0, 10)}T12:00:00`,
            ).toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
            }),
            kg: Number(l.weight_kg),
        }));

    async function submit(e: FormEvent) {
        e.preventDefault();
        const weight = Number.parseFloat(value);

        if (Number.isNaN(weight) || saving) {
            return;
        }

        setSaving(true);

        try {
            const response = await fetch(store().url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((c) => c.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1] ?? '',
                    ),
                },
                body: JSON.stringify({ weight_kg: weight }),
            });

            if (response.ok) {
                const data = (await response.json()) as {
                    log: WeightLogEntry;
                };
                onLogged(data.log);
                setValue('');
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="text-base">Weight</CardTitle>
                {latest !== null && (
                    <span className="text-xs font-semibold text-[#AFFA01]">
                        {latest.weight_kg} kg
                        {delta !== null && (
                            <span className="text-[#A1A1AA]">
                                {' '}
                                ({delta > 0 ? '+' : ''}
                                {delta.toFixed(1)})
                            </span>
                        )}
                    </span>
                )}
            </CardHeader>
            <CardContent>
                {points.length > 0 && (
                    <div className="mb-4 h-36 w-full">
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
                                    dataKey="kg"
                                    name="Weight (kg)"
                                    stroke="#AFFA01"
                                    strokeWidth={2}
                                    dot={{ r: 3, fill: '#AFFA01' }}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                )}

                <form onSubmit={submit} className="flex items-center gap-2">
                    <div className="relative flex-1">
                        <Scale className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="number"
                            inputMode="decimal"
                            step="0.1"
                            min="20"
                            max="500"
                            value={value}
                            onChange={(e) => setValue(e.target.value)}
                            placeholder="kg"
                            className="pl-9"
                            required
                        />
                    </div>
                    <Button type="submit" disabled={saving}>
                        Log
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
