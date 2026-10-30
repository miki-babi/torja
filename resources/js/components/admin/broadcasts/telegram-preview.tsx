import {
    Clapperboard,
    ExternalLink,
    FileText,
    Film,
    Image as ImageIcon,
    MousePointerClick,
    Vote,
} from 'lucide-react';
import type {
    BroadcastButton,
    BroadcastMediaItem,
    BroadcastPoll,
} from '@/types';
import { cn } from '@/lib/utils';

const BUBBLE_HTML_CLS =
    'break-words text-[13px] leading-snug [&_b]:font-bold [&_strong]:font-bold [&_i]:italic [&_em]:italic [&_u]:underline [&_s]:line-through [&_del]:line-through [&_a]:text-sky-400 [&_a]:underline [&_code]:rounded [&_code]:bg-black/30 [&_code]:px-1 [&_code]:font-mono [&_code]:text-[12px] [&_pre]:my-1 [&_pre]:overflow-x-auto [&_pre]:rounded-md [&_pre]:bg-black/30 [&_pre]:p-2 [&_pre]:font-mono [&_pre]:text-[12px] [&_blockquote]:my-1 [&_blockquote]:border-l-2 [&_blockquote]:border-sky-400 [&_blockquote]:pl-2 [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:pl-4 [&_ol]:pl-4 [&_.tg-spoiler]:rounded [&_.tg-spoiler]:bg-white/30 [&_.tg-spoiler]:px-0.5 [&_.tg-spoiler]:text-transparent';

/**
 * Live composer preview styled like a Telegram message bubble: media on
 * top, rich caption below, inline keyboard buttons underneath — plus a
 * poll rendering for the poll message type.
 */
export function TelegramPreview({
    type,
    bodyHtml,
    media,
    buttons,
    poll,
}: {
    type: string;
    bodyHtml: string;
    media: BroadcastMediaItem[];
    buttons: BroadcastButton[][];
    poll: BroadcastPoll | null;
}) {
    const hasBody = bodyHtml !== '' && bodyHtml !== '<p></p>';

    return (
        <div className="space-y-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Preview
            </p>
            <div className="rounded-xl bg-[#0e1621] p-4">
                <div className="max-w-[280px] overflow-hidden rounded-2xl rounded-tl-md bg-[#182533] text-white shadow-lg">
                    <PreviewMedia type={type} media={media} />

                    {type === 'poll' && poll !== null ? (
                        <PollMock poll={poll} />
                    ) : (
                        hasBody && (
                            <div
                                className={cn('px-3 py-2', BUBBLE_HTML_CLS)}
                                // Admin-authored composer HTML, same trust
                                // level as the shop product editor.
                                dangerouslySetInnerHTML={{ __html: bodyHtml }}
                            />
                        )
                    )}

                    <div className="flex justify-end px-3 pb-1">
                        <span className="text-[10px] text-white/40">
                            {new Date().toLocaleTimeString([], {
                                hour: '2-digit',
                                minute: '2-digit',
                            })}
                        </span>
                    </div>
                </div>

                {buttons.some((row) => row.length > 0) && (
                    <div className="mt-0.5 max-w-[280px] space-y-0.5">
                        {buttons.map((row, r) =>
                            row.length === 0 ? null : (
                                <div key={r} className="flex gap-0.5">
                                    {row.map((button, c) => (
                                        <button
                                            key={c}
                                            type="button"
                                            className="flex flex-1 items-center justify-center gap-1 rounded-md bg-[#182533] px-2 py-2 text-[12px] font-medium text-sky-400 hover:bg-[#1f2f3f]"
                                        >
                                            {button.kind === 'url' ||
                                            button.kind === 'web_app' ? (
                                                <ExternalLink className="size-3" />
                                            ) : button.kind === 'callback' ? (
                                                <MousePointerClick className="size-3" />
                                            ) : null}
                                            {button.text || 'Button'}
                                        </button>
                                    ))}
                                </div>
                            ),
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

function PreviewMedia({
    type,
    media,
}: {
    type: string;
    media: BroadcastMediaItem[];
}) {
    const single =
        type === 'photo' ||
        type === 'video' ||
        type === 'document' ||
        type === 'animation';
    const album = type === 'media_group';

    if (!single && !album) {
        return null;
    }

    const items = single ? media.slice(0, 1) : media;

    return (
        <div
            className={cn(
                'grid gap-0.5',
                album && items.length > 1 ? 'grid-cols-2' : 'grid-cols-1',
            )}
        >
            {items.map((item, i) => (
                <MediaThumb key={i} item={item} />
            ))}
        </div>
    );
}

function MediaThumb({ item }: { item: BroadcastMediaItem }) {
    const src =
        item.kind === 'photo'
            ? item.source === 'url'
                ? item.value
                : (item.url ?? null)
            : null;

    if (item.kind === 'photo' && src !== null && src !== '') {
        return (
            <img
                src={src}
                alt=""
                className="h-28 w-full object-cover"
            />
        );
    }

    const Icon =
        item.kind === 'video'
            ? Film
            : item.kind === 'document'
              ? FileText
              : item.kind === 'animation'
                ? Clapperboard
                : ImageIcon;

    return (
        <div className="flex h-24 flex-col items-center justify-center gap-1 bg-white/5 text-xs text-white/70">
            <Icon className="size-6" />
            {item.kind}
        </div>
    );
}

function PollMock({ poll }: { poll: BroadcastPoll }) {
    return (
        <div className="px-3 py-2">
            <div className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-sky-400">
                <Vote className="size-3" />
                {poll.type === 'quiz' ? 'Quiz' : 'Poll'}
                {poll.is_anonymous ? ' · Anonymous' : ''}
            </div>
            <p className="mt-1 text-[13px] font-semibold leading-snug">
                {poll.question !== '' ? poll.question : 'Your question…'}
            </p>
            <div className="mt-2 space-y-1.5">
                {poll.options.map((option, i) => (
                    <div
                        key={i}
                        className="rounded-md border border-white/15 px-2 py-1.5 text-[12px] text-white/80"
                    >
                        {option !== '' ? option : `Option ${i + 1}`}
                    </div>
                ))}
            </div>
        </div>
    );
}
