import {
    Check,
    CheckCheck,
    Copy,
    FileText,
    MoreVertical,
    Pencil,
    Reply,
    Trash2,
} from 'lucide-react';
import { toast } from 'sonner';
import {
    formatBytes,
    formatMessageTime,
    REACTION_EMOJIS,
    type ChatMessage,
} from '@/components/member/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * One chat message: bubble, reply quote, attachments, reactions, and a
 * per-message action menu (reply / react / edit / delete / copy). The
 * `seen` flag renders a double-check on the viewer's own last message.
 */
export function MessageBubble({
    message,
    mine,
    viewerId,
    showSender,
    seen,
    canDelete,
    onReply,
    onEdit,
    onDelete,
    onReact,
    onJump,
}: {
    message: ChatMessage;
    mine: boolean;
    viewerId: number;
    showSender: boolean;
    seen: boolean;
    canDelete: boolean;
    onReply: (message: ChatMessage) => void;
    onEdit: (message: ChatMessage) => void;
    onDelete: (message: ChatMessage) => void;
    onReact: (message: ChatMessage, emoji: string) => void;
    onJump: (messageId: number) => void;
}) {
    const { t } = useTranslation();
    const initials = useInitials();

    if (message.deleted) {
        return (
            <div className={cn('flex', mine ? 'justify-end' : 'justify-start')}>
                <p className="rounded-2xl border border-dashed border-[#2E2E2E] px-3.5 py-2 text-xs text-[#8E8E93] italic">
                    {t('member.chat.deleted')}
                </p>
            </div>
        );
    }

    const images = message.attachments.filter((a) => a.kind === 'image');
    const files = message.attachments.filter((a) => a.kind === 'file');

    return (
        <div
            className={cn(
                'group flex gap-2',
                mine ? 'justify-end' : 'justify-start',
            )}
        >
            {!mine && (
                <Avatar className="mt-auto size-7 shrink-0 border border-[#2E2E2E]">
                    <AvatarImage
                        src={message.sender.avatar ?? undefined}
                        alt={message.sender.name}
                    />
                    <AvatarFallback className="bg-[#1C1C1E] text-[9px] font-bold text-[#AFFA01]">
                        {initials(message.sender.name)}
                    </AvatarFallback>
                </Avatar>
            )}

            <div
                className={cn(
                    'max-w-[78%] space-y-1',
                    mine ? 'items-end' : 'items-start',
                )}
            >
                {showSender && !mine && (
                    <p className="ml-1 text-[10px] font-bold tracking-wide text-[#AFFA01] uppercase">
                        {message.sender.name}
                    </p>
                )}

                <div className="flex items-end gap-1">
                    {mine && (
                        <ActionMenu
                            message={message}
                            mine={mine}
                            canDelete={canDelete}
                            onReply={onReply}
                            onEdit={onEdit}
                            onDelete={onDelete}
                            onReact={onReact}
                            t={t}
                        />
                    )}
                    <div
                        className={cn(
                            'overflow-hidden rounded-2xl border px-3.5 py-2.5',
                            mine
                                ? 'rounded-br-md border-transparent bg-gradient-to-br from-[#C5FF4D] to-[#AFFA01] text-black'
                                : 'rounded-bl-md border-[#2E2E2E] bg-[#161616] text-[#F5F5F5]',
                        )}
                    >
                        {message.reply_to !== null && (
                            <button
                                type="button"
                                onClick={() => onJump(message.reply_to!.id)}
                                className={cn(
                                    'mb-1.5 block w-full rounded-lg border-l-2 px-2 py-1 text-left text-[11px]',
                                    mine
                                        ? 'border-black/40 bg-black/10 text-black/70'
                                        : 'border-[#AFFA01]/60 bg-[#AFFA01]/5 text-[#8E8E93]',
                                )}
                            >
                                <span className="block font-bold">
                                    {message.reply_to.sender_name}
                                </span>
                                <span className="block truncate">
                                    {message.reply_to.snippet ??
                                        t('member.chat.deleted')}
                                </span>
                            </button>
                        )}

                        {images.length > 0 && (
                            <div
                                className={cn(
                                    'grid gap-1',
                                    images.length > 1
                                        ? 'grid-cols-2'
                                        : 'grid-cols-1',
                                    message.body !== null && 'mb-1.5',
                                )}
                            >
                                {images.map((a) => (
                                    <a
                                        key={a.id}
                                        href={a.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="block overflow-hidden rounded-lg"
                                    >
                                        <img
                                            src={a.url}
                                            alt={a.name}
                                            loading="lazy"
                                            className="max-h-56 w-full object-cover"
                                        />
                                    </a>
                                ))}
                            </div>
                        )}

                        {files.map((a) => (
                            <a
                                key={a.id}
                                href={a.url}
                                target="_blank"
                                rel="noreferrer"
                                className={cn(
                                    'mb-1.5 flex items-center gap-2 rounded-lg border px-2.5 py-2 text-xs',
                                    mine
                                        ? 'border-black/20 bg-black/10 text-black'
                                        : 'border-[#2E2E2E] bg-[#101010] text-[#F5F5F5]',
                                )}
                            >
                                <FileText className="size-4 shrink-0" />
                                <span className="min-w-0 flex-1 truncate font-semibold">
                                    {a.name}
                                </span>
                                <span
                                    className={cn(
                                        'shrink-0 text-[10px]',
                                        mine
                                            ? 'text-black/60'
                                            : 'text-[#8E8E93]',
                                    )}
                                >
                                    {formatBytes(a.size)}
                                </span>
                            </a>
                        ))}

                        {message.body !== null && (
                            <p className="text-sm break-words whitespace-pre-wrap">
                                {message.body}
                            </p>
                        )}

                        <p
                            className={cn(
                                'mt-1 flex items-center justify-end gap-1 text-[10px]',
                                mine ? 'text-black/60' : 'text-[#8E8E93]',
                            )}
                        >
                            {message.edited && (
                                <span>{t('member.chat.edited')}</span>
                            )}
                            {formatMessageTime(message.created_at)}
                            {mine &&
                                (seen ? (
                                    <CheckCheck className="size-3.5 text-black" />
                                ) : (
                                    <Check className="size-3.5" />
                                ))}
                        </p>
                    </div>
                    {!mine && (
                        <ActionMenu
                            message={message}
                            mine={mine}
                            canDelete={canDelete}
                            onReply={onReply}
                            onEdit={onEdit}
                            onDelete={onDelete}
                            onReact={onReact}
                            t={t}
                        />
                    )}
                </div>

                {message.reactions.length > 0 && (
                    <div
                        className={cn(
                            'flex flex-wrap gap-1',
                            mine ? 'justify-end' : 'justify-start',
                        )}
                    >
                        {message.reactions.map((r) => {
                            const reacted = r.user_ids.includes(viewerId);
                            return (
                                <button
                                    key={r.emoji}
                                    type="button"
                                    onClick={() => onReact(message, r.emoji)}
                                    className={cn(
                                        'flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs',
                                        reacted
                                            ? 'border-[#AFFA01]/60 bg-[#AFFA01]/10 text-[#AFFA01]'
                                            : 'border-[#2E2E2E] bg-[#101010] text-[#F5F5F5]',
                                    )}
                                >
                                    <span>{r.emoji}</span>
                                    <span className="text-[10px] font-bold">
                                        {r.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}

function ActionMenu({
    message,
    mine,
    canDelete,
    onReply,
    onEdit,
    onDelete,
    onReact,
    t,
}: {
    message: ChatMessage;
    mine: boolean;
    canDelete: boolean;
    onReply: (m: ChatMessage) => void;
    onEdit: (m: ChatMessage) => void;
    onDelete: (m: ChatMessage) => void;
    onReact: (m: ChatMessage, emoji: string) => void;
    t: (key: string) => string;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={t('member.chat.actions')}
                    className="mb-1 rounded-full p-1 text-[#8E8E93] opacity-0 transition-opacity group-hover:opacity-100 hover:text-[#F5F5F5] focus:opacity-100"
                >
                    <MoreVertical className="size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align={mine ? 'end' : 'start'}
                className="w-48 border-[#2E2E2E] bg-[#161616]"
            >
                <div className="flex items-center justify-between px-2 py-1.5">
                    {REACTION_EMOJIS.map((emoji) => (
                        <button
                            key={emoji}
                            type="button"
                            onClick={() => onReact(message, emoji)}
                            className="rounded-lg p-1 text-base transition-transform hover:scale-125"
                        >
                            {emoji}
                        </button>
                    ))}
                </div>
                <DropdownMenuSeparator className="bg-[#2E2E2E]" />
                <DropdownMenuItem onClick={() => onReply(message)}>
                    <Reply className="size-4" />
                    {t('member.chat.reply')}
                </DropdownMenuItem>
                {message.body !== null && (
                    <DropdownMenuItem
                        onClick={() => {
                            void navigator.clipboard.writeText(
                                message.body ?? '',
                            );
                            toast.success(t('member.chat.copied'));
                        }}
                    >
                        <Copy className="size-4" />
                        {t('member.chat.copy')}
                    </DropdownMenuItem>
                )}
                {mine && message.body !== null && (
                    <DropdownMenuItem onClick={() => onEdit(message)}>
                        <Pencil className="size-4" />
                        {t('member.chat.edit')}
                    </DropdownMenuItem>
                )}
                {canDelete && (
                    <DropdownMenuItem
                        variant="destructive"
                        onClick={() => onDelete(message)}
                    >
                        <Trash2 className="size-4" />
                        {t('member.chat.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
