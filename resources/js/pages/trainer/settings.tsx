import { Head, router, useForm } from '@inertiajs/react';
import { BellRing, Languages, MoonStar } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { update as localeUpdate } from '@/routes/locale';
import { dashboard } from '@/routes/trainer';
import { update } from '@/routes/trainer/settings';

type Props = {
    locale: string;
    reminders: {
        enabled: boolean;
        quiet_hours_start: string | null;
        quiet_hours_end: string | null;
    };
};

export default function TrainerSettings({ reminders }: Props) {
    const { t, locale, languages } = useTranslation();
    const { data, setData, patch, processing, errors } = useForm({
        reminders_enabled: reminders.enabled,
        quiet_hours_start: reminders.quiet_hours_start ?? '',
        quiet_hours_end: reminders.quiet_hours_end ?? '',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        patch(update().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Settings" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Settings"
                    description="Your language and how your bot reminds clients."
                />

                <div className="grid max-w-3xl gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Languages className="size-4" />
                                {t('settings.language', 'Language')}
                            </CardTitle>
                            <CardDescription>
                                The language your dashboard uses.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Select
                                value={locale}
                                onValueChange={(value) =>
                                    router.patch(
                                        localeUpdate().url,
                                        { locale: value },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <SelectTrigger className="w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {languages.map((language) => (
                                        <SelectItem
                                            key={language.code}
                                            value={language.code}
                                        >
                                            {language.native_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </CardContent>
                    </Card>

                    <form onSubmit={submit} className="grid gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <BellRing className="size-4" />
                                    Client reminders
                                </CardTitle>
                                <CardDescription>
                                    Daily Telegram nudges your bot sends to
                                    clients — missed workouts, check-ins and
                                    expiring plans.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <label
                                    htmlFor="reminders_enabled"
                                    className="flex cursor-pointer items-center gap-3"
                                >
                                    <Checkbox
                                        id="reminders_enabled"
                                        checked={data.reminders_enabled}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'reminders_enabled',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <span className="text-sm">
                                        Send reminders to my clients
                                    </span>
                                </label>
                                <InputError
                                    message={errors.reminders_enabled}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <MoonStar className="size-4" />
                                    Quiet hours
                                </CardTitle>
                                <CardDescription>
                                    No reminder DMs go out during this window —
                                    it can wrap midnight (e.g. 21:00 → 07:00).
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="quiet_hours_start">
                                        From
                                    </Label>
                                    <Input
                                        id="quiet_hours_start"
                                        type="time"
                                        value={data.quiet_hours_start}
                                        onChange={(event) =>
                                            setData(
                                                'quiet_hours_start',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.quiet_hours_start}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="quiet_hours_end">To</Label>
                                    <Input
                                        id="quiet_hours_end"
                                        type="time"
                                        value={data.quiet_hours_end}
                                        onChange={(event) =>
                                            setData(
                                                'quiet_hours_end',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.quiet_hours_end}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <div>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Save settings
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

TrainerSettings.layout = {
    breadcrumbs: [{ title: 'Coach', href: dashboard() }],
};
