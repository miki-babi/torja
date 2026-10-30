<?php

namespace App\Support\Telegram;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Converts Tiptap composer HTML into Telegram's `parse_mode=HTML` subset.
 * Supported inline styles (bold, italic, underline, strike, spoiler, code,
 * links) map to their Telegram tags; block elements (paragraphs, lists,
 * headings, blockquotes, pre) are flattened the way Telegram renders them.
 * All text is entity-escaped; anything Telegram can't express — images,
 * tables — is dropped or unwrapped.
 */
class TelegramHtml
{
    /**
     * Render composer HTML as Telegram-HTML text.
     */
    public static function toTelegram(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        // The XML processing instruction forces UTF-8 so multibyte
        // copy (Amharic, emoji) survives DOMDocument's Latin-1 default.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = self::findBody($doc);

        $text = $body !== null ? self::renderChildren($body) : '';

        return self::normalize($text);
    }

    /**
     * Collapse block separators and stray entities into the text Telegram
     * will store: at most one blank line, non-breaking spaces to spaces.
     */
    private static function normalize(string $text): string
    {
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text, "\n");
    }

    /**
     * Locate the wrapper <body> the fragment was loaded under.
     */
    private static function findBody(DOMDocument $doc): ?DOMElement
    {
        foreach ($doc->childNodes as $node) {
            if ($node instanceof DOMElement && strtolower($node->tagName) === 'body') {
                return $node;
            }
        }

        return null;
    }

    /**
     * Concatenate the rendered children of an element.
     */
    private static function renderChildren(DOMElement $element): string
    {
        $out = '';

        foreach ($element->childNodes as $child) {
            $out .= self::render($child);
        }

        return $out;
    }

    /**
     * Render a single node to Telegram-HTML.
     */
    private static function render(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        $inner = self::renderChildren($node);

        return match ($tag) {
            'b', 'strong' => '<b>'.$inner.'</b>',
            'i', 'em' => '<i>'.$inner.'</i>',
            'u', 'ins' => '<u>'.$inner.'</u>',
            's', 'strike', 'del' => '<s>'.$inner.'</s>',
            'a' => self::renderLink($node, $inner),
            'code' => '<code>'.$inner.'</code>',
            'pre' => self::renderPre($node),
            'span' => self::renderSpan($node, $inner),
            'p', 'div' => $inner."\n\n",
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => '<b>'.$inner.'</b>'."\n\n",
            'ul' => self::renderList($node, false),
            'ol' => self::renderList($node, true),
            'li' => '• '.$inner."\n",
            'blockquote' => '<blockquote>'.trim($inner).'</blockquote>'."\n",
            'br' => "\n",
            'hr' => "\n",
            'img', 'figure', 'video', 'audio', 'script', 'style', 'iframe' => '',
            default => $inner,
        };
    }

    /**
     * Links keep their href; linkless <a> unwraps to plain text.
     */
    private static function renderLink(DOMElement $node, string $inner): string
    {
        $href = trim($node->getAttribute('href'));

        if ($href === '' || $inner === '') {
            return $inner;
        }

        return '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.$inner.'</a>';
    }

    /**
     * A <span class="tg-spoiler"> (our Tiptap spoiler mark) maps to
     * Telegram's <tg-spoiler>; other spans unwrap.
     */
    private static function renderSpan(DOMElement $node, string $inner): string
    {
        return str_contains($node->getAttribute('class'), 'tg-spoiler')
            ? '<tg-spoiler>'.$inner.'</tg-spoiler>'
            : $inner;
    }

    /**
     * <pre> content is escaped verbatim; a language on the inner <code>
     * becomes Telegram's syntax-hint class.
     */
    private static function renderPre(DOMElement $node): string
    {
        $language = null;
        $code = $node;

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'code') {
                $code = $child;

                if (preg_match('/language-([a-z0-9_+-]+)/i', $child->getAttribute('class'), $m)) {
                    $language = strtolower($m[1]);
                }
            }
        }

        $text = htmlspecialchars($code->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $inner = $language !== null
            ? '<code class="language-'.$language.'">'.$text.'</code>'
            : '<code>'.$text.'</code>';

        return '<pre>'.$inner.'</pre>'."\n";
    }

    /**
     * Lists flatten to "• item" / "1. item" lines — Telegram has no
     * list markup.
     */
    private static function renderList(DOMElement $node, bool $ordered): string
    {
        $out = '';
        $index = 1;

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }

            $marker = $ordered ? ($index++).'. ' : '• ';
            $out .= $marker.rtrim(self::renderChildren($child), "\n")."\n";
        }

        return $out."\n";
    }
}
