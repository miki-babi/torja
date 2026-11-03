import { Head, usePage } from '@inertiajs/react';
import { Check, MessageCircle, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConversationItem } from '@/components/member/chat/conversation-item';
import type { InboxConversation } from '@/components/member/chat/types';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { echo } from '@/lib/echo';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/lib/i18n';
import {
    accept as friendAccept,
    destroy as friendDestroy,
} from '@/routes/member/ranks/friends';

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
type FriendRequest = { id: number; name: string; avatar: string | null };

export default function MemberMessages({
    conversations: initial,
    pendingIn: initialPendingIn,
    pendingOut: initialPendingOut,
}: {
    conversations: InboxConversation[];
    pendingIn: FriendRequest[];
    pendingOut: FriendRequest[];
}) {
    const { t } = useTranslation();
    const initials = useInitials();
    const { auth } = usePage().props;
    const [conversations, setConversations] =
        useState<InboxConversation[]>(initial);
    const [pendingIn, setPendingIn] = useState(initialPendingIn);
    const [pendingOut, setPendingOut] = useState(initialPendingOut);

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
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('member.chats.title')}
                </h1>
            </div>

            {(pendingIn.length > 0 || pendingOut.length > 0) && (
                <Card className="mt-4">
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('member.ranks.requests')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {pendingIn.map((p) => (
                            <div key={p.id} className="flex items-center gap-2">
                                <Avatar className="size-8">
                                    <AvatarImage
                                        src={p.avatar ?? undefined}
                                        alt={p.name}
                                    />
                                    <AvatarFallback className="text-[10px]">
                                        {initials(p.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="flex-1 truncate text-sm">
                                    {p.name}
                                </span>
                                <Button
                                    size="sm"
                                    onClick={async () => {
                                        await memberApi(
                                            friendAccept({
                                                friendship: p.id,
                                            }).url,
                                            'POST',
                                        );
                                        setPendingIn((l) =>
                                            l.filter((x) => x.id !== p.id),
                                        );
                                    }}
                                >
                                    <Check className="size-4" />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={async () => {
                                        await memberApi(
                                            friendDestroy({
                                                friendship: p.id,
                                            }).url,
                                            'DELETE',
                                        );
                                        setPendingIn((l) =>
                                            l.filter((x) => x.id !== p.id),
                                        );
                                    }}
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}
                        {pendingOut.map((p) => (
                            <div
                                key={p.id}
                                className="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <Avatar className="size-8">
                                    <AvatarImage
                                        src={p.avatar ?? undefined}
                                        alt={p.name}
                                    />
                                    <AvatarFallback className="text-[10px]">
                                        {initials(p.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="truncate">
                                    {p.name} · {t('member.ranks.pending')}
                                </span>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    aria-label={t('member.ranks.pending')}
                                    onClick={async () => {
                                        await memberApi(
                                            friendDestroy({
                                                friendship: p.id,
                                            }).url,
                                            'DELETE',
                                        );
                                        setPendingOut((l) =>
                                            l.filter((x) => x.id !== p.id),
                                        );
                                    }}
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

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
