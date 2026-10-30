import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/ambassadors';
import { update } from '@/routes/admin/ambassadors/settings';

type Settings = {
    enabled: boolean;
    commission_etb: string;
    min_payout_etb: string;
};

export default function AdminAmbassadorSettings({
    settings,
    defaults,
}: {
    settings: {
        enabled: boolean;
        commission_etb: number;
        min_payout_etb: number;
    };
    defaults: {
        enabled: boolean;
        commission_etb: number;
        min_payout_etb: number;
    };
}) {
    const form = useForm<Settings>({
        enabled: settings.enabled,
        commission_etb: String(settings.commission_etb),
        min_payout_etb: String(settings.min_payout_etb),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            commission_etb: Number(data.commission_etb),
            min_payout_etb: Number(data.min_payout_etb),
        }));
        form.put(update().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Admin — ambassador settings" />
            <div className="flex flex-1 flex-col gap-4 p-4 lg:p-6">
                <AdminPageHeader
                    title="Ambassador settings"
                    description={`Defaults: ${defaults.commission_etb} ETB commission, ${defaults.min_payout_etb} ETB minimum payout.`}
                />

                <Card className="max-w-xl">
                    <CardHeader>
                        <CardTitle className="text-base">Program</CardTitle>
                        <CardDescription>
                            How much ambassadors earn per referred Pro purchase
                            and when they can cash out.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="enabled"
                                    checked={form.data.enabled}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'enabled',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="enabled">
                                    Program enabled — new commissions are
                                    created on Pro purchases
                                </Label>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="commission_etb">
                                    Commission per referral (ETB)
                                </Label>
                                <Input
                                    id="commission_etb"
                                    type="number"
                                    min={1}
                                    value={form.data.commission_etb}
                                    onChange={(e) =>
                                        form.setData(
                                            'commission_etb',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.commission_etb}
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="min_payout_etb">
                                    Minimum payout (ETB)
                                </Label>
                                <Input
                                    id="min_payout_etb"
                                    type="number"
                                    min={1}
                                    value={form.data.min_payout_etb}
                                    onChange={(e) =>
                                        form.setData(
                                            'min_payout_etb',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.min_payout_etb}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="w-fit"
                                disabled={form.processing}
                            >
                                Save settings
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminAmbassadorSettings.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Ambassadors', href: index() },
        { title: 'Settings', href: '#' },
    ],
};
