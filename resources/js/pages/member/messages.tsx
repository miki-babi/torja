import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, MessageCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConversationItem } from '@/components/member/chat/conversation-item';
import type { InboxConversation } from '@/components/member/chat/types';
import { MobileShell } from '@/components/member/mobile-shell';
import { echo } from '@/lib/echo';
import { useTranslation } from '@/lib/i18n';
import { ranks } from '@/routes/member';

type ActivityEvent = {
    conversation_id: number;
    type: 'dm' | 'group' | 'gym';
    title: string;
    sender: string;
    snippet: string;
    created_at: string | null;
    unread_total: number;
};

/**
 * The chats inbox — every room the member sits in, kept fresh in real
 * time by the per-user `conversation.activity` broadcast.
 */
export default function MemberMessages({
    conversations: initial,
}: {
    conversations: InboxConversation[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [conversations, setConversations] =
        useState<InboxConversation[]>(initial);

    useEffect(() => {
        const userId = auth.user?.id;
        if (userId === undefined) {
            return;
        }

        const channel = echo().private(`user.${userId}`);
        channel.listen('conversation.activity', (event: ActivityEvent) => {
            setConversations((list) => {
                const rest = list.filter((c) => c.id !== event.conversation_id);
                const existing = list.find(
                    (c) => c.id === event.conversation_id,
                );

                const updated: InboxConversation = {
                    id: event.conversation_id,
                    type: event.type,
                    title:
                        existing !== undefined && event.type !== 'dm'
                            ? existing.title
                            : (existing?.title ?? event.title),
                    avatar: existing?.avatar ?? null,
                    members: existing?.members ?? 2,
                    muted: existing?.muted ?? false,
                    unread: (existing?.unread ?? 0) + 1,
                    last_message: {
                        sender: event.sender,
                        mine: false,
                        snippet: event.snippet,
                        created_at: event.created_at,
                    },
                    activity: event.created_at,
                };

                return [updated, ...rest];
            });
        });

        return () => {
            echo().leave(`user.${userId}`);
        };
    }, [auth.user?.id]);

    return (
        <MobileShell>
            <Head title={t('member.chats.title')} />

            <div className="flex items-center gap-3 pt-2">
                <Link
                    href={ranks().url}
                    aria-label={t('member.chats.back')}
                    className="flex size-9 items-center justify-center rounded-full border border-[#2E2E2E] text-[#8E8E93] hover:text-[#F5F5F5]"
                >
                    <ArrowLeft className="size-4" />
                </Link>
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('member.chats.title')}
                </h1>
            </div>

            {conversations.length === 0 ? (
                <div className="mt-16 flex flex-col items-center gap-3 text-center">
                    <div className="flex size-16 items-center justify-center rounded-full border border-[#2E2E2E] bg-[#101010]">
                        <MessageCircle className="size-7 text-[#8E8E93]" />
                    </div>
                    <p className="max-w-56 text-sm text-[#8E8E93]">
                        {t('member.chats.empty')}
                    </p>
                </div>
            ) : (
                <div className="mt-4 space-y-2">
                    {conversations.map((conversation) => (
                        <ConversationItem
                            key={conversation.id}
                            conversation={conversation}
                        />
                    ))}
                </div>
            )}
        </MobileShell>
    );
}
