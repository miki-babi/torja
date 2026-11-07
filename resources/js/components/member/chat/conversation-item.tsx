import { Link } from '@inertiajs/react';
import {
    formatMessageTime,
    type InboxConversation,
} from '@/components/member/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { show } from '@/routes/member/messages';

/**
 * One inbox row — title, last snippet, timestamp, unread badge and a
 * mute glyph when Telegram pushes are off for the room.
 */
export function ConversationItem({
    conversation,
}: {
    conversation: InboxConversation;
}) {
    const initials = useInitials();

    return (
        <Link
            href={show(conversation.id).url}
            className="flex items-center gap-3 rounded-2xl border border-[#2E2E2E] bg-[#101010] px-3.5 py-3 transition-colors hover:border-[#3a3a3a]"
        >
            <div className="relative shrink-0">
                <Avatar className="size-11 border border-[#2E2E2E]">
                    <AvatarImage
                        src={conversation.avatar ?? undefined}
                        alt={conversation.title}
                    />
                    <AvatarFallback className="bg-[#1C1C1E] text-xs font-bold text-[#AFFA01]">
                        {initials(conversation.title)}
                    </AvatarFallback>
                </Avatar>
                {conversation.unread > 0 && (
                    <span className="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-[#AFFA01] px-1 text-[10px] font-extrabold text-black">
                        {conversation.unread > 99 ? '99+' : conversation.unread}
                    </span>
                )}
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex items-baseline justify-between gap-2">
                    <p className="truncate text-sm font-bold text-[#F5F5F5]">
                        {conversation.title}
                    </p>
                    <span className="shrink-0 text-[10px] font-medium text-[#8E8E93]">
                        {formatMessageTime(
                            conversation.last_message?.created_at ?? null,
                        )}
                    </span>
                </div>
                <div className="mt-0.5 flex items-center gap-1.5">
                    <p
                        className={cn(
                            'min-w-0 flex-1 truncate text-xs',
                            conversation.unread > 0
                                ? 'font-semibold text-[#F5F5F5]'
                                : 'text-[#8E8E93]',
                        )}
                    >
                        {conversation.last_message === null
                            ? '…'
                            : `${conversation.last_message.mine ? 'You: ' : ''}${conversation.last_message.snippet}`}
                    </p>
                    {conversation.muted && (
                        <span className="shrink-0 text-[11px] text-[#8E8E93]">
                            🔕
                        </span>
                    )}
                </div>
            </div>
        </Link>
    );
}
