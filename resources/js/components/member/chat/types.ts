export type ChatAttachment = {
    id: number;
    url: string;
    kind: 'image' | 'file';
    name: string;
    mime: string;
    size: number;
    width: number | null;
    height: number | null;
};

export type ChatReaction = {
    emoji: string;
    count: number;
    user_ids: number[];
};

export type ChatMessage = {
    id: number;
    conversation_id: number;
    user_id: number;
    sender: { id: number; name: string; avatar: string | null };
    body: string | null;
    deleted: boolean;
    edited: boolean;
    reply_to: {
        id: number;
        sender_name: string;
        snippet: string | null;
    } | null;
    attachments: ChatAttachment[];
    reactions: ChatReaction[];
    created_at: string | null;
};

export type ChatParticipant = {
    id: number;
    name: string;
    avatar: string | null;
    last_read_message_id: number | null;
};

export type ConversationMeta = {
    id: number;
    type: 'dm';
    title: string;
    avatar: string | null;
    can_moderate: boolean;
    muted: boolean;
    back_url: string;
};

export type InboxConversation = {
    id: number;
    type: 'dm';
    title: string;
    avatar: string | null;
    members: number;
    muted: boolean;
    is_coach: boolean;
    unread: number;
    last_message: {
        sender: string;
        mine: boolean;
        snippet: string;
        created_at: string | null;
    } | null;
    activity: string | null;
};

export const REACTION_EMOJIS = ['👍', '❤️', '🔥', '💪', '😂', '🎉'];

export function formatBytes(size: number): string {
    if (size >= 1024 * 1024) {
        return `${(size / (1024 * 1024)).toFixed(1)} MB`;
    }
    if (size >= 1024) {
        return `${Math.round(size / 1024)} KB`;
    }
    return `${size} B`;
}

export function formatMessageTime(iso: string | null): string {
    if (iso === null) {
        return '';
    }
    const date = new Date(iso);
    const today = new Date();
    const sameDay =
        date.getFullYear() === today.getFullYear() &&
        date.getMonth() === today.getMonth() &&
        date.getDate() === today.getDate();

    if (sameDay) {
        return date.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}
