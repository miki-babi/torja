import type { LucideIcon } from 'lucide-react';
import { TrendingDown, TrendingUp } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: string | number;
    icon?: LucideIcon;
    /** e.g. "+12%" — rendered with an up/down arrow */
    delta?: string;
    /** 'up' = lime, 'down' = destructive, 'neutral' = muted */
    trend?: 'up' | 'down' | 'neutral';
    hint?: string;
};

export function StatCard({
    label,
    value,
    icon: Icon,
    delta,
    trend,
    hint,
}: Props) {
    return (
        <Card className="gap-0 py-0">
            <CardContent className="p-4">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {label}
                    </p>
                    {Icon && (
                        <Icon className="size-4 text-muted-foreground/60" />
                    )}
                </div>
                <p className="mt-1.5 text-2xl font-semibold tabular-nums">
                    {typeof value === 'number' ? value.toLocaleString() : value}
                </p>
                {(delta || hint) && (
                    <div className="mt-1 flex items-center gap-1.5 text-xs">
                        {delta && (
                            <span
                                className={cn(
                                    'inline-flex items-center gap-0.5 font-medium',
                                    trend === 'up' &&
                                        'text-lime-600 dark:text-lime-400',
                                    trend === 'down' && 'text-destructive',
                                    (!trend || trend === 'neutral') &&
                                        'text-muted-foreground',
                                )}
                            >
                                {trend === 'up' && (
                                    <TrendingUp className="size-3" />
                                )}
                                {trend === 'down' && (
                                    <TrendingDown className="size-3" />
                                )}
                                {delta}
                            </span>
                        )}
                        {hint && (
                            <span className="truncate text-muted-foreground">
                                {hint}
                            </span>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
