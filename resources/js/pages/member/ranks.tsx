import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Copy,
    Crown,
    Flame,
    MessageCircle,
    Plus,
    Search,
    Trophy,
    UserPlus,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { GymPicker, type GymPick } from '@/components/member/gym-picker';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    search as friendSearch,
    store as friendStore,
} from '@/routes/member/ranks/friends';
import { optIn } from '@/routes/member/ranks';
import {
    join as groupJoin,
    search as groupSearch,
    store as groupStore,
} from '@/routes/member/ranks/groups';
import { messages as chatsIndex } from '@/routes/member';
import { dm as dmStore, show as chatShow } from '@/routes/member/messages';

type Entry = {
    rank: number;
    user_id: number;
    name: string;
    avatar: string | null;
    score: number;
    streak: number;
    is_you: boolean;
};

type MyGroup = {
    id: number;
    name: string;
    invite_code: string;
    members: number;
    is_owner: boolean;
    chat_id: number | null;
};

type JoinableGroup = { id: number; name: string; members: number };

function PodiumMember({
    entry,
    unit,
    big = false,
    onChat,
}: {
    entry: Entry;
    unit: string;
    big?: boolean;
    onChat?: (entry: Entry) => void;
}) {
    const initials = useInitials();
    const chatButton = onChat !== undefined && !entry.is_you;
    return (
        <div className="flex w-24 flex-col items-center gap-1.5">
            {big ? (
                <Crown className="size-5 text-[#FFD700]" />
            ) : (
                <span className="size-5" />
            )}
            <div className="relative">
                <button
                    type="button"
                    disabled={!chatButton}
                    onClick={() => onChat?.(entry)}
                    className={chatButton ? 'cursor-pointer' : 'cursor-default'}
                >
                    <Avatar
                        className={cn(
                            'border-2 border-[#2E2E2E]',
                            big ? 'size-20' : 'size-14',
                        )}
                    >
                        <AvatarImage
                            src={entry.avatar ?? undefined}
                            alt={entry.name}
                        />
                        <AvatarFallback className={big ? 'text-xl' : 'text-sm'}>
                            {initials(entry.name)}
                        </AvatarFallback>
                    </Avatar>
                </button>
                <span
                    className={cn(
                        'absolute -right-1 -bottom-1 flex size-6 items-center justify-center rounded-full border-2 border-[#141414] text-[11px] font-extrabold',
                        entry.rank === 1
                            ? 'bg-[#FFD700] text-black'
                            : 'bg-[#3A3A3A] text-white',
                    )}
                >
                    {entry.rank}
                </span>
            </div>
            <p className="w-full truncate text-center text-xs font-bold text-[#F5F5F5]">
                {entry.name}
            </p>
            <p className="text-[11px] font-semibold text-[#8E8E93]">
                {entry.score.toLocaleString()} {unit}
            </p>
        </div>
    );
}

