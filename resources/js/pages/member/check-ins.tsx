import { Head, useForm } from '@inertiajs/react';
import { Camera, CheckCircle2, MessageSquareReply } from 'lucide-react';
import { FormEvent, useRef } from 'react';
import { MobileShell } from '@/components/member/mobile-shell';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/member/check-ins';

type Media = {
    id: number;
    url: string;
    kind: 'photo' | 'voice' | 'video';
};

type CheckIn = {
    id: number;
    week_of: string;
    weight_kg: number | null;
    note: string | null;
    trainer_reply: string | null;
    replied_at: string | null;
    media: Media[];
};

type Props = {
    current: CheckIn | null;
    history: CheckIn[];
    week_of: string;
};

function MediaStrip({ media }: { media: Media[] }) {
    if (media.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {media.map((item) =>
                item.kind === 'photo' ? (
                    <img
                        key={item.id}
                        src={item.url}
                        alt=""
                        className="size-16 rounded-lg object-cover"
                    />
                ) : item.kind === 'video' ? (
                    <video
                        key={item.id}
                        src={item.url}
                        controls
                        className="h-24 rounded-lg"
                    />
                ) : (
                    <audio key={item.id} src={item.url} controls />
                ),
            )}
        </div>
    );
}

export default function CheckIns({ current, history, week_of }: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors } = useForm<{
        weight_kg: string;
        note: string;
        media: File[];
    }>({
        weight_kg: current?.weight_kg?.toString() ?? '',
        note: current?.note ?? '',
        media: [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(store().url, { forceFormData: true });
    };

    return (
        <MobileShell>
            <Head title="Weekly check-in" />
            <div className="grid gap-5">
                <div>
                    <h1 className="text-xl font-bold">Weekly check-in</h1>
                    <p className="text-sm text-muted-foreground">
                        Week of {new Date(week_of).toLocaleDateString()}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            {current !== null && (
                                <CheckCircle2 className="size-4 text-green-500" />
                            )}
                            {current !== null
                                ? 'This week’s check-in'
                                : 'Check in with your coach'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="weight_kg">
                                    Weight (kg)
                                </Label>
                                <Input
                                    id="weight_kg"
                                    type="number"
                                    step="0.1"
                                    value={data.weight_kg}
                                    onChange={(event) =>
                                        setData(
                                            'weight_kg',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="75.5"
                                />
                                <InputError message={errors.weight_kg} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="note">
                                    How was your week?
                                </Label>
                                <textarea
                                    id="note"
                                    rows={3}
                                    value={data.note}
                                    onChange={(event) =>
                                        setData('note', event.target.value)
                                    }
                                    placeholder="Energy, sleep, sticking to the plan, anything your coach should know…"
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={errors.note} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label>Photos / voice note</Label>
                                <input
                                    ref={fileInput}
                                    type="file"
                                    multiple
                                    accept="image/*,audio/*,video/*"
                                    className="hidden"
                                    onChange={(event) =>
                                        setData(
                                            'media',
                                            Array.from(
                                                event.target.files ?? [],
                                            ),
                                        )
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-fit"
                                    onClick={() => fileInput.current?.click()}
                                >
                                    <Camera />
                                    {data.media.length > 0
                                        ? `${data.media.length} file${data.media.length === 1 ? '' : 's'} attached`
                                        : 'Attach media'}
                                </Button>
                                <InputError message={errors.media} />
                            </div>

                            {current !== null &&
                                current.media.length > 0 && (
                                    <MediaStrip media={current.media} />
                                )}

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {current !== null
                                    ? 'Update check-in'
                                    : 'Send check-in'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {current?.trainer_reply && (
                    <Card className="border-primary/40">
                        <CardContent className="flex gap-3 py-4">
                            <MessageSquareReply className="size-5 shrink-0 text-primary" />
                            <div className="min-w-0">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Coach replied
                                </p>
                                <p className="mt-1 text-sm whitespace-pre-line">
                                    {current.trainer_reply}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {history.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Past weeks
                        </h2>
                        {history.map((checkIn) => (
                            <Card key={checkIn.id}>
                                <CardContent className="grid gap-2 py-4">
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-medium">
                                            Week of{' '}
                                            {new Date(
                                                checkIn.week_of,
                                            ).toLocaleDateString()}
                                        </p>
                                        {checkIn.weight_kg !== null && (
                                            <Badge variant="secondary">
                                                {checkIn.weight_kg} kg
                                            </Badge>
                                        )}
                                    </div>
                                    {checkIn.note && (
                                        <p className="text-sm text-muted-foreground">
                                            {checkIn.note}
                                        </p>
                                    )}
                                    <MediaStrip media={checkIn.media} />
                                    {checkIn.trainer_reply && (
                                        <p className="rounded-lg bg-muted/60 px-3 py-2 text-sm">
                                            <span className="font-medium">
                                                Coach:
                                            </span>{' '}
                                            {checkIn.trainer_reply}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </section>
                )}

            </div>
        </MobileShell>
    );
}
