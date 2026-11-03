import { Loader2, RefreshCw, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useInitials } from '@/hooks/use-initials';
import { removeAvatar, uploadAvatar } from '@/lib/avatar-upload';
import { useTranslation } from '@/lib/i18n';

/**
 * Click-to-view dialog for the member's own profile photo: large preview
 * (or big initials when none is set) with replace and delete actions.
 * Upload/remove go through the shared settings/avatar endpoints; the
 * parent gets the new URL via `onChange`.
 */
export function AvatarDialog({
    open,
    onOpenChange,
    name,
    avatarUrl,
    onChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    name: string;
    avatarUrl: string | null;
    onChange: (url: string | null) => void;
}) {
    const { t } = useTranslation();
    const getInitials = useInitials();
    const inputRef = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [confirmRemove, setConfirmRemove] = useState(false);

    function handleOpenChange(next: boolean) {
        if (!next) {
            setConfirmRemove(false);
        }
        onOpenChange(next);
    }

    async function upload(file: File) {
        setBusy(true);
        try {
            onChange(await uploadAvatar(file));
        } catch {
            toast.error(t('member.profile.photo_upload_failed'));
        } finally {
            setBusy(false);
        }
    }

    async function remove() {
        setBusy(true);
        try {
            await removeAvatar();
            onChange(null);
            toast.success(t('member.profile.photo_deleted'));
            handleOpenChange(false);
        } catch {
            toast.error(t('member.profile.photo_delete_failed'));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{t('member.profile.photo_title')}</DialogTitle>
                    <DialogDescription className="sr-only">
                        {t('member.profile.photo_title')}
                    </DialogDescription>
                </DialogHeader>

                <input
                    ref={inputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        e.target.value = '';
                        if (file && !busy) {
                            void upload(file);
                        }
                    }}
                />

                <div className="flex justify-center">
                    {avatarUrl !== null ? (
                        <img
                            src={avatarUrl}
                            alt={name}
                            className="size-52 rounded-3xl border border-[#2E2E2E] object-cover shadow-lg"
                        />
                    ) : (
                        <Avatar className="size-52 rounded-3xl border border-[#2E2E2E] shadow-lg">
                            <AvatarFallback className="rounded-3xl bg-[#1C1C1E] text-6xl font-bold text-[#AFFA01]">
                                {getInitials(name)}
                            </AvatarFallback>
                        </Avatar>
                    )}
                </div>

                {confirmRemove ? (
                    <div className="rounded-2xl border border-[#2E2E2E] bg-[#262626] p-3.5 text-center">
                        <p className="text-xs font-semibold text-[#F5F5F5]">
                            {t('member.profile.photo_delete_confirm')}
                        </p>
                        <div className="mt-3 flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="flex-1"
                                disabled={busy}
                                onClick={() => setConfirmRemove(false)}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                className="flex-1"
                                disabled={busy}
                                onClick={remove}
                            >
                                {busy && (
                                    <Loader2 className="size-4 animate-spin" />
                                )}
                                {t('member.profile.photo_delete')}
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            disabled={busy}
                            onClick={() => inputRef.current?.click()}
                        >
                            {busy ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <RefreshCw className="size-4" />
                            )}
                            {t('member.profile.photo_replace')}
                        </Button>
                        {avatarUrl !== null && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-red-400 hover:bg-red-400/10 hover:text-red-300"
                                disabled={busy}
                                onClick={() => setConfirmRemove(true)}
                                aria-label={t('member.profile.photo_delete')}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
