import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import {
    Bold,
    Code,
    EyeOff,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    RemoveFormatting,
    SquareCode,
    Strikethrough,
    TextQuote,
    Underline,
} from 'lucide-react';
import { Spoiler } from '@/tiptap/spoiler';
import { cn } from '@/lib/utils';

const EDITOR_CLS =
    'min-h-28 rounded-b-md bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring [&_.tiptap]:outline-none [&_a]:text-lime-400 [&_a]:underline [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:pl-5 [&_ol]:pl-5 [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:font-mono [&_code]:text-xs [&_pre]:rounded-md [&_pre]:bg-muted [&_pre]:p-3 [&_pre]:font-mono [&_pre]:text-xs [&_blockquote]:border-l-2 [&_blockquote]:border-muted-foreground [&_blockquote]:pl-3 [&_blockquote]:italic [&_.tg-spoiler]:rounded [&_.tg-spoiler]:bg-muted-foreground/40 [&_.tg-spoiler]:px-0.5 [&_.tg-spoiler]:text-transparent [&_.tg-spoiler]:selection:bg-accent [&_p.is-editor-empty:first-child::before]:text-muted-foreground [&_p.is-editor-empty:first-child::before]:content-[attr(data-placeholder)] [&_p.is-editor-empty:first-child::before]:float-left [&_p.is-editor-empty:first-child::before]:h-0 [&_p.is-editor-empty:first-child::before]:pointer-events-none';

function ToolButton({
    active,
    onClick,
    label,
    children,
}: {
    active?: boolean;
    onClick: () => void;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            onClick={onClick}
            className={cn(
                'flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                active && 'bg-accent text-foreground',
            )}
        >
            {children}
        </button>
    );
}

/**
 * Telegram-flavored rich text editor — only the formatting Telegram can
 * actually render: bold, italic, underline, strike, spoiler, inline +
 * block code, links, lists and quotes. Emits HTML converted server-side
 * into parse_mode=HTML markup.
 */
export function TelegramEditor({
    value,
    onChange,
    placeholder = 'Write your message…',
}: {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
}) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: false,
                link: { openOnClick: false },
            }),
            Spoiler,
            Placeholder.configure({ placeholder }),
        ],
        content: value,
        onUpdate: ({ editor: e }) => onChange(e.getHTML()),
    });

    function setLink() {
        if (editor === null) {
            return;
        }
        const url = window.prompt('Link URL', 'https://');
        if (url === null) {
            return;
        }
        if (url === '') {
            editor.chain().focus().unsetLink().run();
            return;
        }
        editor.chain().focus().setLink({ href: url }).run();
    }

    return (
        <div className="rounded-md border border-input">
            <div className="flex flex-wrap items-center gap-1 border-b border-input px-2 py-1.5">
                <ToolButton
                    label="Bold"
                    active={editor?.isActive('bold')}
                    onClick={() => editor?.chain().focus().toggleBold().run()}
                >
                    <Bold className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Italic"
                    active={editor?.isActive('italic')}
                    onClick={() =>
                        editor?.chain().focus().toggleItalic().run()
                    }
                >
                    <Italic className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Underline"
                    active={editor?.isActive('underline')}
                    onClick={() =>
                        editor?.chain().focus().toggleUnderline().run()
                    }
                >
                    <Underline className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Strikethrough"
                    active={editor?.isActive('strike')}
                    onClick={() =>
                        editor?.chain().focus().toggleStrike().run()
                    }
                >
                    <Strikethrough className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Spoiler"
                    active={editor?.isActive('spoiler')}
                    onClick={() => editor?.chain().focus().toggleSpoiler().run()}
                >
                    <EyeOff className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Inline code"
                    active={editor?.isActive('code')}
                    onClick={() => editor?.chain().focus().toggleCode().run()}
                >
                    <Code className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Code block"
                    active={editor?.isActive('codeBlock')}
                    onClick={() =>
                        editor?.chain().focus().toggleCodeBlock().run()
                    }
                >
                    <SquareCode className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Quote"
                    active={editor?.isActive('blockquote')}
                    onClick={() =>
                        editor?.chain().focus().toggleBlockquote().run()
                    }
                >
                    <TextQuote className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Bullet list"
                    active={editor?.isActive('bulletList')}
                    onClick={() =>
                        editor?.chain().focus().toggleBulletList().run()
                    }
                >
                    <List className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Numbered list"
                    active={editor?.isActive('orderedList')}
                    onClick={() =>
                        editor?.chain().focus().toggleOrderedList().run()
                    }
                >
                    <ListOrdered className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Link"
                    active={editor?.isActive('link')}
                    onClick={setLink}
                >
                    <LinkIcon className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Clear formatting"
                    onClick={() =>
                        editor?.chain().focus().unsetAllMarks().run()
                    }
                >
                    <RemoveFormatting className="size-4" />
                </ToolButton>
            </div>
            <EditorContent editor={editor} className={EDITOR_CLS} />
        </div>
    );
}
