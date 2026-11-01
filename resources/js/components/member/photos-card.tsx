import { Camera, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { xsrfToken } from '@/components/member/member-api';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';
import { destroy, store } from '@/routes/member/progress-photos';

export type ProgressPhotoEntry = {
    id: number;
    url: string;
    taken_on: string;
};

/**
 * Progress-photo gallery: upload to the public disk, grid with date
 * captions, per-photo delete.
 */
export function PhotosCard({
    photos,
    onChange,
}: {
    photos: ProgressPhotoEntry[];
    onChange: (photos: ProgressPhotoEntry[]) => void;
}) {
    const { t } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    async function upload(file: File) {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            const body = new FormData();
            body.append('photo', file);
            const response = await fetch(store().url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body,
            });
            if (!response.ok) {
                throw new Error('upload failed');
            }
            const data = (await response.json()) as {
                photo: ProgressPhotoEntry;
            };
            onChange([data.photo, ...photos]);
        } catch {
            toast.error(t('member.progress.photo_failed'));
        } finally {
            setBusy(false);
        }
    }

    async function remove(photo: ProgressPhotoEntry) {
        if (deletingId !== null) {
            return;
        }
        setDeletingId(photo.id);
        try {
            const response = await fetch(
                destroy({ progressPhoto: photo.id }).url,
                {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                },
            );
            if (response.ok) {
                onChange(photos.filter((p) => p.id !== photo.id));
            }
        } finally {
            setDeletingId(null);
        }
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="text-base">
                    {t('member.progress.photos')}
                </CardTitle>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={busy}
                    onClick={() => inputRef.current?.click()}
                >
                    <Camera className="size-4" />
                    {t('member.progress.add_photo')}
                </Button>
            </CardHeader>
            <CardContent>
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/*"
                    className="hidden"
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) {
                            void upload(file);
                        }
                        e.target.value = '';
                    }}
                />
                {photos.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('member.progress.photos_empty')}
                    </p>
                ) : (
                    <div className="grid grid-cols-3 gap-2">
                        {photos.map((photo) => (
                            <div
                                key={photo.id}
                                className="group relative overflow-hidden rounded-xl border border-[#2E2E2E]"
                            >
                                <img
                                    src={photo.url}
                                    alt={photo.taken_on}
                                    loading="lazy"
                                    className="aspect-square w-full object-cover"
                                />
                                <div className="absolute inset-x-0 bottom-0 flex items-center justify-between bg-black/60 px-2 py-1">
                                    <span className="text-[10px] font-medium text-[#F5F5F5]">
                                        {photo.taken_on.slice(5)}
                                    </span>
                                    <button
                                        type="button"
                                        disabled={deletingId === photo.id}
                                        onClick={() => remove(photo)}
                                        aria-label={t(
                                            'member.progress.delete_photo',
                                        )}
                                        className="text-[#F5F5F5]/70 transition-colors hover:text-[#FF6B6B] disabled:opacity-40"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
