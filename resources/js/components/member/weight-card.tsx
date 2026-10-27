import { Scale } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/member/weight-logs';

export type WeightLogEntry = {
    id: number;
    logged_on: string;
    weight_kg: string;
};

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
                    <span className="text-xs text-muted-foreground">
                        {latest.weight_kg} kg
                        {delta !== null && (
                            <>
                                {' '}
                                ({delta > 0 ? '+' : ''}
                                {delta.toFixed(1)})
                            </>
                        )}
                    </span>
                )}
            </CardHeader>
            <CardContent>
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
