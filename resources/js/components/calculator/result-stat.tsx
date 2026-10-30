import { Badge } from '@/components/ui/badge';

export function ResultStat({
    label,
    value,
    badge,
}: {
    label: string;
    value: string;
    badge?: string;
}) {
    return (
        <div>
            <p className="text-sm text-muted-foreground">{label}</p>
            <div className="mt-1 flex items-baseline gap-2">
                <span className="text-3xl font-semibold tracking-tight">
                    {value}
                </span>
                {badge && <Badge variant="secondary">{badge}</Badge>}
            </div>
        </div>
    );
}
