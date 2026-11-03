import {
    Loader2,
    MessageCircle,
    MoreHorizontal,
    Pencil,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { formatMessageTime } from '@/components/member/chat/types';
import { memberApi } from '@/components/member/member-api';
import { RecapSummaryBlock } from '@/components/member/recap-summary';
import type { Recap, RecapComment } from '@/components/member/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    bravo as bravoRoute,
    destroy as destroyRecap,
    update as updateRecap,
} from '@/routes/member/recaps';
import {
    destroy as destroyComment,
    index as commentsIndex,
    store as storeComment,
} from '@/routes/member/recaps/comments';

function initials(name: string | null): string {
    return (name ?? '?')
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

const COMMENTS_PAGE = 10;

/**
 * One recap post on the home feed: photo, caption, exercise summary, bravo
 * toggle and an expandable comment thread. Card-local state mirrors the
 * server so interactions feel instant; failures roll back with a toast.
 */
export function RecapCard({
    recap,
    onDeleted,
}: {
    recap: Recap;
    onDeleted: (recapId: number) => void;
}) {
    const { t } = useTranslation();
    const [caption, setCaption] = useState(recap.caption);
    const [bravoed, setBravoed] = useState(recap.bravoed);
    const [bravosCount, setBravosCount] = useState(recap.bravos_count);
    const [comments, setComments] = useState<RecapComment[]>(recap.comments);
    const [commentsCount, setCommentsCount] = useState(recap.comments_count);
    const [commentsCursor, setCommentsCursor] = useState<number | null>(
        recap.comments.length < recap.comments_count
            ? (recap.comments[recap.comments.length - 1]?.id ?? null)
            : null,
    );
    const [showComments, setShowComments] = useState(false);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [loadingComments, setLoadingComments] = useState(false);
    const [editing, setEditing] = useState(false);
    const [editCaption, setEditCaption] = useState(recap.caption ?? '');
    const [savingCaption, setSavingCaption] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);

    async function toggleBravo() {
        const nextBravoed = !bravoed;
        setBravoed(nextBravoed);
        setBravosCount((count) => count + (nextBravoed ? 1 : -1));
        try {
            const result = await memberApi<{
                bravos_count: number;
                bravoed: boolean;
            }>(bravoRoute(recap.id).url, 'POST');
            setBravoed(result.bravoed);
            setBravosCount(result.bravos_count);
        } catch {
            setBravoed(!nextBravoed);
            setBravosCount((count) => count + (nextBravoed ? -1 : 1));
            toast.error(t('member.recap.publish_failed_toast'));
        }
    }

    async function loadOlderComments() {
        if (loadingComments || commentsCursor === null) {
            return;
        }
        setLoadingComments(true);
        try {
            const page = await memberApi<{
                comments: RecapComment[];
                next_cursor: number | null;
            }>(
                commentsIndex(recap.id, {
                    query: { before: commentsCursor },
                }).url,
                'GET',
            );
            setComments((list) => [...list, ...page.comments]);
            setCommentsCursor(page.next_cursor);
        } catch {
            toast.error(t('member.recap.comment_failed_toast'));
        } finally {
            setLoadingComments(false);
        }
    }

    async function sendComment() {
        const body = draft.trim();
        if (body === '' || sending) {
            return;
        }
        setSending(true);
        try {
            const result = await memberApi<{
                comment: RecapComment;
                comments_count: number;
            }>(storeComment(recap.id).url, 'POST', { body });
            // Server returns newest-first; append keeps chronological view.
            setComments((list) => [...list, result.comment]);
            setCommentsCount(result.comments_count);
            setDraft('');
            setShowComments(true);
        } catch {
            toast.error(t('member.recap.comment_failed_toast'));
        } finally {
            setSending(false);
        }
    }

    async function removeComment(comment: RecapComment) {
        try {
            const result = await memberApi<{ comments_count: number }>(
                destroyComment({ recap: recap.id, comment: comment.id }).url,
                'DELETE',
            );
            setComments((list) => list.filter((c) => c.id !== comment.id));
            setCommentsCount(result.comments_count);
        } catch {
            toast.error(t('member.recap.comment_failed_toast'));
        }
    }

    async function saveCaption() {
        if (savingCaption) {
            return;
        }
        setSavingCaption(true);
        try {
            const result = await memberApi<{ recap: Recap }>(
                updateRecap(recap.id).url,
                'PATCH',
                { caption: editCaption.trim() === '' ? null : editCaption.trim() },
            );
            setCaption(result.recap.caption);
            setEditing(false);
            toast.success(t('member.recap.caption_saved'));
        } catch {
            toast.error(t('member.recap.comment_failed_toast'));
        } finally {
            setSavingCaption(false);
        }
    }

    async function removeRecap() {
        if (deleting) {
            return;
        }
        setDeleting(true);
        try {
            await memberApi(destroyRecap(recap.id).url, 'DELETE');
            toast.success(t('member.recap.deleted_toast'));
            onDeleted(recap.id);
        } catch {
            toast.error(t('member.recap.comment_failed_toast'));
            setDeleting(false);
        }
    }

    // Comments arrive newest-first; display oldest → newest.
    const chronological = [...comments].reverse();

    return (
        <article className="overflow-hidden rounded-3xl border border-[#2E2E2E] bg-[#1F1F1F]">
            {/* Header */}
            <div className="flex items-center gap-3 px-4 pt-4">
                <Avatar className="size-10 border border-[#2E2E2E]">
                    <AvatarImage
                        src={recap.user.avatar ?? undefined}
                        alt={recap.user.name ?? ''}
                    />
                    <AvatarFallback className="bg-[#1C1C1E] text-xs font-bold text-[#AFFA01]">
                        {initials(recap.user.name)}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold text-[#F5F5F5]">
                        {recap.user.name}
                    </p>
                    <p className="text-[11px] text-[#8E8E93]">
                        {formatMessageTime(recap.created_at)}
                    </p>
                </div>
                {(recap.can_edit || recap.can_delete) && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 text-[#8E8E93]"
                                aria-label="Post options"
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {recap.can_edit && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setEditCaption(caption ?? '');
                                        setEditing(true);
                                    }}
                                >
                                    <Pencil className="size-4" />
                                    {t('member.recap.edit_caption')}
                                </DropdownMenuItem>
                            )}
                            {recap.can_delete && (
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => setConfirmDelete(true)}
                                >
                                    <Trash2 className="size-4" />
                                    {t('member.recap.delete_post')}
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>

            {/* Photo */}
            {recap.image_url !== null && (
                <img
                    src={recap.image_url}
                    alt=""
                    className="mt-3 max-h-80 w-full object-cover"
                    loading="lazy"
                />
            )}

            {/* Caption */}
            {caption !== null && (
                <p className="px-4 pt-3 text-sm whitespace-pre-wrap text-[#F5F5F5]">
                    {caption}
                </p>
            )}

            {/* Exercise summary */}
            <div className="px-4 pt-3">
                <RecapSummaryBlock summary={recap.summary} t={t} />
            </div>

            {/* Actions */}
            <div className="mt-3 flex items-center gap-2 border-t border-[#2E2E2E] px-4 py-2.5">
                <button
                    type="button"
                    onClick={toggleBravo}
                    className={cn(
                        'flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-bold transition-all active:scale-95',
                        bravoed
                            ? 'border-[#AFFA01]/60 bg-[#AFFA01]/15 text-[#AFFA01]'
                            : 'border-[#2E2E2E] text-[#A1A1AA] hover:border-[#AFFA01]/40 hover:text-[#AFFA01]',
                    )}
                    aria-pressed={bravoed}
                >
                    <span aria-hidden>👏</span>
                    {t('member.recap.bravo')}
                    {bravosCount > 0 && <span>{bravosCount}</span>}
                </button>
                <button
                    type="button"
                    onClick={() => setShowComments((open) => !open)}
                    className={cn(
                        'flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-bold transition-all active:scale-95',
                        showComments
                            ? 'border-[#AFFA01]/60 bg-[#AFFA01]/15 text-[#AFFA01]'
                            : 'border-[#2E2E2E] text-[#A1A1AA] hover:border-[#AFFA01]/40 hover:text-[#AFFA01]',
                    )}
                >
                    <MessageCircle className="size-3.5" />
                    {commentsCount > 0 && <span>{commentsCount}</span>}
                </button>
            </div>

            {/* Comments */}
            {showComments && (
                <div className="border-t border-[#2E2E2E] px-4 py-3">
                    {commentsCursor !== null && (
                        <button
                            type="button"
                            onClick={loadOlderComments}
                            disabled={loadingComments}
                            className="mb-2 text-xs font-semibold text-[#AFFA01] hover:underline disabled:opacity-50"
                        >
                            {loadingComments ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                t('member.recap.view_all_comments', {
                                    count: commentsCount,
                                })
                            )}
                        </button>
                    )}
                    <div className="space-y-2.5">
                        {chronological.map((comment) => (
                            <div key={comment.id} className="flex gap-2">
                                <Avatar className="size-7 shrink-0 border border-[#2E2E2E]">
                                    <AvatarImage
                                        src={comment.user.avatar ?? undefined}
                                        alt={comment.user.name ?? ''}
                                    />
                                    <AvatarFallback className="bg-[#1C1C1E] text-[9px] font-bold text-[#AFFA01]">
                                        {initials(comment.user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1 rounded-xl bg-[#262626] px-3 py-2">
                                    <div className="flex items-baseline justify-between gap-2">
                                        <p className="truncate text-xs font-bold text-[#F5F5F5]">
                                            {comment.user.name}
                                        </p>
                                        <span className="shrink-0 text-[10px] text-[#8E8E93]">
                                            {formatMessageTime(
                                                comment.created_at,
                                            )}
                                        </span>
                                    </div>
                                    <p className="mt-0.5 text-xs whitespace-pre-wrap text-[#D4D4D8]">
                                        {comment.body}
                                    </p>
                                </div>
                                {comment.can_delete && (
                                    <button
                                        type="button"
                                        onClick={() => removeComment(comment)}
                                        className="self-start p-1 text-[#666666] transition-colors hover:text-red-400"
                                        aria-label={t('member.chat.delete')}
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>

                    <div className="mt-3 flex items-center gap-2">
                        <input
                            value={draft}
                            onChange={(e) =>
                                setDraft(e.target.value.slice(0, 1000))
                            }
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    void sendComment();
                                }
                            }}
                            placeholder={t('member.recap.comment_placeholder')}
                            className="h-9 min-w-0 flex-1 rounded-full border border-[#2E2E2E] bg-[#262626] px-3.5 text-xs text-[#F5F5F5] placeholder:text-[#666666] focus:border-[#AFFA01]/50 focus:outline-none"
                        />
                        <Button
                            type="button"
                            size="icon"
                            disabled={sending || draft.trim() === ''}
                            onClick={sendComment}
                            className="size-9 shrink-0 rounded-full bg-[#AFFA01] text-black hover:brightness-105"
                            aria-label={t('member.recap.comment_send')}
                        >
                            {sending ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Send className="size-4" />
                            )}
                        </Button>
                    </div>
                </div>
            )}

            {/* Edit caption dialog */}
            <Dialog open={editing} onOpenChange={setEditing}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {t('member.recap.edit_caption')}
                        </DialogTitle>
                        <DialogDescription className="sr-only">
                            {t('member.recap.edit_caption')}
                        </DialogDescription>
                    </DialogHeader>
                    <textarea
                        value={editCaption}
                        onChange={(e) =>
                            setEditCaption(e.target.value.slice(0, 280))
                        }
                        rows={3}
                        placeholder={t('member.recap.caption_placeholder')}
                        className="w-full resize-none rounded-xl border border-[#2E2E2E] bg-[#1F1F1F] px-3 py-2.5 text-sm text-[#F5F5F5] placeholder:text-[#666666] focus:border-[#AFFA01]/50 focus:outline-none"
                    />
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setEditing(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            onClick={saveCaption}
                            disabled={savingCaption}
                            className="bg-[#AFFA01] font-bold text-black hover:brightness-105"
                        >
                            {savingCaption && (
                                <Loader2 className="size-4 animate-spin" />
                            )}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete confirm dialog */}
            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>
                            {t('member.recap.delete_confirm_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('member.recap.delete_confirm_body')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirmDelete(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={removeRecap}
                            disabled={deleting}
                        >
                            {deleting && (
                                <Loader2 className="size-4 animate-spin" />
                            )}
                            {t('member.recap.delete_post')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </article>
    );
}
