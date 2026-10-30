import Image from '@tiptap/extension-image';
import { mergeAttributes } from '@tiptap/core';

/**
 * Image node that renders as <figure style="width:X%"><img><figcaption>
 * so admins can resize images via width presets and attach captions.
 */
export const ImageWithCaption = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: '100%',
            },
            caption: {
                default: '',
            },
        };
    },

    renderHTML({ node, HTMLAttributes }) {
        const children: unknown[] = [
            'figcaption',
            mergeAttributes(HTMLAttributes, { contenteditable: 'true' }),
            node.attrs.caption,
        ];

        return [
            'figure',
            { style: `width: ${node.attrs.width}` },
            [
                'img',
                mergeAttributes(HTMLAttributes, {
                    src: node.attrs.src,
                    alt: node.attrs.alt,
                    style: 'width: 100%',
                }),
            ],
            ...(node.attrs.caption !== '' ? [children] : []),
        ];
    },

    parseHTML() {
        return [
            {
                tag: 'figure',
                getAttrs: (element) => {
                    const img = element.querySelector('img');
                    const figcaption = element.querySelector('figcaption');
                    const styleWidth = /width:\s*([\d.]+%)/
                        .exec(element.getAttribute('style') ?? '')?.[1];
                    return {
                        src: img?.getAttribute('src'),
                        alt: img?.getAttribute('alt'),
                        width: styleWidth ?? '100%',
                        caption: figcaption?.textContent ?? '',
                    };
                },
            },
        ];
    },
});
