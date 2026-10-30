import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import TiptapLink from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import {
    Bold,
    Heading2,
    Heading3,
    Image as ImageIcon,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Strikethrough,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ImageWithCaption } from '@/tiptap/image-with-caption';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='));
    return decodeURIComponent(cookie?.split('=')[1] ?? '');
}

const EDITOR_CLS =
    'min-h-40 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring [&_.tiptap]:outline-none [&_img]:max-w-full [&_img]:rounded-md [&_a]:text-lime-400 [&_a]:underline [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:pl-5 [&_ol]:pl-5 [&_p.is-editor-empty:first-child::before]:text-muted-foreground [&_p.is-editor-empty:first-child::before]:content-[attr(data-placeholder)] [&_p.is-editor-empty:first-child::before]:float-left [&_p.is-editor-empty:first-child::before]:h-0 [&_p.is-editor-empty:first-child::before]:pointer-events-none';

function ToolButton({
    active,
    disabled,
    onClick,
    label,
    children,
}: {
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-40',
                active && 'bg-accent text-foreground',
            )}
        >
            {children}
        </button>
    );
}

export function RichTextEditor({
    value,
    onChange,
    placeholder,
    uploadUrl = '/admin/shop-products/upload-image',
}: {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    uploadUrl?: string;
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [imageSelected, setImageSelected] = useState(false);

    const editor = useEditor({
        extensions: [
            StarterKit,
            ImageWithCaption,
            TiptapLink.configure({ openOnClick: false }),
            Placeholder.configure({
                placeholder: placeholder ?? 'Write product details…',
            }),
        ],
        content: value,
        onUpdate: ({ editor: e }) => onChange(e.getHTML()),
    });

    useEffect(() => {
        if (editor === null) {
            return;
        }
        const sync = () => setImageSelected(editor.isActive('image'));
        editor.on('selectionUpdate', sync);
        return () => {
            editor.off('selectionUpdate', sync);
        };
    }, [editor]);

    const imageAttrs = imageSelected
        ? (editor?.getAttributes('image') as
              | { width?: string; caption?: string }
              | undefined)
        : undefined;

    function updateImage(attrs: Record<string, string>) {
        editor?.chain().focus().updateAttributes('image', attrs).run();
    }

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

    async function uploadImage(file: File) {
        const body = new FormData();
        body.append('image', file);
        const response = await fetch(uploadUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-XSRF-TOKEN': xsrfToken() },
            body,
        });
        if (!response.ok) {
            return;
        }
        const { url } = (await response.json()) as { url: string };
        editor?.chain().focus().setImage({ src: url }).run();
    }

    return (
        <div className="rounded-xl border border-input">
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
                    label="Strike"
                    active={editor?.isActive('strike')}
                    onClick={() =>
                        editor?.chain().focus().toggleStrike().run()
                    }
                >
                    <Strikethrough className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Heading 2"
                    active={editor?.isActive('heading', { level: 2 })}
                    onClick={() =>
                        editor
                            ?.chain()
                            .focus()
                            .toggleHeading({ level: 2 })
                            .run()
                    }
                >
                    <Heading2 className="size-4" />
                </ToolButton>
                <ToolButton
                    label="Heading 3"
                    active={editor?.isActive('heading', { level: 3 })}
                    onClick={() =>
                        editor
                            ?.chain()
                            .focus()
                            .toggleHeading({ level: 3 })
                            .run()
                    }
                >
                    <Heading3 className="size-4" />
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
                    label="Insert image"
                    onClick={() => fileRef.current?.click()}
                >
                    <ImageIcon className="size-4" />
                </ToolButton>
            </div>
            {imageSelected && (
                <div className="flex flex-wrap items-center gap-2 border-b border-input px-3 py-2">
                    <span className="text-xs font-semibold text-muted-foreground">
                        Width
                    </span>
                    {['25%', '50%', '75%', '100%'].map((width) => (
                        <button
                            key={width}
                            type="button"
                            onClick={() => updateImage({ width })}
                            className={cn(
                                'rounded-md px-2 py-1 text-xs font-bold',
                                imageAttrs?.width === width
                                    ? 'bg-lime-400/20 text-lime-400'
                                    : 'bg-muted text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {width}
                        </button>
                    ))}
                    <Input
                        className="h-8 w-48 text-xs"
                        placeholder="Image caption…"
                        value={imageAttrs?.caption ?? ''}
                        onChange={(event) =>
                            updateImage({ caption: event.target.value })
                        }
                    />
                </div>
            )}
            <EditorContent editor={editor} className={EDITOR_CLS} />
            <input
                ref={fileRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) {
                        void uploadImage(file);
                    }
                    event.target.value = '';
                }}
            />
        </div>
    );
}
