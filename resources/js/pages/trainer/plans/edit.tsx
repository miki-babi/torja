import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes/trainer';
import { index, store, update } from '@/routes/trainer/plans';

type Plan = {
    id: number;
    name: string;
    description: string | null;
    kind: string;
    price_etb: number;
    duration_days: number;
    is_active: boolean;
};

type Props = {
    plan: Plan | null;
    kinds: Record<string, string>;
};

export default function TrainerPlanEdit({ plan, kinds }: Props) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: plan?.name ?? '',
        description: plan?.description ?? '',
        kind: plan?.kind ?? 'one_on_one',
        price_etb: plan?.price_etb ?? '',
        duration_days: plan?.duration_days ?? 30,
        is_active: plan?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (plan) {
            put(update(plan.id).url);
        } else {
            post(store().url);
        }
    };

    return (
        <>
            <Head title={plan ? 'Edit plan' : 'New plan'} />
            <div className="grid gap-6">
                <AdminPageHeader
                    title={plan ? 'Edit plan' : 'New coaching plan'}
                    description="Shown on your public page — clients pay through the app to subscribe."
                />

                <form onSubmit={submit} className="grid gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Plan details
                            </CardTitle>
                            <CardDescription>
                                What clients see on your public page and the
                                checkout screen.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    placeholder="e.g. Monthly 1:1 coaching"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="description">
                                    Description
                                </Label>
                                <textarea
                                    id="description"
                                    rows={3}
                                    value={data.description}
                                    onChange={(event) =>
                                        setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="What's included — check-in cadence, program access, chat…"
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label>Type</Label>
                                <Select
                                    value={data.kind}
                                    onValueChange={(value) =>
                                        setData('kind', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(kinds).map(
                                            ([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.kind} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="price_etb">
                                        Price (ETB)
                                    </Label>
                                    <Input
                                        id="price_etb"
                                        type="number"
                                        min={1}
                                        value={data.price_etb}
                                        onChange={(event) =>
                                            setData(
                                                'price_etb',
                                                event.target.value === ''
                                                    ? ''
                                                    : Number(
                                                          event.target.value,
                                                      ),
                                            )
                                        }
                                        placeholder="2000"
                                    />
                                    <InputError message={errors.price_etb} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="duration_days">
                                        Duration (days)
                                    </Label>
                                    <Input
                                        id="duration_days"
                                        type="number"
                                        min={1}
                                        value={data.duration_days}
                                        onChange={(event) =>
                                            setData(
                                                'duration_days',
                                                event.target.value === ''
                                                    ? ''
                                                    : Number(
                                                          event.target.value,
                                                      ),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.duration_days}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-fit"
                    >
                        {processing && <Spinner />}
                        {plan ? 'Save changes' : 'Create plan'}
                    </Button>
                </form>
            </div>
        </>
    );
}

TrainerPlanEdit.layout = {
    breadcrumbs: [
        { title: 'Coach', href: dashboard() },
        { title: 'Coaching plans', href: index() },
    ],
};
