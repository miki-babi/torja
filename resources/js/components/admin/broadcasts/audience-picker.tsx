import { Search, Users, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { xsrfToken } from '@/components/member/member-api';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    audienceCount as audienceCountRoute,
    usersSearch,
} from '@/routes/admin/broadcasts';
import type {
    BroadcastAudienceConfig,
    BroadcastSegment,
    BroadcastUserHit,
} from '@/types';

/**
 * Broadcast targeting: everyone, any mix of user_traits segments, and
 * hand-picked Telegram-linked users. Debounced live recipient count is
 * fetched from the audience-count endpoint.
 */
export function AudiencePicker({
    value,
    onChange,
    segments,
    linkedCount,
    pickedUsers,
    onPickUser,
    onRemoveUser,
}: {
    value: BroadcastAudienceConfig;
    onChange: (audience: BroadcastAudienceConfig) => void;
    segments: BroadcastSegment[];
    linkedCount: number;
    pickedUsers: BroadcastUserHit[];
    onPickUser: (user: BroadcastUserHit) => void;
    onRemoveUser: (id: number) => void;
}) {
    const [count, setCount] = useState<number | null>(null);
    const [query, setQuery] = useState('');
    const [hits, setHits] = useState<BroadcastUserHit[]>([]);
    const latest = useRef(0);

    // Live recipient estimate for the current audience config.
    useEffect(() => {
        const request = ++latest.current;
        const timer = setTimeout(() => {
            fetch(audienceCountRoute().url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ audience: value }),
            })
                .then((r) => (r.ok ? r.json() : null))
                .then((body: { count?: number } | null) => {
                    if (request === latest.current && body !== null) {
                        setCount(body.count ?? null);
                    }
                })
                .catch(() => {});
        }, 350);

        return () => clearTimeout(timer);
    }, [value]);

    // User search for the picker.
    useEffect(() => {
        if (query.trim() === '') {
            setHits([]);
            return;
        }
        const timer = setTimeout(() => {
            fetch(usersSearch({ query: { q: query } }).url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then((r) => (r.ok ? r.json() : null))
                .then((body: { users?: BroadcastUserHit[] } | null) => {
                    setHits(body?.users ?? []);
                })
                .catch(() => {});
        }, 300);

        return () => clearTimeout(timer);
    }, [query]);

    function toggleSegment(key: string, checked: boolean) {
        const segments = checked
            ? [...value.segments, key]
            : value.segments.filter((k) => k !== key);
        onChange({ ...value, segments });
    }

    return (
        <div className="space-y-4">
            <label className="flex items-center gap-2 text-sm font-medium">
                <Checkbox
                    checked={value.everyone}
                    onCheckedChange={(v) =>
                        onChange({ ...value, everyone: v === true })
                    }
                />
                Everyone on Telegram
                <span className="text-xs font-normal text-muted-foreground">
                    ({linkedCount} linked)
                </span>
            </label>

            <div className="space-y-1">
                <p className="text-xs font-semibold text-muted-foreground">
                    Segments
                </p>
                <div className="grid gap-1 rounded-lg border border-input p-3 sm:grid-cols-2">
                    {segments.map((segment) => (
                        <label
                            key={segment.key}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                disabled={value.everyone}
                                checked={value.segments.includes(segment.key)}
                                onCheckedChange={(v) =>
                                    toggleSegment(segment.key, v === true)
                                }
                            />
                            <span className="flex-1">{segment.label}</span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {segment.count}
                            </span>
                        </label>
                    ))}
                </div>
            </div>

            <div className="space-y-2">
                <p className="text-xs font-semibold text-muted-foreground">
                    Specific users
                </p>
                <div className="relative">
                    <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input
                        className="pl-8"
                        placeholder="Search name, @telegram, phone…"
                        value={query}
                        disabled={value.everyone}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                </div>
                {hits.length > 0 && (
                    <div className="rounded-lg border border-input">
                        {hits.map((hit) => (
                            <button
                                key={hit.id}
                                type="button"
                                className="flex w-full items-center justify-between px-3 py-2 text-sm hover:bg-accent disabled:opacity-50"
                                disabled={value.user_ids.includes(hit.id)}
                                onClick={() => {
                                    onPickUser(hit);
                                    setQuery('');
                                    setHits([]);
                                }}
                            >
                                <span>{hit.label}</span>
                                <span className="text-xs text-muted-foreground">
                                    {hit.detail}
                                </span>
                            </button>
                        ))}
                    </div>
                )}
                {pickedUsers.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {pickedUsers.map((user) => (
                            <Badge
                                key={user.id}
                                variant="secondary"
                                className="gap-1"
                            >
                                {user.label}
                                <button
                                    type="button"
                                    aria-label={`Remove ${user.label}`}
                                    onClick={() => onRemoveUser(user.id)}
                                >
                                    <X className="size-3" />
                                </button>
                            </Badge>
                        ))}
                    </div>
                )}
            </div>

            <div className="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2 text-sm">
                <Users className="size-4 text-muted-foreground" />
                {count === null ? (
                    <span className="text-muted-foreground">
                        Counting recipients…
                    </span>
                ) : (
                    <span>
                        <strong className="tabular-nums">{count}</strong>{' '}
                        Telegram-linked recipient{count === 1 ? '' : 's'}
                    </span>
                )}
            </div>
        </div>
    );
}
