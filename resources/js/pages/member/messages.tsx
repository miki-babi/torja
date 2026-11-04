import { Head, router, usePage } from '@inertiajs/react';
import { Check, MessageCircle, Search, UserPlus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConversationItem } from '@/components/member/chat/conversation-item';
import type { InboxConversation } from '@/components/member/chat/types';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import {
    reasonText,
    ShareLocationButton,
    type Suggested,
} from '@/components/member/people-suggestions';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { echo } from '@/lib/echo';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/lib/i18n';
import { locationSupported } from '@/lib/location';
import {
    accept as friendAccept,
    destroy as friendDestroy,
    search as friendSearch,
    store as friendStore,
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
    suggestions: initialSuggestions,
    hasLocation,
}: {
    conversations: InboxConversation[];
    pendingIn: FriendRequest[];
    pendingOut: FriendRequest[];
    suggestions: Suggested[];
    hasLocation: boolean;
}) {
    const { t } = useTranslation();
    const initials = useInitials();
    const { auth } = usePage().props;
    const [conversations, setConversations] =
        useState<InboxConversation[]>(initial);
    const [pendingIn, setPendingIn] = useState(initialPendingIn);
    const [pendingOut, setPendingOut] = useState(initialPendingOut);
    const [suggestions, setSuggestions] = useState(initialSuggestions);
    const [friendQuery, setFriendQuery] = useState('');
    const [friendResults, setFriendResults] = useState<Suggested[] | null>(
        null,
    );

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

    // Debounced live search: results replace the suggestion list while a
    // query is typed; empty query restores the server-provided defaults.
    useEffect(() => {
        if (friendQuery.trim() === '') {
            setFriendResults(null);
            return;
        }
        const timer = setTimeout(async () => {
            const response = await fetch(
                friendSearch({ query: { q: friendQuery } }).url,
                {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                },
            );
            const data = (await response.json()) as {
                users: Suggested[];
            };
            setFriendResults(data.users);
        }, 250);
        return () => clearTimeout(timer);
    }, [friendQuery]);

    async function sendRequest(user: Suggested) {
        await memberApi(friendStore().url, 'POST', { user_id: user.id });
        setSuggestions((list) => list.filter((x) => x.id !== user.id));
        setFriendResults(
            (list) => list?.filter((x) => x.id !== user.id) ?? null,
        );
    }

    return (
        <MobileShell>
            <Head title={t('member.chats.title')} />

            <div className="flex items-center gap-3 pt-2">
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('member.chats.title')}
                </h1>
            </div>

            {(suggestions.length > 0 ||
                friendResults !== null ||
                (!hasLocation && locationSupported())) && (
                <Card className="mt-4">
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('member.ranks.suggest.title')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {!hasLocation && locationSupported() && (
                            <div className="mb-3 flex items-center justify-between gap-3 rounded-xl border border-[#2E2E2E] bg-[#101010] px-3 py-2.5">
                                <span className="text-xs text-[#8E8E93]">
                                    {t('member.ranks.suggest.share')}
                                </span>
                                <ShareLocationButton
                                    onShared={() => router.reload()}
                                />
                            </div>
                        )}
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#8E8E93]" />
                            <Input
                                value={friendQuery}
                                onChange={(e) => setFriendQuery(e.target.value)}
                                placeholder={t('member.ranks.search_users')}
                                className="pl-9"
                            />
                        </div>
                        {(() => {
                            const visible = friendResults ?? suggestions;
                            return visible.length === 0 &&
                                friendQuery.trim() !== '' ? (
                                <p className="mt-3 text-xs text-[#8E8E93]">
                                    {t('member.ranks.search_no_results')}
                                </p>
                            ) : (
                                <div className="-mx-6 mt-2 divide-y divide-[#2E2E2E] px-6">
                                    {visible.map((u) => {
                                        const reason = reasonText(t, u.reason);

                                        return (
                                            <div
                                                key={u.id}
                                                className="flex items-center justify-between gap-2 py-2.5"
                                            >
                                                <div className="flex min-w-0 items-center gap-2">
                                                    <Avatar className="size-8">
                                                        <AvatarImage
                                                            src={
                                                                u.avatar ??
                                                                undefined
                                                            }
                                                            alt={u.name}
                                                        />
                                                        <AvatarFallback className="text-[10px]">
                                                            {initials(u.name)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="min-w-0">
                                                        <span className="block truncate text-sm font-medium text-[#F5F5F5]">
                                                            {u.name}
                                                        </span>
                                                        {reason !== null && (
                                                            <span className="block truncate text-[11px] text-[#8E8E93]">
                                                                {reason}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    aria-label={u.name}
                                                    onClick={() =>
                                                        void sendRequest(u)
                                                    }
                                                >
                                                    <UserPlus className="size-4" />
                                                </Button>
                                            </div>
                                        );
                                    })}
                                </div>
                            );
                        })()}
                    </CardContent>
                </Card>
            )}

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
