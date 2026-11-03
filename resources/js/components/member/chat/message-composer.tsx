import { FileText, Loader2, Paperclip, Send, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { formatBytes, type ChatMessage } from '@/components/member/chat/types';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const ACCEPT =
    'image/jpeg,image/png,image/webp,image/gif,.pdf,.txt,.zip,.doc,.docx,.xls,.xlsx';

/**
 * Bottom composer: auto-growing textarea, attachment picker with a
 * preview strip, reply/edit banners, and a send button. Enter sends,
 * Shift+Enter inserts a newline.
 */
export function MessageComposer({
    replyingTo,
    editing,
    sending,
    onSend,
    onCancelContext,
    onTyping,
}: {
    replyingTo: ChatMessage | null;
    editing: ChatMessage | null;
    sending: boolean;
    onSend: (body: string, files: File[]) => void;
    onCancelContext: () => void;
    onTyping: () => void;
}) {
    const { t } = useTranslation();
    const [body, setBody] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [previews, setPreviews] = useState<string[]>([]);
    const inputRef = useRef<HTMLTextAreaElement>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    // Pre-fill the draft when entering edit mode.
    useEffect(() => {
        if (editing !== null) {
            setBody(editing.body ?? '');
            inputRef.current?.focus();
        }
    }, [editing]);

    // Revoke object URLs when the file list changes or on unmount.
    useEffect(() => {
        const urls = files
            .filter((f) => f.type.startsWith('image/'))
            .map((f) => URL.createObjectURL(f));
        setPreviews(urls);
        return () => urls.forEach((url) => URL.revokeObjectURL(url));
    }, [files]);

    const contextMessage = editing ?? replyingTo;

    function submit() {
        const text = body.trim();
        if ((text === '' && files.length === 0) || sending) {
            return;
        }
        onSend(text, files);
        setBody('');
        setFiles([]);
        inputRef.current?.focus();
    }

    return (
        <div className="space-y-2">
            {contextMessage !== null && (
                <div className="flex items-center gap-2 rounded-xl border border-[#2E2E2E] bg-[#101010] px-3 py-2">
                    <div className="min-w-0 flex-1 border-l-2 border-[#AFFA01] pl-2">
                        <p className="text-[10px] font-bold text-[#AFFA01] uppercase">
                            {editing !== null
                                ? t('member.chat.editing')
                                : contextMessage.sender.name}
                        </p>
                        <p className="truncate text-xs text-[#8E8E93]">
                            {contextMessage.body ?? t('member.chat.attachment')}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            onCancelContext();
                            if (editing !== null) {
                                setBody('');
                            }
                        }}
                        className="p-1 text-[#8E8E93] hover:text-[#F5F5F5]"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            )}

            {files.length > 0 && (
                <div className="flex gap-2 overflow-x-auto pb-1">
                    {files.map((file, i) => (
                        <div
                            key={`${file.name}-${i}`}
                            className="relative shrink-0"
                        >
                            {file.type.startsWith('image/') ? (
                                <img
                                    src={previews[i]}
                                    alt={file.name}
                                    className="size-16 rounded-xl border border-[#2E2E2E] object-cover"
                                />
                            ) : (
                                <div className="flex h-16 w-36 flex-col justify-center gap-0.5 rounded-xl border border-[#2E2E2E] bg-[#101010] px-2">
                                    <FileText className="size-4 text-[#AFFA01]" />
                                    <p className="truncate text-[10px] font-semibold text-[#F5F5F5]">
                                        {file.name}
                                    </p>
                                    <p className="text-[9px] text-[#8E8E93]">
                                        {formatBytes(file.size)}
                                    </p>
                                </div>
                            )}
                            <button
                                type="button"
                                onClick={() =>
                                    setFiles((list) =>
                                        list.filter((_, j) => j !== i),
                                    )
                                }
                                className="absolute -top-1.5 -right-1.5 rounded-full border border-[#2E2E2E] bg-[#1C1C1E] p-0.5 text-[#8E8E93] hover:text-white"
                            >
                                <X className="size-3" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            <div className="flex items-end gap-2">
                <input
                    ref={fileRef}
                    type="file"
                    accept={ACCEPT}
                    multiple
                    hidden
                    onChange={(e) => {
                        const picked = Array.from(e.target.files ?? []).slice(
                            0,
                            5,
                        );
                        setFiles((list) => [...list, ...picked].slice(0, 5));
                        e.target.value = '';
                    }}
                />
                <button
                    type="button"
                    onClick={() => fileRef.current?.click()}
                    aria-label={t('member.chat.attach')}
                    className="flex size-10 shrink-0 items-center justify-center rounded-full border border-[#2E2E2E] text-[#8E8E93] transition-colors hover:border-[#3a3a3a] hover:text-[#F5F5F5]"
                >
                    <Paperclip className="size-4" />
                </button>
                <textarea
                    ref={inputRef}
                    value={body}
                    rows={1}
                    placeholder={t('member.chat.placeholder')}
                    onChange={(e) => {
                        setBody(e.target.value);
                        onTyping();
                        e.target.style.height = 'auto';
                        e.target.style.height = `${Math.min(e.target.scrollHeight, 120)}px`;
                    }}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            submit();
                        }
                    }}
                    className={cn(
                        'max-h-30 min-h-10 flex-1 resize-none rounded-2xl border border-[#2E2E2E] bg-[#101010] px-3.5 py-2.5 text-sm text-[#F5F5F5]',
                        'placeholder:text-[#666666] focus:border-[#AFFA01]/50 focus:outline-none',
                    )}
                />
                <button
                    type="button"
                    onClick={submit}
                    disabled={
                        sending || (body.trim() === '' && files.length === 0)
                    }
                    aria-label={t('member.chat.send')}
                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#C5FF4D] to-[#AFFA01] text-black transition-opacity disabled:opacity-40"
                >
                    {sending ? (
                        <Loader2 className="size-4 animate-spin" />
                    ) : (
                        <Send className="size-4" />
                    )}
                </button>
            </div>
        </div>
    );
}
