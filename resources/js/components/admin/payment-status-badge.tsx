import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const STATUS_STYLES: Record<string, string> = {
    success:
        'border-lime-500/30 bg-lime-500/10 text-lime-700 dark:text-lime-400',
    pending:
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    failed: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400',
    refunded: 'border-border bg-muted text-muted-foreground',
};

export function PaymentStatusBadge({ status }: { status: string }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'font-medium capitalize',
                STATUS_STYLES[status] ?? 'border-border bg-muted',
            )}
        >
            {status}
        </Badge>
    );
}
