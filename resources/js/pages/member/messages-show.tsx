import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Bell, BellOff, Dumbbell, Users } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { MessageBubble } from '@/components/member/chat/message-bubble';
import { MessageComposer } from '@/components/member/chat/message-composer';
import {
    type ChatMessage,
    type ChatParticipant,
    type ChatReaction,
    type ConversationMeta,
} from '@/components/member/chat/types';
import { memberApi, memberApiForm } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { echo } from '@/lib/echo';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { messages as messagesIndex } from '@/routes/member';
import {
    destroy as destroyMessage,
    mute as muteConversation,
    older,
    react as reactMessage,
    read as readConversation,
    store as storeMessage,
    unmute as unmuteConversation,
    update as updateMessage,
} from '@/routes/member/messages';

type PresenceUser = { id: number; name: string; avatar: string | null };

/**
 * The chat thread — realtime over the room's presence channel (messages,
 * reactions, reads, online roster, typing whispers), cursor-paged
 * history, optimistic sends, and read receipts.
 */
export default function MemberMessagesShow({
    conversation: initialConversation,
    participants: initialParticipants,
    messages: initialMessages,
    next_cursor: initialCursor,
}: {
    conversation: ConversationMeta;
    participants: ChatParticipant[];
    messages: ChatMessage[];
    next_cursor: number | null;
}) {
    const { t } = useTranslation();
    const initials = useInitials();
    const { auth } = usePage().props;
    const me = auth.user!.id;
    const myName = auth.user!.username ?? auth.user!.name;

    const [conversation, setConversation] = useState(initialConversation);
    const [participants, setParticipants] =
        useState<ChatParticipant[]>(initialParticipants);
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [cursor, setCursor] = useState<number | null>(initialCursor);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const [online, setOnline] = useState<Set<number>>(new Set());
    const [typing, setTyping] = useState<Map<number, string>>(new Map());
    const [replyingTo, setReplyingTo] = useState<ChatMessage | null>(null);
    const [editing, setEditing] = useState<ChatMessage | null>(null);
    const [sending, setSending] = useState(false);

    const bottomRef = useRef<HTMLDivElement>(null);
    const topSentinelRef = useRef<HTMLDivElement>(null);
    const listRef = useRef<HTMLDivElement>(null);
    const typingTimers = useRef<Map<number, ReturnType<typeof setTimeout>>>(
        new Map(),
    );
    const whisperLast = useRef(0);
    const tempId = useRef(-1);
    const channelRef = useRef<ReturnType<
        ReturnType<typeof echo>['join']
    > | null>(null);

    const cursors = useMemo(
        () =>
            new Map(
                participants.map((p) => [p.id, p.last_read_message_id ?? 0]),
            ),
        [participants],
    );

    const scrollToBottom = useCallback((smooth = true) => {
        bottomRef.current?.scrollIntoView({
            behavior: smooth ? 'smooth' : 'auto',
            block: 'end',
        });
    }, []);

    const markRead = useCallback(
        (messageId: number) => {
            const mine = cursors.get(me) ?? 0;
            if (messageId <= mine) {
                return;
            }
            setParticipants((list) =>
                list.map((p) =>
                    p.id === me ? { ...p, last_read_message_id: messageId } : p,
                ),
            );
            void memberApi(readConversation(conversation.id).url, 'POST', {
                message_id: messageId,
            }).catch(() => {});
        },
        [conversation.id, cursors, me],
    );

    // Presence channel lifecycle.
    useEffect(() => {
        const channel = echo().join(`conversation.${conversation.id}`);
        channelRef.current = channel;

        channel
            .here((users: PresenceUser[]) => {
                setOnline(new Set(users.map((u) => u.id)));
            })
            .joining((user: PresenceUser) => {
                setOnline((set) => new Set(set).add(user.id));
            })
            .leaving((user: PresenceUser) => {
                setOnline((set) => {
                    const next = new Set(set);
                    next.delete(user.id);
                    return next;
                });
            })
            .listen('message.sent', (event: { message: ChatMessage }) => {
                setMessages((list) =>
                    list.some((m) => m.id === event.message.id)
                        ? list
                        : [...list, event.message],
                );
                scrollToBottom();
                markRead(event.message.id);
            })
            .listen('message.updated', (event: { message: ChatMessage }) => {
                setMessages((list) =>
                    list.map((m) =>
                        m.id === event.message.id ? event.message : m,
                    ),
                );
            })
            .listen('message.deleted', (event: { message_id: number }) => {
                setMessages((list) =>
                    list.map((m) =>
                        m.id === event.message_id
                            ? { ...m, deleted: true, body: null }
                            : m,
                    ),
                );
            })
            .listen(
                'reaction.changed',
                (event: { message_id: number; reactions: ChatReaction[] }) => {
                    setMessages((list) =>
                        list.map((m) =>
                            m.id === event.message_id
                                ? { ...m, reactions: event.reactions }
                                : m,
                        ),
                    );
                },
            )
            .listen(
                'messages.read',
                (event: { user_id: number; message_id: number }) => {
                    setParticipants((list) =>
                        list.map((p) =>
                            p.id === event.user_id &&
                            event.message_id > (p.last_read_message_id ?? 0)
                                ? {
                                      ...p,
                                      last_read_message_id: event.message_id,
                                  }
                                : p,
                        ),
                    );
                },
            )
            .listenForWhisper('typing', (user: PresenceUser) => {
                if (user.id === me) {
                    return;
                }
                setTyping((map) => new Map(map).set(user.id, user.name));
                clearTimeout(typingTimers.current.get(user.id));
                typingTimers.current.set(
                    user.id,
                    setTimeout(() => {
                        setTyping((map) => {
                            const next = new Map(map);
                            next.delete(user.id);
                            return next;
                        });
                    }, 3000),
                );
            });

        return () => {
            typingTimers.current.forEach((timer) => clearTimeout(timer));
            echo().leave(`conversation.${conversation.id}`);
            channelRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [conversation.id]);

    // Scroll to the newest message on first render and mark it read.
    useEffect(() => {
        scrollToBottom(false);
        const latest = initialMessages.at(-1);
        if (latest !== undefined) {
            markRead(latest.id);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Load the previous page when the top sentinel scrolls into view.
    useEffect(() => {
        const sentinel = topSentinelRef.current;
        if (sentinel === null || cursor === null) {
            return;
        }

        const observer = new IntersectionObserver(async (entries) => {
            if (!entries[0].isIntersecting || loadingOlder) {
                return;
            }
            setLoadingOlder(true);
            const previousHeight = document.documentElement.scrollHeight;
            try {
                const page = await memberApi<{
                    messages: ChatMessage[];
                    next_cursor: number | null;
                }>(
                    older(conversation.id, {
                        query: { before_id: cursor },
                    }).url,
                    'GET',
                );
                setMessages((list) => [...page.messages, ...list]);
                setCursor(page.next_cursor);
                requestAnimationFrame(() => {
                    window.scrollTo(
                        0,
                        window.scrollY +
                            (document.documentElement.scrollHeight -
                                previousHeight),
                    );
                });
            } catch {
                // Leave the cursor so a later scroll retries.
            } finally {
                setLoadingOlder(false);
            }
        });

        observer.observe(sentinel);
        return () => observer.disconnect();
    }, [conversation.id, cursor, loadingOlder]);

    // Typing whisper, throttled to one every 2s.
    function signalTyping() {
        const now = Date.now();
        if (now - whisperLast.current < 2000) {
            return;
        }
        whisperLast.current = now;
        channelRef.current?.whisper('typing', { id: me, name: myName });
    }

    async function send(body: string, files: File[]) {
        if (editing !== null) {
            setSending(true);
            try {
                const { message } = await memberApi<{ message: ChatMessage }>(
                    updateMessage({
                        conversation: conversation.id,
                        message: editing.id,
                    }).url,
                    'PATCH',
                    { body },
                );
                setMessages((list) =>
                    list.map((m) => (m.id === message.id ? message : m)),
                );
                setEditing(null);
            } catch {
                toast.error(t('member.chat.send_failed'));
            } finally {
                setSending(false);
            }
            return;
        }

        const optimistic: ChatMessage = {
            id: tempId.current--,
            conversation_id: conversation.id,
            user_id: me,
            sender: { id: me, name: myName, avatar: auth.user!.avatar ?? null },
            body: body === '' ? null : body,
            deleted: false,
            edited: false,
            reply_to:
                replyingTo !== null
                    ? {
                          id: replyingTo.id,
                          sender_name: replyingTo.sender.name,
                          snippet: replyingTo.body,
                      }
                    : null,
            attachments: [],
            reactions: [],
            created_at: new Date().toISOString(),
        };

        setMessages((list) => [...list, optimistic]);
        setReplyingTo(null);
        scrollToBottom();
        setSending(true);

        const form = new FormData();
        if (body !== '') {
            form.append('body', body);
        }
        if (optimistic.reply_to !== null) {
            form.append('reply_to_id', String(optimistic.reply_to.id));
        }
        files.forEach((file) => form.append('attachments[]', file));

        try {
            const { message } = await memberApiForm<{ message: ChatMessage }>(
                storeMessage(conversation.id).url,
                form,
            );
            setMessages((list) =>
                list.map((m) => (m.id === optimistic.id ? message : m)),
            );
        } catch {
            setMessages((list) => list.filter((m) => m.id !== optimistic.id));
            toast.error(t('member.chat.send_failed'));
        } finally {
            setSending(false);
        }
    }

    async function remove(message: ChatMessage) {
        try {
            await memberApi(
                destroyMessage({
                    conversation: conversation.id,
                    message: message.id,
                }).url,
                'DELETE',
            );
            setMessages((list) =>
                list.map((m) =>
                    m.id === message.id
                        ? { ...m, deleted: true, body: null }
                        : m,
                ),
            );
        } catch {
            toast.error(t('member.chat.send_failed'));
        }
    }

    async function react(message: ChatMessage, emoji: string) {
        try {
            const { reactions } = await memberApi<{
                reactions: ChatReaction[];
            }>(
                reactMessage({
                    conversation: conversation.id,
                    message: message.id,
                }).url,
                'POST',
                { emoji },
            );
            setMessages((list) =>
                list.map((m) =>
                    m.id === message.id ? { ...m, reactions } : m,
                ),
            );
        } catch {
            toast.error(t('member.chat.send_failed'));
        }
    }

    async function toggleMute() {
        const route = conversation.muted
            ? unmuteConversation
            : muteConversation;
        const { muted } = await memberApi<{ muted: boolean }>(
            route(conversation.id).url,
            'POST',
        );
        setConversation((c) => ({ ...c, muted }));
    }

    function jumpTo(messageId: number) {
        document
            .getElementById(`msg-${messageId}`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    const lastMineId = [...messages]
        .reverse()
        .find((m) => m.user_id === me && !m.deleted)?.id;

    function isSeen(message: ChatMessage): boolean {
        if (message.user_id !== me) {
            return false;
        }
        const others = participants.filter((p) => p.id !== me);
        if (others.length === 0) {
            return false;
        }
        return others.every((p) => (cursors.get(p.id) ?? 0) >= message.id);
    }

    const onlineCount = online.size;
    const typingNames = [...typing.values()];
    const showSender = conversation.type !== 'dm';

    return (
        <MobileShell hideNav>
            <Head title={conversation.title} />

            <div className="sticky top-0 z-10 -mx-4 flex items-center gap-3 border-b border-[#1C1C1E] bg-[#0A0A0A]/95 px-4 py-2.5 backdrop-blur-md">
                <Link
                    href={messagesIndex().url}
                    aria-label={t('member.chats.back')}
                    className="flex size-9 shrink-0 items-center justify-center rounded-full border border-[#2E2E2E] text-[#8E8E93] hover:text-[#F5F5F5]"
                >
                    <ArrowLeft className="size-4" />
                </Link>
                <Avatar className="size-9 shrink-0 border border-[#2E2E2E]">
                    <AvatarImage
                        src={conversation.avatar ?? undefined}
                        alt={conversation.title}
                    />
                    <AvatarFallback className="bg-[#1C1C1E] text-[10px] font-bold text-[#AFFA01]">
                        {conversation.type === 'gym' ? (
                            <Dumbbell className="size-4" />
                        ) : conversation.type === 'group' ? (
                            <Users className="size-4" />
                        ) : (
                            initials(conversation.title)
                        )}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold text-[#F5F5F5]">
                        {conversation.title}
                    </p>
                    <p className="truncate text-[11px] text-[#8E8E93]">
                        {typingNames.length > 0
                            ? `${typingNames.join(', ')} ${t('member.chat.typing')}`
                            : conversation.type === 'dm'
                              ? online.has(
                                    participants.find((p) => p.id !== me)?.id ??
                                        -1,
                                )
                                  ? t('member.chat.online')
                                  : t('member.chat.offline')
                              : `${onlineCount} ${t('member.chat.online_count')}`}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={toggleMute}
                    aria-label={
                        conversation.muted
                            ? t('member.chat.unmute')
                            : t('member.chat.mute')
                    }
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-full border',
                        conversation.muted
                            ? 'border-[#AFFA01]/50 text-[#AFFA01]'
                            : 'border-[#2E2E2E] text-[#8E8E93] hover:text-[#F5F5F5]',
                    )}
                >
                    {conversation.muted ? (
                        <BellOff className="size-4" />
                    ) : (
                        <Bell className="size-4" />
                    )}
                </button>
            </div>

            <div ref={listRef} className="flex flex-col gap-3 py-4">
                <div ref={topSentinelRef} className="h-px" />
                {loadingOlder && (
                    <p className="text-center text-[11px] text-[#8E8E93]">
                        {t('member.chat.loading')}
                    </p>
                )}
                {cursor === null && messages.length > 0 && (
                    <p className="pb-2 text-center text-[11px] text-[#8E8E93]">
                        {t('member.chat.start')}
                    </p>
                )}
                {messages.map((message, index) => {
                    const previous = messages[index - 1];
                    const firstOfRun =
                        previous === undefined ||
                        previous.user_id !== message.user_id ||
                        previous.deleted;

                    return (
                        <div key={message.id} id={`msg-${message.id}`}>
                            <MessageBubble
                                message={message}
                                mine={message.user_id === me}
                                viewerId={me}
                                showSender={showSender && firstOfRun}
                                seen={
                                    message.id === lastMineId && isSeen(message)
                                }
                                canDelete={
                                    message.user_id === me ||
                                    conversation.can_moderate
                                }
                                onReply={setReplyingTo}
                                onEdit={setEditing}
                                onDelete={remove}
                                onReact={react}
                                onJump={jumpTo}
                            />
                        </div>
                    );
                })}
                <div ref={bottomRef} />
            </div>

            <div className="sticky bottom-3 z-10">
                <MessageComposer
                    replyingTo={replyingTo}
                    editing={editing}
                    sending={sending}
                    onSend={send}
                    onCancelContext={() => {
                        setReplyingTo(null);
                        setEditing(null);
                    }}
                    onTyping={signalTyping}
                />
            </div>
        </MobileShell>
    );
}