function ListRow({
    entry,
    unit,
    onChat,
}: {
    entry: Entry;
    unit: string;
    onChat?: (entry: Entry) => void;
}) {
    const initials = useInitials();
    return (
        <div
            className={cn(
                'flex items-center gap-3 py-3',
                entry.is_you && '-mx-2 rounded-xl bg-[#AFFA01]/5 px-2',
            )}
        >
            <span className="w-6 text-center text-sm font-bold text-[#8E8E93]">
                {entry.rank}
            </span>
            <Avatar className="size-10 border border-[#2E2E2E]">
                <AvatarImage src={entry.avatar ?? undefined} alt={entry.name} />
                <AvatarFallback className="text-xs">
                    {initials(entry.name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-[#F5F5F5]">
                    {entry.name}
                </p>
                <p className="text-xs text-[#8E8E93]">
                    {entry.score.toLocaleString()} {unit}
                </p>
            </div>
            {entry.streak > 0 && (
                <span className="flex items-center gap-1 text-xs font-bold text-[#FFB800]">
                    <Flame className="size-4" />
                    {entry.streak}d
                </span>
            )}
            {onChat !== undefined && !entry.is_you && (
                <button
                    type="button"
                    onClick={() => onChat(entry)}
                    aria-label={entry.name}
                    className="rounded-full border border-[#2E2E2E] p-1.5 text-[#8E8E93] transition-colors hover:border-[#AFFA01]/50 hover:text-[#AFFA01]"
                >
                    <MessageCircle className="size-4" />
                </button>
            )}
        </div>
    );
}

function RankList({
    entries,
    you,
    scopeLabel,
    metric,
    onChat,
}: {
    entries: Entry[];
    you: Entry | null;
    scopeLabel: string;
    metric: 'sessions' | 'volume';
    onChat?: (entry: Entry) => void;
}) {
    const { t } = useTranslation();
    const initials = useInitials();
    const unit = metric === 'sessions' ? t('member.ranks.workouts') : 'kg';
    if (entries.length === 0) {
        return (
            <p className="py-6 text-center text-sm text-muted-foreground">
                {t('member.ranks.empty')}
            </p>
        );
    }
    const podium = entries.slice(0, 3);
    const rest = entries.slice(3);
    return (
        <div>
            <div className="rounded-2xl bg-[#1F1F1F] p-4 pb-5">
                <p className="text-center text-[10px] font-extrabold tracking-widest text-[#8E8E93] uppercase">
                    {t('member.ranks.top_performers')} · {scopeLabel}
                </p>
                <div className="mt-4 flex items-end justify-center gap-4">
                    {podium[1] !== undefined && (
                        <PodiumMember
                            entry={podium[1]}
                            unit={unit}
                            onChat={onChat}
                        />
                    )}
                    <PodiumMember
                        entry={podium[0]}
                        unit={unit}
                        big
                        onChat={onChat}
                    />
                    {podium[2] !== undefined && (
                        <PodiumMember
                            entry={podium[2]}
                            unit={unit}
                            onChat={onChat}
                        />
                    )}
                </div>
            </div>

            {rest.length > 0 && (
                <div className="mt-2 divide-y divide-[#2E2E2E] rounded-2xl bg-[#1A1A1A] px-4">
                    {rest.map((e) => (
                        <ListRow
                            key={e.user_id}
                            entry={e}
                            unit={unit}
                            onChat={onChat}
                        />
                    ))}
                </div>
            )}

            {you !== null && (
                <div className="mt-3 flex items-center gap-2.5 rounded-full border border-[#2E2E2E] bg-[#101010] px-3 py-2">
                    <span className="flex h-7 min-w-7 items-center justify-center rounded-full bg-[#AFFA01] px-1.5 text-xs font-extrabold text-black">
                        #{you.rank}
                    </span>
                    <Avatar className="size-7">
                        <AvatarImage
                            src={you.avatar ?? undefined}
                            alt={you.name}
                        />
                        <AvatarFallback className="text-[10px]">
                            {initials(you.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="text-sm font-bold text-[#F5F5F5]">
                        You
                    </span>
                    <span className="ml-auto text-sm font-bold text-[#F5F5F5]">
                        {you.score.toLocaleString()}
                        <span className="ml-1 text-[10px] font-medium text-[#8E8E93]">
                            {unit}
                        </span>
                    </span>
                </div>
            )}
        </div>
    );
}

function AddFriendDialog({
    open,
    onOpenChange,
    onSent,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    onSent: () => void;
}) {
    const { t } = useTranslation();
    const initials = useInitials();
    const [q, setQ] = useState('');
    const [users, setUsers] = useState<
        { id: number; name: string; avatar: string | null }[]
    >([]);

    async function search(value: string) {
        setQ(value);
        if (value.trim() === '') {
            setUsers([]);
            return;
        }
        const response = await fetch(
            friendSearch({ query: { q: value } }).url,
            {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            },
        );
        const data = (await response.json()) as {
            users: { id: number; name: string; avatar: string | null }[];
        };
        setUsers(data.users);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{t('member.ranks.add_friend')}</DialogTitle>
                </DialogHeader>
                <Input
                    value={q}
                    onChange={(e) => void search(e.target.value)}
                    placeholder={t('member.ranks.search_users')}
                    autoFocus
                />
                <div className="max-h-64 divide-y divide-[#2E2E2E] overflow-y-auto rounded-xl border border-[#2E2E2E]">
                    {users.map((u) => (
                        <div
                            key={u.id}
                            className="flex items-center justify-between gap-2 px-3.5 py-2.5"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <Avatar className="size-8">
                                    <AvatarImage
                                        src={u.avatar ?? undefined}
                                        alt={u.name}
                                    />
                                    <AvatarFallback className="text-[10px]">
                                        {initials(u.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="truncate text-sm font-medium text-[#F5F5F5]">
                                    {u.name}
                                </span>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={async () => {
                                    await memberApi(friendStore().url, 'POST', {
                                        user_id: u.id,
                                    });
                                    setUsers((list) =>
                                        list.filter((x) => x.id !== u.id),
                                    );
                                    onSent();
                                }}
                            >
                                <UserPlus className="size-4" />
                            </Button>
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}

export default function MemberRanks({
    optedIn: initialOptedIn,
    scope: initialScope,
    entries: initialEntries,
    groups: initialGroups,
    selectedGroup: initialSelected,

    joinableGroups: initialJoinable,
    gym: initialGym,
    gymChatId: initialGymChatId,
    period: initialPeriod,
    metric,
    you,
    hasLocation,
}: {
    optedIn: boolean;
    scope: 'friends' | 'groups' | 'gym';
    period: 'today' | 'week' | 'all';
    metric: 'sessions' | 'volume';
    entries: Entry[];
    groups: MyGroup[];
    selectedGroup?: number;

    joinableGroups: JoinableGroup[];
    gym: GymPick | null;
    gymChatId?: number;
    you: Entry | null;
    hasLocation: boolean;
}) {
    const { t } = useTranslation();
    const { unreadChats } = usePage().props;
    const [optedIn, setOptedIn] = useState(initialOptedIn);
    const [scope, setScope] = useState(initialScope);
    const [entries] = useState(initialEntries);
    const [groups, setGroups] = useState(initialGroups);
    const [selectedGroup, setSelectedGroup] = useState<number | undefined>(
        initialSelected,
    );

    const [joinable, setJoinable] = useState(initialJoinable);
    const [groupQuery, setGroupQuery] = useState('');
    const [groupResults, setGroupResults] = useState<JoinableGroup[] | null>(
        null,
    );
    const [gym, setGym] = useState<GymPick | null>(initialGym);
    const [gymChatId, setGymChatId] = useState<number | undefined>(
        initialGymChatId,
    );
    const [friendOpen, setFriendOpen] = useState(false);
    const [groupOpen, setGroupOpen] = useState(false);
    const [groupName, setGroupName] = useState('');
    const [joinCode, setJoinCode] = useState('');

    const [period, setPeriod] = useState(initialPeriod);
    const SCOPES = ['friends', 'groups', 'gym'] as const;
    const PERIODS = ['today', 'week', 'all'] as const;

    function visit(nextScope: string, nextPeriod: string, group?: number) {
        let url = `/member/ranks?scope=${nextScope}&period=${nextPeriod}`;
        if (group !== undefined) {
            url += `&group=${group}`;
        }
        router.visit(url, { preserveState: false, preserveScroll: true });
    }

    function changeScope(next: string) {
        setScope(next as typeof scope);
        visit(next, period);
    }

    function changePeriod(next: string) {
        setPeriod(next as typeof period);
        visit(scope, next, selectedGroup);
    }

    async function joinRanks() {
        const { optedIn: now } = await memberApi<{ optedIn: boolean }>(
            optIn().url,
            'POST',
        );
        setOptedIn(now);
        if (now) {
            router.visit('/member/ranks', { preserveState: false });
        }
    }

    // Debounced live search: results replace the suggestion lists while a
    // query is typed; empty query restores the server-provided defaults.
    useEffect(() => {
        if (groupQuery.trim() === '') {
            setGroupResults(null);
            return;
        }
        const timer = setTimeout(async () => {
            const response = await fetch(
                groupSearch({ query: { q: groupQuery } }).url,
                {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                },
            );
            const data = (await response.json()) as {
                groups: JoinableGroup[];
            };
            setGroupResults(data.groups);
        }, 250);
        return () => clearTimeout(timer);
    }, [groupQuery]);

    async function joinGroupCard(group: JoinableGroup) {
        try {
            await memberApi(groupJoin().url, 'POST', { group_id: group.id });
            setJoinable((list) => list.filter((x) => x.id !== group.id));
            setGroupResults(
                (list) => list?.filter((x) => x.id !== group.id) ?? null,
            );
            visit('groups', period, group.id);
        } catch {
            toast.error(t('member.ranks.bad_code'));
        }
    }

    async function createGroup() {
        if (groupName.trim() === '') {
            return;
        }
        const { group, chat_id } = await memberApi<{
            group: MyGroup & { members?: number };
            chat_id: number;
        }>(groupStore().url, 'POST', { name: groupName.trim() });
        setGroups((list) => [
            ...list,
            { ...group, members: 1, is_owner: true, chat_id },
        ]);
        setSelectedGroup(group.id);
        setGroupName('');
        setGroupOpen(false);
        changeScope('groups');
    }

    async function joinGroup() {
        if (joinCode.trim() === '') {
            return;
        }
        try {
            await memberApi(groupJoin().url, 'POST', {
                code: joinCode.trim(),
            });
            setJoinCode('');
            changeScope('groups');
        } catch {
            toast.error(t('member.ranks.bad_code'));
        }
    }

    async function openDm(entry: Entry) {
        try {
            const { conversation_id } = await memberApi<{
                conversation_id: number;
            }>(dmStore().url, 'POST', { user_id: entry.user_id });
            router.visit(chatShow(conversation_id).url);
        } catch {
            toast.error(t('member.chats.dm_failed'));
        }
    }

    return (
        <MobileShell>
            <Head title={t('member.ranks.title')} />

            <div className="flex items-center justify-between pt-2">
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('member.ranks.title')}
                </h1>
                <div className="flex items-center gap-3">
                    <Link
                        href={chatsIndex().url}
                        aria-label={t('member.chats.title')}
                        className="relative flex size-9 items-center justify-center rounded-full border border-[#2E2E2E] text-[#8E8E93] transition-colors hover:text-[#F5F5F5]"
                    >
                        <MessageCircle className="size-4" />
                        {unreadChats > 0 && (
                            <span className="absolute -top-1 -right-1 flex min-w-4.5 items-center justify-center rounded-full bg-[#AFFA01] px-1 text-[9px] font-extrabold text-black">
                                {unreadChats > 99 ? '99+' : unreadChats}
                            </span>
                        )}
                    </Link>
                </div>
            </div>

            {!optedIn ? (
                <div className="mt-5 space-y-4">
                    <Link
                        href={chatsIndex().url}
                        className="flex items-center gap-3 rounded-2xl border border-[#2E2E2E] bg-[#101010] px-4 py-3.5"
                    >
                        <span className="flex size-10 items-center justify-center rounded-full bg-[#AFFA01]/10 text-[#AFFA01]">
                            <MessageCircle className="size-5" />
                        </span>
                        <span className="flex-1 text-sm font-bold text-[#F5F5F5]">
                            {t('member.chats.title')}
                        </span>
                        {unreadChats > 0 && (
                            <span className="flex min-w-6 items-center justify-center rounded-full bg-[#AFFA01] px-1.5 text-[11px] font-extrabold text-black">
                                {unreadChats > 99 ? '99+' : unreadChats}
                            </span>
                        )}
                    </Link>
                    <div className="relative overflow-hidden rounded-2xl border border-[#2E2E2E]">
                        <img
                            src="/images/rank.png"
                            alt=""
                            className="h-72 w-full object-cover"
                        />
                        <div className="absolute inset-0 bg-gradient-to-t from-black/95 via-black/40 to-transparent" />
                        <div className="absolute inset-x-0 bottom-0 p-4">
                            <p className="text-lg font-bold text-white">
                                {t('member.ranks.join_title')}
                            </p>
                            <p className="mt-0.5 text-xs text-white/70">
                                {t('member.ranks.join_hint')}
                            </p>
                            <Button
                                onClick={joinRanks}
                                className="mt-3 w-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black hover:brightness-105"
                            >
                                <Trophy className="size-4" />
                                {t('member.ranks.join')}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : (
                <>
                    <div className="mt-4 flex gap-2">
                        {SCOPES.map((s) => (
                            <button
                                key={s}
                                type="button"
                                onClick={() => changeScope(s)}
                                className={cn(
                                    'flex-1 rounded-xl border px-3 py-2 text-xs font-bold tracking-wider uppercase transition-colors',
                                    scope === s
                                        ? 'border-transparent bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] text-black'
                                        : 'border-[#2E2E2E] text-[#8E8E93]',
                                )}
                            >
                                {t(`member.ranks.scope.${s}`)}
                            </button>
                        ))}
                    </div>

                    {/* Period pills */}
                    <div className="mt-2 flex gap-1.5 rounded-xl border border-[#2E2E2E] bg-[#101010] p-1">
                        {PERIODS.map((p) => (
                            <button
                                key={p}
                                type="button"
                                onClick={() => changePeriod(p)}
                                className={cn(
                                    'flex-1 rounded-lg px-2 py-1.5 text-[11px] font-bold tracking-wider uppercase transition-colors',
                                    period === p
                                        ? 'bg-[#AFFA01] text-black'
                                        : 'text-[#8E8E93] hover:text-[#F5F5F5]',
                                )}
                            >
                                {t(`member.ranks.period.${p}`)}
                            </button>
                        ))}
                    </div>

                    {scope === 'friends' && (
                        <div className="mt-4 space-y-4">
                            <Card>
                                <CardHeader className="flex-row items-center justify-between">
                                    <CardTitle className="text-base">
                                        {t('member.ranks.friends_board')}
                                    </CardTitle>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setFriendOpen(true)}
                                    >
                                        <UserPlus className="size-4" />
                                        {t('member.ranks.add_friend')}
                                    </Button>
                                </CardHeader>
                                <CardContent>
                                    <RankList
                                        entries={entries}
                                        you={you}
                                        metric={metric}
                                        scopeLabel={t(
                                            'member.ranks.scope.friends',
                                        )}
                                        onChat={openDm}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {scope === 'groups' && (
                        <div className="mt-4 space-y-4">
                            <div className="-mx-1 flex scrollbar-none gap-2 overflow-x-auto px-1">
                                {groups.map((g) => (
                                    <button
                                        key={g.id}
                                        type="button"
                                        onClick={() => {
                                            setSelectedGroup(g.id);
                                            visit('groups', period, g.id);
                                        }}
                                        className={cn(
                                            'shrink-0 rounded-xl border px-3 py-1.5 text-xs font-semibold',
                                            selectedGroup === g.id
                                                ? 'border-[#AFFA01]/60 bg-[#AFFA01]/10 text-[#AFFA01]'
                                                : 'border-[#2E2E2E] text-[#8E8E93]',
                                        )}
                                    >
                                        {g.name} · {g.members}
                                    </button>
                                ))}
                            </div>

                            <Card>
                                <CardHeader className="flex-row items-center justify-between">
                                    <CardTitle className="text-base">
                                        {selectedGroup !== undefined
                                            ? groups.find(
                                                  (g) => g.id === selectedGroup,
                                              )?.name
                                            : t('member.ranks.groups')}
                                    </CardTitle>
                                    {selectedGroup !== undefined && (
                                        <div className="flex items-center gap-3">
                                            {groups.find(
                                                (g) => g.id === selectedGroup,
                                            )?.chat_id !== null &&
                                                groups.find(
                                                    (g) =>
                                                        g.id === selectedGroup,
                                                )?.chat_id !== undefined && (
                                                    <Link
                                                        href={
                                                            chatShow(
                                                                groups.find(
                                                                    (g) =>
                                                                        g.id ===
                                                                        selectedGroup,
                                                                )!.chat_id!,
                                                            ).url
                                                        }
                                                        aria-label={t(
                                                            'member.chats.title',
                                                        )}
                                                        className="flex items-center gap-1.5 text-xs font-semibold text-[#AFFA01]"
                                                    >
                                                        <MessageCircle className="size-3.5" />
                                                        {t('member.chats.open')}
                                                    </Link>
                                                )}
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    void navigator.clipboard.writeText(
                                                        groups.find(
                                                            (g) =>
                                                                g.id ===
                                                                selectedGroup,
                                                        )?.invite_code ?? '',
                                                    );
                                                    toast.success(
                                                        t(
                                                            'member.ranks.copied',
                                                        ),
                                                    );
                                                }}
                                                className="flex items-center gap-1.5 text-xs font-semibold text-[#AFFA01]"
                                            >
                                                <Copy className="size-3.5" />
                                                {groups.find(
                                                    (g) =>
                                                        g.id === selectedGroup,
                                                )?.invite_code ??
                                                    t('member.ranks.code')}
                                            </button>
                                        </div>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    <RankList
                                        entries={entries}
                                        you={you}
                                        metric={metric}
                                        scopeLabel={
                                            groups.find(
                                                (g) => g.id === selectedGroup,
                                            )?.name ??
                                            t('member.ranks.scope.groups')
                                        }
                                    />
                                </CardContent>
                            </Card>

                            <div className="grid grid-cols-2 gap-2.5">
                                <Button
                                    variant="outline"
                                    onClick={() => setGroupOpen(true)}
                                >
                                    <Plus className="size-4" />
                                    {t('member.ranks.create_group')}
                                </Button>
                                <div className="flex gap-1.5">
                                    <Input
                                        value={joinCode}
                                        onChange={(e) =>
                                            setJoinCode(
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder={t('member.ranks.code')}
                                        className="h-9"
                                    />
                                    <Button
                                        variant="outline"
                                        onClick={joinGroup}
                                        disabled={joinCode.trim() === ''}
                                    >
                                        {t('member.ranks.join_group')}
                                    </Button>
                                </div>
                            </div>

                            {(joinable.length > 0 || groupResults !== null) && (
                                <div>
                                    <p className="text-[10px] font-extrabold tracking-widest text-[#8E8E93] uppercase">
                                        {t('member.ranks.discover_groups')}
                                    </p>
                                    <div className="relative mt-2">
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#8E8E93]" />
                                        <Input
                                            value={groupQuery}
                                            onChange={(e) =>
                                                setGroupQuery(e.target.value)
                                            }
                                            placeholder={t(
                                                'member.ranks.search_groups',
                                            )}
                                            className="pl-9"
                                        />
                                    </div>
                                    {(() => {
                                        const visible =
                                            groupResults ?? joinable;
                                        return visible.length === 0 &&
                                            groupQuery.trim() !== '' ? (
                                            <p className="mt-3 text-xs text-[#8E8E93]">
                                                {t(
                                                    'member.ranks.search_no_results',
                                                )}
                                            </p>
                                        ) : (
                                            <div className="mt-2 grid grid-cols-2 gap-2.5">
                                                {visible.map((g) => (
                                                    <div
                                                        key={g.id}
                                                        className="rounded-2xl border border-[#2E2E2E] bg-[#1A1A1A] p-3.5"
                                                    >
                                                        <p className="truncate text-sm font-bold text-[#F5F5F5]">
                                                            {g.name}
                                                        </p>
                                                        <p className="mt-0.5 text-xs text-[#8E8E93]">
                                                            {g.members}{' '}
                                                            {t(
                                                                'member.ranks.members',
                                                            )}
                                                        </p>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="mt-2.5 w-full"
                                                            onClick={() =>
                                                                void joinGroupCard(
                                                                    g,
                                                                )
                                                            }
                                                        >
                                                            {t(
                                                                'member.ranks.join_group',
                                                            )}
                                                        </Button>
                                                    </div>
                                                ))}
                                            </div>
                                        );
                                    })()}
                                </div>
                            )}
                        </div>
                    )}

                    {scope === 'gym' && (
                        <div className="mt-4 space-y-4">
                            <Card>
                                <CardContent className="p-4">
                                    <GymPicker
                                        gym={gym}
                                        hasLocation={hasLocation}
                                        onPicked={(picked, chatId) => {
                                            setGym(picked);
                                            setGymChatId(chatId);
                                            visit('gym', period);
                                        }}
                                        onLeft={() => {
                                            setGym(null);
                                            setGymChatId(undefined);
                                            visit('gym', period);
                                        }}
                                    />
                                </CardContent>
                            </Card>
                            {gym !== null && (
                                <Card>
                                    <CardHeader className="flex-row items-center justify-between">
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Crown className="size-4 text-[#AFFA01]" />
                                            {gym.name}
                                        </CardTitle>
                                        {gymChatId !== undefined && (
                                            <Link
                                                href={chatShow(gymChatId).url}
                                                className="flex items-center gap-1.5 text-xs font-semibold text-[#AFFA01]"
                                            >
                                                <MessageCircle className="size-3.5" />
                                                {t('member.chats.open')}
                                            </Link>
                                        )}
                                    </CardHeader>
                                    <CardContent>
                                        <RankList
                                            entries={entries}
                                            you={you}
                                            metric={metric}
                                            scopeLabel={gym.name}
                                        />
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    )}
                </>
            )}

            <AddFriendDialog
                open={friendOpen}
                onOpenChange={setFriendOpen}
                onSent={() => {}}
            />

            <Dialog open={groupOpen} onOpenChange={setGroupOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>
                            {t('member.ranks.create_group')}
                        </DialogTitle>
                    </DialogHeader>
                    <Input
                        value={groupName}
                        onChange={(e) => setGroupName(e.target.value)}
                        placeholder={t('member.ranks.group_name')}
                    />
                    <Button
                        onClick={createGroup}
                        disabled={groupName.trim() === ''}
                        className="w-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black"
                    >
                        {t('member.ranks.create_group')}
                    </Button>
                </DialogContent>
            </Dialog>
        </MobileShell>
    );
}
