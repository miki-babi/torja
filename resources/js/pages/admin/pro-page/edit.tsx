import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import {
    iconFor,
    type ProCopy,
    ProScreen,
} from '@/components/pro/pro-screen';
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
import { dashboard } from '@/routes/admin';
import { edit, reset, update } from '@/routes/admin/pro-page';

type Bilingual = { en: string; am: string };
type Locale = keyof Bilingual;

type FeatureRow = {
    icon: string;
    title: Bilingual;
    description: Bilingual;
    starter: boolean;
    pro: boolean;
};

type TrustBadge = { icon: string; text: Bilingual };

type TextKey =
    | 'badge'
    | 'headline'
    | 'headline_accent'
    | 'subheadline'
    | 'starter_label'
    | 'pro_label'
    | 'unlock_button'
    | 'sheet_title'
    | 'sheet_subtitle'
    | 'price_label'
    | 'price_pill'
    | 'sheet_button'
    | 'pro_title'
    | 'pro_body'
    | 'free_button'
    | 'free_notice'
    | 'card_title'
    | 'card_body'
    | 'card_button';

type Settings = Record<TextKey, Bilingual> & {
    price_etb: number | null;
    features: FeatureRow[];
    trust_badges: TrustBadge[];
};

type FormData = Omit<Settings, 'price_etb'> & { price_etb: string };

const TEXT_KEYS: TextKey[] = [
    'badge',
    'headline',
    'headline_accent',
    'subheadline',
    'starter_label',
    'pro_label',
    'unlock_button',
    'sheet_title',
    'sheet_subtitle',
    'price_label',
    'price_pill',
    'sheet_button',
    'pro_title',
    'pro_body',
    'free_button',
    'free_notice',
    'card_title',
    'card_body',
    'card_button',
];

function pick(field: Bilingual, locale: Locale, price: string): string {
    const value = field[locale].trim() !== '' ? field[locale] : field.en;
    return value.replaceAll('{price}', price);
}

function localize(data: FormData, locale: Locale, price: number): ProCopy {
    const formatted = price.toLocaleString();
    const copy = Object.fromEntries(
        TEXT_KEYS.map((key) => [key, pick(data[key], locale, formatted)]),
    ) as Record<TextKey, string>;

    return {
        ...copy,
        features: data.features.map((row) => ({
            icon: row.icon,
            title: pick(row.title, locale, formatted),
            description: pick(row.description, locale, formatted),
            starter: row.starter,
            pro: row.pro,
        })),
        trust_badges: data.trust_badges.map((badge) => ({
            icon: badge.icon,
            text: pick(badge.text, locale, formatted),
        })),
    };
}

function toForm(settings: Settings): FormData {
    return {
        ...settings,
        price_etb: settings.price_etb === null ? '' : String(settings.price_etb),
    };
}

