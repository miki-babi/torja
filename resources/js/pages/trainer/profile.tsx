import { Head, useForm } from '@inertiajs/react';
import { Copy, ExternalLink } from 'lucide-react';
import { toast } from 'sonner';
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
import { useClipboard } from '@/hooks/use-clipboard';
import { dashboard } from '@/routes/trainer';
import { update } from '@/routes/trainer/profile';

type Props = {
    trainer: {
        display_name: string;
        bio: string | null;
        specialty: string | null;
        languages: string[];
        years_experience: number | null;
        has_certification: boolean;
        payout_method: 'telebirr' | 'bank' | null;
        payout_account: string | null;
        offer_text: string | null;
        page_url: string;
    };
};

export default function TrainerProfile({ trainer }: Props) {
    const [, copy] = useClipboard();
    const { data, setData, patch, processing, errors, transform } = useForm({
        bio: trainer.bio ?? '',
        specialty: trainer.specialty ?? '',
        languages_text: trainer.languages.join(', '),
        years_experience: trainer.years_experience ?? '',
        payout_method: trainer.payout_method ?? '',
        payout_account: trainer.payout_account ?? '',
        offer_text: trainer.offer_text ?? '',
        certification: null as File | null,
    });

    transform((values) => ({
        bio: values.bio || null,
        specialty: values.specialty || null,
        languages: values.languages_text
            .split(',')
            .map((language) => language.trim())
            .filter((language) => language !== ''),
        years_experience:
            values.years_experience === '' ? null : values.years_experience,
        payout_method: values.payout_method || null,
        payout_account: values.payout_account || null,
        offer_text: values.offer_text || null,
        certification: values.certification,
    }));

    return (
        <>
            <Head title="Coach profile" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Coach profile"
                    description="What clients see on your public page, plus where we send your payouts."
                />

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <div>
                            <CardTitle className="text-base">
                                Your public page
                            </CardTitle>
                            <CardDescription>
                                Share this link — it lists your profile and
                                programs.
                            </CardDescription>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={trainer.page_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink className="size-4" />
                                Open
                            </a>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex items-center gap-2">
                        <code className="flex-1 truncate rounded-md bg-muted px-3 py-2 text-xs">
                            {trainer.page_url}
                        </code>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={async () => {
                                if (await copy(trainer.page_url)) {
                                    toast.success('Link copied');
                                }
                            }}
                        >
                            <Copy className="size-4" />
                        </Button>
                    </CardContent>
                </Card>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        patch(update().url, { forceFormData: true });
                    }}
                    className="grid gap-4 lg:grid-cols-2"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Public profile
                            </CardTitle>
                            <CardDescription>
                                Shown on your page at the top of your programs.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="specialty">Specialty</Label>
                                <Input
                                    id="specialty"
                                    value={data.specialty}
                                    onChange={(event) =>
                                        setData('specialty', event.target.value)
                                    }
                                    placeholder="e.g. Strength & fat loss"
                                />
                                <InputError message={errors.specialty} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="bio">Bio</Label>
                                <textarea
                                    id="bio"
                                    rows={4}
                                    value={data.bio}
                                    onChange={(event) =>
                                        setData('bio', event.target.value)
                                    }
                                    placeholder="Tell clients who you are and how you coach."
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={errors.bio} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="languages_text">
                                    Languages{' '}
                                    <span className="text-muted-foreground">
                                        (comma separated)
                                    </span>
                                </Label>
                                <Input
                                    id="languages_text"
                                    value={data.languages_text}
                                    onChange={(event) =>
                                        setData(
                                            'languages_text',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Amharic, English"
                                />
                                <InputError message={errors.languages} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="years_experience">
                                    Years of experience
                                </Label>
                                <Input
                                    id="years_experience"
                                    type="number"
                                    min={0}
                                    max={60}
                                    value={data.years_experience}
                                    onChange={(event) =>
                                        setData(
                                            'years_experience',
                                            event.target.value === ''
                                                ? ''
                                                : Number(event.target.value),
                                        )
                                    }
                                />
                                <InputError message={errors.years_experience} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="certification">
                                    Certification{' '}
                                    <span className="text-muted-foreground">
                                        (optional, image or PDF)
                                    </span>
                                </Label>
                                {trainer.has_certification && (
                                    <p className="text-xs text-lime-600 dark:text-lime-400">
                                        A certification is on file — upload a
                                        new one to replace it.
                                    </p>
                                )}
                                <Input
                                    id="certification"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    onChange={(event) =>
                                        setData(
                                            'certification',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={errors.certification} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Payout details
                                </CardTitle>
                                <CardDescription>
                                    Where the platform sends your weekly
                                    payout.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="payout_method">
                                        Payout method
                                    </Label>
                                    <Select
                                        value={data.payout_method}
                                        onValueChange={(value) =>
                                            setData('payout_method', value)
                                        }
                                    >
                                        <SelectTrigger id="payout_method">
                                            <SelectValue placeholder="Choose a method" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="telebirr">
                                                Telebirr
                                            </SelectItem>
                                            <SelectItem value="bank">
                                                Bank account
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.payout_method} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="payout_account">
                                        {data.payout_method === 'bank'
                                            ? 'Account number'
                                            : 'Telebirr phone number'}
                                    </Label>
                                    <Input
                                        id="payout_account"
                                        value={data.payout_account}
                                        onChange={(event) =>
                                            setData(
                                                'payout_account',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={
                                            data.payout_method === 'bank'
                                                ? '1000xxxxxxx'
                                                : '09xxxxxxxx'
                                        }
                                    />
                                    <InputError
                                        message={errors.payout_account}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Continue-with-me offer
                                </CardTitle>
                                <CardDescription>
                                    Shown to clients near the end of your free
                                    program — invite them onto a paid plan.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-1.5">
                                <textarea
                                    rows={3}
                                    value={data.offer_text}
                                    onChange={(event) =>
                                        setData(
                                            'offer_text',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. Keep the momentum — train with me 1:1 for 600 birr/month."
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={errors.offer_text} />
                            </CardContent>
                        </Card>

                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-fit"
                        >
                            {processing && <Spinner />}
                            Save profile
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

TrainerProfile.layout = {
    breadcrumbs: [
        { title: 'Coach', href: dashboard() },
        { title: 'Profile', href: '/trainer/profile' },
    ],
};
