import { Head, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, ClipboardCheck } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes/trainer';
import { index, reply } from '@/routes/trainer/check-ins';

type Attention = {
    id: number;
    name: string;
    status: 'missing' | 'awaiting_reply' | 'done';
    check_in_id: number | null;
};

type Media = {
    id: number;
    url: string;
    kind: 'photo' | 'voice' | 'video';
};

type Review = {
    id: number;
    client_name: string;
    week_of: string;
    weight_kg: number | null;
    note: string | null;
    trainer_reply: string | null;
    needs_reply: boolean;
    media: Media[];
};

type Props = {
    attention: Attention[];
    review: Review[];
    week_of: string;
};

const STATUS_META: Record<Attention['status'], { label: string; variant: 'destructive' | 'secondary' | 'default' }> = {
    missing: { label: 'No check-in', variant: 'destructive' },
    awaiting_reply: { label: 'Awaiting reply', variant: 'secondary' },
    done: { label: 'Done', variant: 'default' },
};

function ReplyForm({ checkInId }: { checkInId: number }) {
    const [text, setText] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        router.post(
            reply(checkInId).url,
            { reply: text },
            {
                onFinish: () => setProcessing(false),
                onError: (errors) =>
                    setError((errors.reply as string) ?? null),
                onSuccess: () => setText(''),
            },
        );
    };

    return (
        <form onSubmit={submit} className="grid gap-2">
            <textarea
                rows={2}
                value={text}
                onChange={(event) => setText(event.target.value)}
                placeholder="Reply to this client…"
                className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError message={error ?? undefined} />
            <Button
                type="submit"
                size="sm"
                className="w-fit"
                disabled={processing || text.trim() === ''}
            >
                {processing && <Spinner />}
                Send reply
            </Button>
        </form>
    );
}

export default function TrainerCheckIns({ attention, review, week_of }: Props) {
    return (
        <>
            <Head title="Check-ins" />
            <div className="grid gap-6">
                <AdminPageHeader
                    title="Check-ins"
                    description={`Week of ${new Date(week_of).toLocaleDateString()} — who needs attention.`}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <AlertCircle className="size-4" />
                            Needs attention
                        </CardTitle>
                        <CardDescription>
                            Clients who missed this week's check-in or are
                            waiting on your reply.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {attention.length === 0 && (
                            <div className="flex flex-col items-center gap-2 py-8 text-center text-sm text-muted-foreground">
                                <ClipboardCheck className="size-6" />
                                No clients yet.
                            </div>
                        )}
                        {attention.map((row) => (
                            <div
                                key={row.id}
                                className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                            >
                                <p className="min-w-0 truncate font-medium">
                                    {row.name}
                                </p>
                                <Badge variant={STATUS_META[row.status].variant}>
                                    {STATUS_META[row.status].label}
                                </Badge>
                            </div>
                        ))}
                        {attention.every((row) => row.status === 'done') &&
                            attention.length > 0 && (
                                <p className="flex items-center gap-1.5 pt-2 text-xs text-muted-foreground">
                                    <CheckCircle2 className="size-4 text-green-500" />
                                    Everyone checked in and heard back. Nice.
                                </p>
                            )}
                    </CardContent>
                </Card>

                <section className="grid gap-3">
                    <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                        This week's check-ins
                    </h2>
                    {review.length === 0 && (
                        <Card>
                            <CardContent className="py-8 text-center text-sm text-muted-foreground">
                                Nothing submitted yet this week.
                            </CardContent>
                        </Card>
                    )}
                    {review.map((checkIn) => (
                        <Card key={checkIn.id}>
                            <CardHeader className="flex-row items-center justify-between gap-3 space-y-0">
                                <div>
                                    <CardTitle className="text-base">
                                        {checkIn.client_name}
                                    </CardTitle>
                                    {checkIn.weight_kg !== null && (
                                        <CardDescription>
                                            {checkIn.weight_kg} kg
                                        </CardDescription>
                                    )}
                                </div>
                                {!checkIn.needs_reply && (
                                    <Badge variant="default">Replied</Badge>
                                )}
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {checkIn.note && (
                                    <p className="text-sm whitespace-pre-line">
                                        {checkIn.note}
                                    </p>
                                )}
                                {checkIn.media.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {checkIn.media.map((item) =>
                                            item.kind === 'photo' ? (
                                                <img
                                                    key={item.id}
                                                    src={item.url}
                                                    alt=""
                                                    className="size-20 rounded-lg object-cover"
                                                />
                                            ) : item.kind === 'video' ? (
                                                <video
                                                    key={item.id}
                                                    src={item.url}
                                                    controls
                                                    className="h-28 rounded-lg"
                                                />
                                            ) : (
                                                <audio
                                                    key={item.id}
                                                    src={item.url}
                                                    controls
                                                />
                                            ),
                                        )}
                                    </div>
                                )}
                                {checkIn.trainer_reply ? (
                                    <p className="rounded-lg bg-muted/60 px-3 py-2 text-sm">
                                        <span className="font-medium">
                                            You:
                                        </span>{' '}
                                        {checkIn.trainer_reply}
                                    </p>
                                ) : (
                                    <ReplyForm checkInId={checkIn.id} />
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </section>
            </div>
        </>
    );
}

TrainerCheckIns.layout = {
    breadcrumbs: [
        { title: 'Coach', href: dashboard() },
        { title: 'Check-ins', href: index() },
    ],
};