function BilingualField({
    id,
    label,
    value,
    onChange,
    errors,
    hint,
}: {
    id: string;
    label: string;
    value: Bilingual;
    onChange: (next: Bilingual) => void;
    errors: Record<string, string>;
    hint?: string;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={`${id}-en`}>{label}</Label>
            <div className="grid gap-2 sm:grid-cols-2">
                <div>
                    <Input
                        id={`${id}-en`}
                        value={value.en}
                        placeholder="English"
                        onChange={(e) =>
                            onChange({ ...value, en: e.target.value })
                        }
                    />
                    <InputError message={errors[`${id}.en`]} />
                </div>
                <div>
                    <Input
                        id={`${id}-am`}
                        value={value.am}
                        placeholder="አማርኛ"
                        onChange={(e) =>
                            onChange({ ...value, am: e.target.value })
                        }
                    />
                    <InputError message={errors[`${id}.am`]} />
                </div>
            </div>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

function IconSelect({
    value,
    onChange,
    icons,
}: {
    value: string;
    onChange: (next: string) => void;
    icons: string[];
}) {
    const Current = iconFor(value);

    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger className="w-full">
                <span className="flex items-center gap-2">
                    <Current className="size-4" />
                    <SelectValue />
                </span>
            </SelectTrigger>
            <SelectContent>
                {icons.map((slug) => {
                    const Icon = iconFor(slug);
                    return (
                        <SelectItem key={slug} value={slug}>
                            <span className="flex items-center gap-2">
                                <Icon className="size-4" />
                                {slug}
                            </span>
                        </SelectItem>
                    );
                })}
            </SelectContent>
        </Select>
    );
}

export default function AdminProPageEdit({
    settings,
    defaults,
    envPrice,
    icons,
}: {
    settings: Settings;
    defaults: Settings;
    envPrice: number;
    icons: string[];
}) {
    const form = useForm<FormData>(toForm(settings));
    const { confirm, confirmDialog } = useConfirm();
    const [previewLocale, setPreviewLocale] = useState<Locale>('en');
    const [previewPro, setPreviewPro] = useState(false);

    const errors = form.errors as Record<string, string>;
    const resolvedPrice =
        form.data.price_etb.trim() === '' || Number.isNaN(Number(form.data.price_etb))
            ? envPrice
            : Number(form.data.price_etb);

    function setText(key: TextKey, next: Bilingual) {
        form.setData(key, next);
    }

    function setFeature(index: number, patch: Partial<FeatureRow>) {
        form.setData(
            'features',
            form.data.features.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        );
    }

    function moveFeature(index: number, delta: number) {
        const next = [...form.data.features];
        const target = index + delta;
        if (target < 0 || target >= next.length) {
            return;
        }
        [next[index], next[target]] = [next[target], next[index]];
        form.setData('features', next);
    }

    function removeFeature(index: number) {
        form.setData(
            'features',
            form.data.features.filter((_, i) => i !== index),
        );
    }

    function addFeature() {
        form.setData('features', [
            ...form.data.features,
            {
                icon: 'sparkles',
                title: { en: '', am: '' },
                description: { en: '', am: '' },
                starter: false,
                pro: true,
            },
        ]);
    }

    function setBadge(index: number, patch: Partial<TrustBadge>) {
        form.setData(
            'trust_badges',
            form.data.trust_badges.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        );
    }

    function removeBadge(index: number) {
        form.setData(
            'trust_badges',
            form.data.trust_badges.filter((_, i) => i !== index),
        );
    }

    function addBadge() {
        form.setData('trust_badges', [
            ...form.data.trust_badges,
            { icon: 'shield-check', text: { en: '', am: '' } },
        ]);
    }

    function save(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            price_etb: data.price_etb.trim() === '' ? null : Number(data.price_etb),
        }));
        form.put(update().url, { preserveScroll: true });
    }

    function resetToDefaults() {
        confirm({
            title: 'Reset the Pro page?',
            description:
                'All saved copy, feature rows and the price override will be replaced by the shipped defaults.',
            destructive: true,
            onConfirm: () =>
                router.delete(reset().url, {
                    preserveScroll: true,
                    onSuccess: () => form.setData(toForm(defaults)),
                }),
        });
    }

    const previewCopy = localize(form.data, previewLocale, resolvedPrice);

    return (
        <>
            <Head title="Admin — Pro page" />
            {confirmDialog}
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <AdminPageHeader
                    title="Pro page"
                    description="Copy, comparison table and price for the upgrade screen"
                    actions={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetToDefaults}
                            >
                                <RotateCcw className="size-4" />
                                Reset to defaults
                            </Button>
                            <Button
                                type="submit"
                                form="pro-page-form"
                                disabled={form.processing}
                            >
                                Save
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[1fr_420px]">
                    <form
                        id="pro-page-form"
                        onSubmit={save}
                        className="space-y-4"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Pricing</CardTitle>
                                <CardDescription>
                                    Leave empty to use PRO_PRICE_ETB from the
                                    environment ({envPrice.toLocaleString()} ETB).
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-1.5">
                                <Label htmlFor="price_etb">Price (ETB)</Label>
                                <Input
                                    id="price_etb"
                                    type="number"
                                    min={1}
                                    inputMode="numeric"
                                    className="max-w-xs"
                                    value={form.data.price_etb}
                                    placeholder={String(envPrice)}
                                    onChange={(e) =>
                                        form.setData('price_etb', e.target.value)
                                    }
                                />
                                <InputError message={errors.price_etb} />
                                <p className="text-xs text-muted-foreground">
                                    Use <code>{'{price}'}</code> in any text to
                                    insert the resolved price.
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Hero</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <BilingualField
                                    id="badge"
                                    label="Badge"
                                    value={form.data.badge}
                                    onChange={(v) => setText('badge', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="headline"
                                    label="Headline"
                                    value={form.data.headline}
                                    onChange={(v) => setText('headline', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="headline_accent"
                                    label="Headline accent (lime)"
                                    value={form.data.headline_accent}
                                    onChange={(v) => setText('headline_accent', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="subheadline"
                                    label="Subheadline"
                                    value={form.data.subheadline}
                                    onChange={(v) => setText('subheadline', v)}
                                    errors={errors}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Comparison table
                                </CardTitle>
                                <CardDescription>
                                    Up to 10 rows. Tick which tier includes each
                                    feature.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <BilingualField
                                        id="starter_label"
                                        label="Starter column label"
                                        value={form.data.starter_label}
                                        onChange={(v) => setText('starter_label', v)}
                                        errors={errors}
                                    />
                                    <BilingualField
                                        id="pro_label"
                                        label="Pro column label"
                                        value={form.data.pro_label}
                                        onChange={(v) => setText('pro_label', v)}
                                        errors={errors}
                                    />
                                </div>
                                <InputError message={errors.features} />

                                {form.data.features.map((row, index) => (
                                    <div
                                        key={index}
                                        className="space-y-3 rounded-lg border p-3"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-sm font-medium">
                                                Row {index + 1}
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    disabled={index === 0}
                                                    onClick={() => moveFeature(index, -1)}
                                                >
                                                    <ArrowUp className="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    disabled={index === form.data.features.length - 1}
                                                    onClick={() => moveFeature(index, 1)}
                                                >
                                                    <ArrowDown className="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    disabled={form.data.features.length === 1}
                                                    onClick={() => removeFeature(index)}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-[180px_1fr]">
                                            <div className="grid gap-1.5">
                                                <Label>Icon</Label>
                                                <IconSelect
                                                    value={row.icon}
                                                    icons={icons}
                                                    onChange={(icon) => setFeature(index, { icon })}
                                                />
                                                <InputError message={errors[`features.${index}.icon`]} />
                                            </div>
                                            <div className="flex items-end gap-6 pb-1">
                                                <label className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={row.starter}
                                                        onCheckedChange={(checked) =>
                                                            setFeature(index, { starter: checked === true })
                                                        }
                                                    />
                                                    Starter
                                                </label>
                                                <label className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={row.pro}
                                                        onCheckedChange={(checked) =>
                                                            setFeature(index, { pro: checked === true })
                                                        }
                                                    />
                                                    Pro
                                                </label>
                                            </div>
                                        </div>
                                        <BilingualField
                                            id={`features.${index}.title`}
                                            label="Title"
                                            value={row.title}
                                            onChange={(title) => setFeature(index, { title })}
                                            errors={errors}
                                        />
                                        <BilingualField
                                            id={`features.${index}.description`}
                                            label="Description"
                                            value={row.description}
                                            onChange={(description) => setFeature(index, { description })}
                                            errors={errors}
                                        />
                                    </div>
                                ))}

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={form.data.features.length >= 10}
                                    onClick={addFeature}
                                >
                                    <Plus className="size-4" />
                                    Add row
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Bottom buttons</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <BilingualField
                                    id="unlock_button"
                                    label="Unlock button"
                                    value={form.data.unlock_button}
                                    onChange={(v) => setText('unlock_button', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="free_button"
                                    label='"Continue with free" button'
                                    value={form.data.free_button}
                                    onChange={(v) => setText('free_button', v)}
                                    errors={errors}
                                    hint="Outlined button under the unlock button; dismisses the screen."
                                />
                                <BilingualField
                                    id="free_notice"
                                    label='Toast on "Continue with free"'
                                    value={form.data.free_notice}
                                    onChange={(v) => setText('free_notice', v)}
                                    errors={errors}
                                    hint="Shown as a toast when a member dismisses the screen with the free button."
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Payment sheet</CardTitle>
                                <CardDescription>
                                    The bottom sheet that opens from the unlock
                                    button. Up to 3 trust badges.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <BilingualField
                                    id="sheet_title"
                                    label="Title (also the browser tab)"
                                    value={form.data.sheet_title}
                                    onChange={(v) => setText('sheet_title', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="sheet_subtitle"
                                    label="Subtitle"
                                    value={form.data.sheet_subtitle}
                                    onChange={(v) => setText('sheet_subtitle', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="price_pill"
                                    label="Price pill (e.g. Lifetime)"
                                    value={form.data.price_pill}
                                    onChange={(v) => setText('price_pill', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="price_label"
                                    label="Price card label"
                                    value={form.data.price_label}
                                    onChange={(v) => setText('price_label', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="sheet_button"
                                    label="Continue button"
                                    value={form.data.sheet_button}
                                    onChange={(v) => setText('sheet_button', v)}
                                    errors={errors}
                                    hint="Shown when exactly one payment provider is configured; otherwise one button per provider appears."
                                />
                                <InputError message={errors.trust_badges} />
                                {form.data.trust_badges.map((badge, index) => (
                                    <div
                                        key={index}
                                        className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[180px_1fr_auto]"
                                    >
                                        <div className="grid gap-1.5">
                                            <Label>Icon</Label>
                                            <IconSelect
                                                value={badge.icon}
                                                icons={icons}
                                                onChange={(icon) => setBadge(index, { icon })}
                                            />
                                        </div>
                                        <BilingualField
                                            id={`trust_badges.${index}.text`}
                                            label="Text"
                                            value={badge.text}
                                            onChange={(text) => setBadge(index, { text })}
                                            errors={errors}
                                        />
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="self-end"
                                            onClick={() => removeBadge(index)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={form.data.trust_badges.length >= 3}
                                    onClick={addBadge}
                                >
                                    <Plus className="size-4" />
                                    Add badge
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Profile card</CardTitle>
                                <CardDescription>
                                    The upgrade card shown on the member
                                    profile for free members. Pro members see
                                    the "Pro member state" copy below instead.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <BilingualField
                                    id="card_title"
                                    label="Title"
                                    value={form.data.card_title}
                                    onChange={(v) => setText('card_title', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="card_body"
                                    label="Body"
                                    value={form.data.card_body}
                                    onChange={(v) => setText('card_body', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="card_button"
                                    label="Button"
                                    value={form.data.card_button}
                                    onChange={(v) => setText('card_button', v)}
                                    errors={errors}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Pro member state
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <BilingualField
                                    id="pro_title"
                                    label="Pro member title"
                                    value={form.data.pro_title}
                                    onChange={(v) => setText('pro_title', v)}
                                    errors={errors}
                                />
                                <BilingualField
                                    id="pro_body"
                                    label="Pro member body"
                                    value={form.data.pro_body}
                                    onChange={(v) => setText('pro_body', v)}
                                    errors={errors}
                                />
                            </CardContent>
                        </Card>
                    </form>

                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <span className="text-sm font-medium">Live preview</span>
                            <div className="flex items-center gap-1">
                                {(['en', 'am'] as const).map((locale) => (
                                    <Button
                                        key={locale}
                                        type="button"
                                        size="sm"
                                        variant={previewLocale === locale ? 'default' : 'outline'}
                                        onClick={() => setPreviewLocale(locale)}
                                    >
                                        {locale.toUpperCase()}
                                    </Button>
                                ))}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={previewPro ? 'default' : 'outline'}
                                    onClick={() => setPreviewPro((v) => !v)}
                                >
                                    {previewPro ? 'Pro member' : 'Free member'}
                                </Button>
                            </div>
                        </div>
                        <div className="mx-auto w-[390px] max-w-full rounded-[2rem] border-4 border-[#2E2E2E] bg-black p-4">
                            <div className="dark max-h-[75vh] overflow-y-auto rounded-[1.5rem] bg-black p-3">
                                <ProScreen
                                    copy={previewCopy}
                                    isPro={previewPro}
                                    preview
                                />
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}

AdminProPageEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Pro page', href: edit() },
    ],
};
