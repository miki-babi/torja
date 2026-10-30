import { Link2, Loader2, Plus, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { xsrfToken } from '@/components/member/member-api';
import { TelegramEditor } from '@/components/telegram-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { uploadMedia } from '@/routes/admin/broadcasts';
import type { BroadcastMediaItem } from '@/types';

const KIND_LABELS: Record<string, string> = {
    photo: 'Photo',
    video: 'Video',
    document: 'Document',
    animation: 'GIF / animation',
};

const ACCEPTS: Record<string, string> = {
    photo: 'image/jpeg,image/png,image/webp,image/gif',
    video: 'video/mp4,video/quicktime,video/webm',
    document: '*/*',
    animation: 'video/mp4,image/gif',
};

/**
 * Media attachment editor. Single-media types get one item; albums get a
 * 2–10 item list, each with an optional rich caption. Files upload to
 * the public disk through the upload-media endpoint; Telegram reuses the
 * stored file_id after the first send.
 */
export function MediaPicker({
    items,
    onChange,
    allowedKinds,
    album,
}: {
    items: BroadcastMediaItem[];
    onChange: (items: BroadcastMediaItem[]) => void;
    allowedKinds: string[];
    album?: boolean;
}) {
    const [uploading, setUploading] = useState<number | null>(null);
    const fileRefs = useRef<Record<number, HTMLInputElement | null>>({});

    function patch(index: number, value: Partial<BroadcastMediaItem>) {
        onChange(
            items.map((item, i) =>
                i === index ? { ...item, ...value } : item,
            ),
        );
    }

    async function upload(index: number, file: File) {
        const item = items[index];
        const body = new FormData();
        body.append('kind', item.kind);
        body.append('file', file);
        setUploading(index);

        try {
            const response = await fetch(uploadMedia().url, {
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
            patch(index, {
                source: 'path',
                value: path,
                url,
                file_id: null,
            });
        } finally {
            setUploading(null);
        }
    }

    return (
        <div className="space-y-3">
            {items.map((item, i) => (
                <div
                    key={i}
                    className="rounded-lg border border-input p-3 space-y-3"
                >
                    <div className="flex items-center justify-between gap-2">
                        <Select
                            value={item.kind}
                            onValueChange={(v) =>
                                patch(i, {
                                    kind: v as BroadcastMediaItem['kind'],
                                })
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {allowedKinds.map((kind) => (
                                    <SelectItem key={kind} value={kind}>
                                        {KIND_LABELS[kind] ?? kind}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {album && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={items.length <= 2}
                                onClick={() =>
                                    onChange(items.filter((_, j) => j !== i))
                                }
                            >
                                <Trash2 className="size-3" />
                            </Button>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            ref={(el) => {
                                fileRefs.current[i] = el;
                            }}
                            type="file"
                            accept={ACCEPTS[item.kind] ?? '*/*'}
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) {
                                    void upload(i, file);
                                }
                                e.target.value = '';
                            }}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={uploading !== null}
                            onClick={() => fileRefs.current[i]?.click()}
                        >
                            {uploading === i ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Upload className="size-4" />
                            )}
                            {item.source === 'path' && item.value !== ''
                                ? 'Replace file'
                                : 'Upload file'}
                        </Button>
                        <div className="flex flex-1 items-center gap-2 min-w-52">
                            <Link2 className="size-4 shrink-0 text-muted-foreground" />
                            <Input
                                placeholder="…or paste a public URL"
                                value={
                                    item.source === 'url' ? item.value : ''
                                }
                                onChange={(e) =>
                                    patch(i, {
                                        source: 'url',
                                        value: e.target.value,
                                        url: null,
                                        file_id: null,
                                    })
                                }
                            />
                        </div>
                    </div>

                    {item.source === 'path' && item.value !== '' && (
                        <p className="text-xs text-muted-foreground">
                            Uploaded: {item.value}
                            {item.file_id ? ' · file_id cached' : ''}
                        </p>
                    )}

                    {item.kind === 'photo' &&
                        item.source === 'url' &&
                        item.value !== '' && (
                            <img
                                src={item.value}
                                alt=""
                                className="max-h-40 rounded-md object-cover"
                            />
                        )}
                    {item.kind === 'photo' &&
                        item.source === 'path' &&
                        item.url && (
                            <img
                                src={item.url}
                                alt=""
                                className="max-h-40 rounded-md object-cover"
                            />
                        )}

                    {album && (
                        <div className="space-y-1">
                            <Label className="text-xs">
                                Item caption (optional)
                            </Label>
                            <TelegramEditor
                                value={item.caption_html ?? ''}
                                placeholder="Caption for this item…"
                                onChange={(html) =>
                                    patch(i, { caption_html: html })
                                }
                            />
                        </div>
                    )}
                </div>
            ))}

            {album && items.length < 10 && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        onChange([
                            ...items,
                            {
                                kind: 'photo',
                                source: 'url',
                                value: '',
                                caption_html: '',
                            },
                        ])
                    }
                >
                    <Plus className="size-4" /> Add item ({items.length}/10)
                </Button>
            )}
        </div>
    );
}
