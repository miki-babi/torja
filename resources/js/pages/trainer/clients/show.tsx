import { Head, router } from '@inertiajs/react';
import {
    Dumbbell,
    Flame,
    MessageCircle,
    Send,
    UtensilsCrossed,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/trainer';
import { index as clientsIndex, message } from '@/routes/trainer/clients';
import { assign as assignProgram } from '@/routes/trainer/programs';
import { assign as assignMealProgram } from '@/routes/trainer/meal-programs';

type Client = {
    id: number;
    name: string;
    username: string | null;
    avatar: string | null;
    phone: string | null;
    joined: string | null;
    on_telegram: boolean;
    streak: number;
};

type Progress = {
    week_sessions: number;
    week_volume: number;
    sessions: { id: number; date: string; sets: number; volume: number }[];
    weight_logs: { date: string; weight_kg: number }[];
};

type AssignedPlan = { id: number; name: string; assigned: string };

type Props = {
    client: Client;
    progress: Progress;
    assigned: { programs: AssignedPlan[]; meal_programs: AssignedPlan[] };
    assignable: {
        programs: { id: number; name: string }[];
        meal_programs: { id: number; name: string }[];
    };
};

export default function TrainerClientShow({
    client,
    progress,
    assigned,
    assignable,
}: Props) {
    const [open, setOpen] = useState(false);
    const [picked, setPicked] = useState<Record<string, boolean>>({});
    const [sending, setSending] = useState(false);

    const assignedIds = new Set([
        ...assigned.programs.map((p) => `p-${p.id}`),
        ...assigned.meal_programs.map((p) => `m-${p.id}`),
    ]);

    const options = [
        ...assignable.programs.map((p) => ({
            key: `p-${p.id}`,
            id: p.id,
            name: p.name,
            assign: assignProgram,
        })),
        ...assignable.meal_programs.map((p) => ({
            key: `m-${p.id}`,
            id: p.id,
            name: p.name,
            assign: assignMealProgram,
        })),
    ];

    async function sendAssigns() {
        setSending(true);
        try {
            const chosen = options.filter((o) => picked[o.key]);
            for (const option of chosen) {
                const res = await memberApi<{
                    granted: number;
                    already_claimed: number;
                }>(option.assign(option.id).url, 'POST', {
                    client_ids: [client.id],
                });
                toast.success(
                    res.granted > 0
                        ? `Assigned "${option.name}"`
                        : `"${option.name}" was already assigned`,
                );
            }
            setOpen(false);
            setPicked({});
            router.reload({ only: ['assigned'] });
        } catch {
            toast.error('Could not assign plan');
        } finally {
            setSending(false);
        }
    }

    return (
        <>
            <Head title={client.name} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={client.name}
                    description={
                        client.username
                            ? `@${client.username} — client since ${client.joined ?? '—'}`
                            : `Client since ${client.joined ?? '—'}`
                    }
                    actions={
                        <>
                            <Dialog open={open} onOpenChange={setOpen}>
                                <DialogTrigger asChild>
                                    <Button size="sm">
                                        <Send className="size-4" />
                                        Assign plan
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Assign plan</DialogTitle>
                                        <DialogDescription>
                                            Pick plans to copy into{' '}
                                            {client.name}'s account. They'll be
                                            notified on Telegram.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid max-h-72 gap-2 overflow-y-auto">
                                        {options.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                Build a program or meal plan
                                                first.
                                            </p>
                                        )}
                                        {options.map((option) => (
                                            <Label
                                                key={option.key}
                                                className="flex items-center gap-3 rounded-md border p-2.5 text-sm"
                                            >
                                                <Checkbox
                                                    checked={
                                                        picked[option.key] ??
                                                        false
                                                    }
                                                    disabled={assignedIds.has(
                                                        option.key,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setPicked((map) => ({
                                                            ...map,
                                                            [option.key]:
                                                                checked ===
                                                                true,
                                                        }))
                                                    }
                                                />
                                                <span className="flex-1">
                                                    {option.name}
                                                </span>
                                                {assignedIds.has(
                                                    option.key,
                                                ) && (
                                                    <Badge variant="secondary">
                                                        assigned
                                                    </Badge>
                                                )}
                                            </Label>
                                        ))}
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            onClick={sendAssigns}
                                            disabled={
                                                sending ||
                                                !Object.values(picked).some(
                                                    Boolean,
                                                )
                                            }
                                        >
                                            Assign selected
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(message(client.id).url)
                                }
                            >
                                <MessageCircle className="size-4" />
                                Message
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardContent className="flex items-center gap-4 pt-6">
                            <Avatar className="size-14">
                                <AvatarImage
                                    src={client.avatar ?? undefined}
                                />
                                <AvatarFallback className="text-lg">
                                    {client.name.slice(0, 1)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="grid gap-1 text-sm">
                                <p className="font-medium">{client.name}</p>
                                <p className="flex items-center gap-1.5 text-muted-foreground">
                                    <Flame className="size-3.5 text-orange-500" />
                                    {client.streak}-day streak
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {client.on_telegram
                                        ? 'Telegram linked'
                                        : 'Not on Telegram'}
                                    {client.phone ? ` · ${client.phone}` : ''}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">
                                This week
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-1 text-sm">
                            <p>
                                <b>{progress.week_sessions}</b> sessions
                            </p>
                            <p className="text-muted-foreground">
                                {Math.round(
                                    progress.week_volume,
                                ).toLocaleString()}{' '}
                                kg volume
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">
                                Weight trend
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {progress.weight_logs.length === 0 ? (
                                <p className="text-muted-foreground">
                                    No check-ins yet.
                                </p>
                            ) : (
                                <p>
                                    Latest:{' '}
                                    <b>
                                        {
                                            progress.weight_logs[
                                                progress.weight_logs.length - 1
                                            ].weight_kg
                                        }
                                        kg
                                    </b>{' '}
                                    <span className="text-muted-foreground">
                                        (
                                        {
                                            progress.weight_logs[
                                                progress.weight_logs.length - 1
                                            ].date
                                        }
                                        )
                                    </span>
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Dumbbell className="size-4" /> Assigned
                                programs
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-1.5">
                            {assigned.programs.length === 0 && (
                                <CardDescription>
                                    Nothing assigned yet.
                                </CardDescription>
                            )}
                            {assigned.programs.map((plan) => (
                                <div
                                    key={plan.id}
                                    className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                                >
                                    <span>{plan.name}</span>
                                    <span className="text-xs text-muted-foreground">
                                        {plan.assigned}
                                    </span>
                                </div>
                            ))}
                            {assigned.meal_programs.map((plan) => (
                                <div
                                    key={`m-${plan.id}`}
                                    className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                                >
                                    <span className="flex items-center gap-2">
                                        <UtensilsCrossed className="size-3.5 text-muted-foreground" />
                                        {plan.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {plan.assigned}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Recent sessions
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-1.5">
                            {progress.sessions.length === 0 && (
                                <CardDescription>
                                    No workouts logged yet.
                                </CardDescription>
                            )}
                            {progress.sessions.map((session) => (
                                <div
                                    key={session.id}
                                    className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                                >
                                    <span>{session.date}</span>
                                    <span className="text-xs text-muted-foreground">
                                        {session.sets} sets ·{' '}
                                        {Math.round(
                                            session.volume,
                                        ).toLocaleString()}{' '}
                                        kg
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

TrainerClientShow.layout = {
    breadcrumbs: [
        { title: 'Coach', href: dashboard() },
        { title: 'Clients', href: clientsIndex() },
    ],
};
