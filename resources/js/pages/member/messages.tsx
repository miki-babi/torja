import { Head, router, usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConversationItem } from '@/components/member/chat/conversation-item';
import type { InboxConversation } from '@/components/member/chat/types';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { echo } from '@/lib/echo';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/lib/i18n';
import { dm } from '@/routes/member/messages';

type ActivityEvent = {
    conversation_id: number;
    type: 'dm';
    title: string;
    sender: string;
    snippet: string;
    created_at: string | null;
    unread_total: number;
};

/**
 * The chats inbox — every room the user sits in, kept fresh in real time
 * by the per-user `conversation.activity` broadcast. Members are
 * redirected straight into their coach DM, so only staff see this page.
 */
export default function MemberMessages({
    conversations: initial,
}: {
    conversations: InboxConversation[];
}) {
    const { t } = useTranslation();
    const initials = useInitials();
    const { auth, coach } = usePage().props;
    const [messaging, setMessaging] = useState(false);
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
                    type: 'dm',
                    title: existing?.title ?? event.title,
                    avatar: existing?.avatar ?? null,
                    members: existing?.members ?? 2,
                    muted: existing?.muted ?? false,
                    is_coach: existing?.is_coach ?? false,
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

    async function openCoachChat() {
        if (coach === null || coach === undefined) {
            return;
        }

        setMessaging(true);
        try {
            const { conversation_id } = await memberApi<{
                conversation_id: number;
            }>(dm().url, 'POST', { user_id: coach.user_id });
            router.visit(`/member/messages/${conversation_id}`);
        } catch {
            toast.error(t('member.coach.chat_failed'));
        } finally {
            setMessaging(false);
        }
    }

    return (
        <MobileShell>
            <Head title={t('member.chats.title')} />

            <div className="flex items-center gap-3 pt-2">
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('member.chats.title')}
                </h1>
            </div>

            {conversations.length === 0 && !coach ? (
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
                    {coach && !conversations.some((c) => c.is_coach) && (
                        <button
                            type="button"
                            onClick={() => void openCoachChat()}
                            disabled={messaging}
                            className="flex w-full items-center gap-3 rounded-2xl border border-[#AFFA01]/25 bg-[#101010] px-3.5 py-3 text-left transition-colors hover:border-[#AFFA01]/50"
                        >
                            <Avatar className="size-11 border border-[#2E2E2E]">
                                <AvatarImage
                                    src={coach.avatar ?? undefined}
                                    alt={coach.name}
                                />
                                <AvatarFallback className="bg-[#1C1C1E] text-xs font-bold text-[#AFFA01]">
                                    {initials(coach.name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-bold text-[#F5F5F5]">
                                    {coach.name}
                                </p>
                                <p className="mt-0.5 text-xs text-[#8E8E93]">
                                    {t('member.coach.your_coach')}
                                </p>
                            </div>
                            <MessageCircle className="size-4 shrink-0 text-[#AFFA01]" />
                        </button>
                    )}
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
