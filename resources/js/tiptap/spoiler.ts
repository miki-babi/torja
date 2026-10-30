import { Mark, mergeAttributes } from '@tiptap/core';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        spoiler: {
            /** Toggle a spoiler (hidden text) mark */
            toggleSpoiler: () => ReturnType;
        };
    }
}

/**
 * Telegram spoiler formatting — hidden-until-tapped text. Renders as
 * <span class="tg-spoiler"> which the server-side TelegramHtml converter
 * maps to Telegram's <tg-spoiler> tag.
 */
export const Spoiler = Mark.create({
    name: 'spoiler',

    parseHTML() {
        return [{ tag: 'span.tg-spoiler' }, { tag: 'tg-spoiler' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'span',
            mergeAttributes(HTMLAttributes, { class: 'tg-spoiler' }),
            0,
        ];
    },

    addCommands() {
        return {
            toggleSpoiler:
                () =>
                ({ commands }) =>
                    commands.toggleMark(this.name),
        };
    },

    addKeyboardShortcuts() {
        return {
            'Mod-Shift-p': () => this.editor.commands.toggleSpoiler(),
        };
    },
});
