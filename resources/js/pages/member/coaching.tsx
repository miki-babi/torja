import { Head, Link } from '@inertiajs/react';
import { CalendarClock, Dumbbell, RefreshCw } from 'lucide-react';
import { MobileShell } from '@/components/member/mobile-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Active = {
    id: number;
    plan_name: string;
    trainer_name: string;
    starts_at: string;
    ends_at: string;
    days_left: number;
    due_soon: boolean;
    renew_url: string;
} | null;

type HistoryRow = {
    id: number;
    plan_name: string;
    trainer_name: string;
    starts_at: string;
    ends_at: string;
    status: string;
};

type Props = {
    active: Active;
    history: HistoryRow[];
};

const date = (value: string) => new Date(value).toLocaleDateString();

export default function Coaching({ active, history }: Props) {
    return (
        <MobileShell>
            <Head title="My coaching" />
            <div className="grid gap-5">
                <h1 className="text-xl font-bold">My coaching</h1>

                {active === null ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 py-10 text-center text-sm text-muted-foreground">
                            <Dumbbell className="size-6" />
                            No active coaching plan. Visit your coach's page
                            to subscribe.
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="grid gap-3 py-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-semibold">
                                        {active.plan_name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        with {active.trainer_name}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        active.due_soon
                                            ? 'destructive'
                                            : 'secondary'
                                    }
                                >
                                    {active.days_left}d left
                                </Badge>
                            </div>

                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <CalendarClock className="size-3.5" />
                                {date(active.starts_at)} →{' '}
                                {date(active.ends_at)}
                            </p>

                            {active.due_soon && (
                                <Button asChild size="sm" className="w-fit">
                                    <Link href={active.renew_url}>
                                        <RefreshCw />
                                        Renew plan
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}

                {history.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            History
                        </h2>
                        {history.map((row) => (
                            <div
                                key={row.id}
                                className="flex items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {row.plan_name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {row.trainer_name} ·{' '}
                                        {date(row.ends_at)}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        row.status === 'active'
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {row.status}
                                </Badge>
                            </div>
                        ))}
                    </section>
                )}
            </div>
        </MobileShell>
    );
}
