import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ImagePlus,
    Loader2,
    Plus,
    Trash2,
} from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import {
    BannerCarousel,
    type HomeBanner,
} from '@/components/member/banner-carousel';
import { xsrfToken } from '@/components/member/member-api';
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
import { reset, update, uploadImage } from '@/routes/admin/home-banners';

type BannerRow = { image: string; url: string; cta: string };
type FormData = { banners: BannerRow[] };

const MAX_BANNERS = 6;

export default function AdminHomeBannersEdit({
    banners,
    resolved,
}: {
    banners: BannerRow[];
    resolved: HomeBanner[];
}) {
    const form = useForm<FormData>({ banners });
    const { confirm, confirmDialog } = useConfirm();
    const [uploading, setUploading] = useState<number | null>(null);
    const [previews, setPreviews] = useState<Record<number, string>>(() =>
        Object.fromEntries(resolved.map((row, i) => [i, row.image])),
    );
    const fileRefs = useRef<Record<number, HTMLInputElement | null>>({});

    const errors = form.errors as Record<string, string>;

    function setBanner(index: number, patch: Partial<BannerRow>) {
        form.setData(
            'banners',
            form.data.banners.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        );
    }

    function moveBanner(index: number, delta: number) {
        const next = [...form.data.banners];
        const target = index + delta;
        if (target < 0 || target >= next.length) {
            return;
        }
        [next[index], next[target]] = [next[target], next[index]];
        form.setData('banners', next);
        setPreviews((current) => {
            const copy = { ...current };
            copy[index] = current[target] ?? '';
            copy[target] = current[index] ?? '';
            return copy;
        });
    }

    function removeBanner(index: number) {
        form.setData(
            'banners',
            form.data.banners.filter((_, i) => i !== index),
        );
        setPreviews((current) => {
            const copy: Record<number, string> = {};
            for (const [key, value] of Object.entries(current)) {
                const i = Number(key);
                if (i < index) copy[i] = value;
                if (i > index) copy[i - 1] = value;
            }
            return copy;
        });
    }

    function addBanner() {
        form.setData('banners', [
            ...form.data.banners,
            { image: '', url: '', cta: '' },
        ]);
    }

    async function upload(index: number, file: File) {
        const body = new FormData();
        body.append('image', file);
        setUploading(index);

        try {
            const response = await fetch(uploadImage().url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-XSRF-TOKEN': xsrfToken() },
                body,
            });
            if (!response.ok) {
                return;
            }
            const { path, url } = (await response.json()) as {
                path: string;
                url: string;
            };
            setBanner(index, { image: path });
            setPreviews((current) => ({ ...current, [index]: url }));
        } finally {
            setUploading(null);
        }
    }

    function previewImage(row: BannerRow, index: number): string {
        if (row.image.startsWith('http') || row.image.startsWith('/')) {
            return row.image;
        }
        return previews[index] ?? '';
    }

    function save(e: FormEvent) {
        e.preventDefault();
        form.put(update().url, { preserveScroll: true });
    }

    function clearAll() {
        confirm({
            title: 'Clear all home banners?',
            description:
                'The ad carousel will disappear from the member home screen.',
            destructive: true,
            onConfirm: () =>
                router.delete(reset().url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.setData('banners', []);
                        setPreviews({});
                    },
                }),
        });
    }

    const previewBanners: HomeBanner[] = form.data.banners
        .map((row, index) => ({ row, index }))
        .filter(({ row }) => row.image.trim() !== '' && row.url.trim() !== '')
        .map(({ row, index }) => ({
            image: previewImage(row, index),
            url: row.url,
            cta: row.cta,
            external: row.url.startsWith('http'),
        }));

    return (
        <>
            <Head title="Admin — Home banners" />
            {confirmDialog}
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <AdminPageHeader
                    title="Home banners"
                    description="Ad carousel on the member home screen, between the quick actions and the meal card"
                    actions={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.data.banners.length === 0}
                                onClick={clearAll}
                            >
                                <Trash2 className="size-4" />
                                Clear all
                            </Button>
                            <Button
                                type="submit"
                                form="home-banners-form"
                                disabled={form.processing}
                            >
                                Save
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[1fr_420px]">
                    <form
                        id="home-banners-form"
                        onSubmit={save}
                        className="space-y-4"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Banners
                                </CardTitle>
                                <CardDescription>
                                    Up to {MAX_BANNERS} slides. Links starting
                                    with / open inside the app; http(s) links
                                    open in a new tab. Remove every banner to
                                    hide the carousel.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <InputError message={errors.banners} />

                                {form.data.banners.map((row, index) => (
                                    <div
                                        key={index}
                                        className="space-y-3 rounded-lg border p-3"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-sm font-medium">
                                                Banner {index + 1}
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    disabled={index === 0}
                                                    onClick={() =>
                                                        moveBanner(index, -1)
                                                    }
                                                >
                                                    <ArrowUp className="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    disabled={
                                                        index ===
                                                        form.data.banners
                                                            .length -
                                                            1
                                                    }
                                                    onClick={() =>
                                                        moveBanner(index, 1)
                                                    }
                                                >
                                                    <ArrowDown className="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        removeBanner(index)
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label>Image</Label>
                                            {previewImage(row, index) !==
                                                '' && (
                                                <img
                                                    src={previewImage(
                                                        row,
                                                        index,
                                                    )}
                                                    alt=""
                                                    className="aspect-[16/7] w-full max-w-xs rounded-md border object-cover"
                                                />
                                            )}
                                            <div className="flex flex-wrap items-center gap-2">
                                                <input
                                                    ref={(el) => {
                                                        fileRefs.current[
                                                            index
                                                        ] = el;
                                                    }}
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                                    className="hidden"
                                                    onChange={(e) => {
                                                        const file =
                                                            e.target.files?.[0];
                                                        if (file) {
                                                            void upload(
                                                                index,
                                                                file,
                                                            );
                                                        }
                                                        e.target.value = '';
                                                    }}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={
                                                        uploading !== null
                                                    }
                                                    onClick={() =>
                                                        fileRefs.current[
                                                            index
                                                        ]?.click()
                                                    }
                                                >
                                                    {uploading === index ? (
                                                        <Loader2 className="size-4 animate-spin" />
                                                    ) : (
                                                        <ImagePlus className="size-4" />
                                                    )}
                                                    Upload image
                                                </Button>
                                                <Input
                                                    className="min-w-52 flex-1"
                                                    placeholder="…or paste an image URL"
                                                    value={row.image}
                                                    onChange={(e) =>
                                                        setBanner(index, {
                                                            image: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <InputError
                                                message={
                                                    errors[
                                                        `banners.${index}.image`
                                                    ]
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label>Link</Label>
                                            <Input
                                                placeholder="/pro or https://example.com"
                                                value={row.url}
                                                onChange={(e) =>
                                                    setBanner(index, {
                                                        url: e.target.value,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `banners.${index}.url`
                                                    ]
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label>CTA label</Label>
                                            <Input
                                                placeholder="Learn more"
                                                value={row.cta}
                                                onChange={(e) =>
                                                    setBanner(index, {
                                                        cta: e.target.value,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `banners.${index}.cta`
                                                    ]
                                                }
                                            />
                                        </div>
                                    </div>
                                ))}

                                {form.data.banners.length < MAX_BANNERS && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addBanner}
                                    >
                                        <Plus className="size-4" /> Add banner (
                                        {form.data.banners.length}/{MAX_BANNERS}
                                        )
                                    </Button>
                                )}

                                {form.data.banners.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No banners — the carousel is hidden on
                                        the home screen.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </form>

                    <div className="space-y-3 lg:sticky lg:top-4 lg:self-start">
                        <p className="text-sm font-medium text-muted-foreground">
                            Preview
                        </p>
                        <div className="mx-auto w-full max-w-md overflow-hidden">
                            {previewBanners.length > 0 ? (
                                <BannerCarousel banners={previewBanners} />
                            ) : (
                                <div className="grid aspect-[32/7] place-items-center rounded-[24px] border border-dashed text-sm text-muted-foreground">
                                    Nothing to preview
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
