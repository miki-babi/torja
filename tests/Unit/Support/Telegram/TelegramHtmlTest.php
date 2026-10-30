<?php

use App\Support\Telegram\TelegramHtml;

test('converts inline formatting to telegram tags', function () {
    expect(TelegramHtml::toTelegram('<p><strong>bold</strong> <em>italic</em> <u>under</u> <s>strike</s></p>'))
        ->toBe('<b>bold</b> <i>italic</i> <u>under</u> <s>strike</s>');
});

test('converts b i del variants', function () {
    expect(TelegramHtml::toTelegram('<p><b>b</b><i>i</i><del>d</del></p>'))
        ->toBe('<b>b</b><i>i</i><s>d</s>');
});

test('keeps links with escaped hrefs', function () {
    expect(TelegramHtml::toTelegram('<p><a href="https://ex.com/?a=1&b=2">link</a></p>'))
        ->toBe('<a href="https://ex.com/?a=1&amp;b=2">link</a>');
});

test('drops link tags without href but keeps text', function () {
    expect(TelegramHtml::toTelegram('<p><a>nolink</a></p>'))->toBe('nolink');
});

test('maps tg-spoiler spans', function () {
    expect(TelegramHtml::toTelegram('<p><span class="tg-spoiler">secret</span></p>'))
        ->toBe('<tg-spoiler>secret</tg-spoiler>');
});

test('unwraps non-spoiler spans', function () {
    expect(TelegramHtml::toTelegram('<p><span class="x">plain</span></p>'))
        ->toBe('plain');
});

test('escapes html in text', function () {
    expect(TelegramHtml::toTelegram('<p>a &lt; b &amp; "q"</p>'))
        ->toBe('a &lt; b &amp; &quot;q&quot;');
});

test('paragraphs become blank lines', function () {
    expect(TelegramHtml::toTelegram('<p>one</p><p>two</p>'))
        ->toBe("one\n\ntwo");
});

test('headings become bold blocks', function () {
    expect(TelegramHtml::toTelegram('<h2>Title</h2><p>body</p>'))
        ->toBe("<b>Title</b>\n\nbody");
});

test('lists flatten to bullets and numbers', function () {
    expect(TelegramHtml::toTelegram('<ul><li>a</li><li>b</li></ul>'))
        ->toBe("• a\n• b");

    expect(TelegramHtml::toTelegram('<ol><li>a</li><li>b</li></ol>'))
        ->toBe("1. a\n2. b");
});

test('code and pre map to telegram equivalents', function () {
    expect(TelegramHtml::toTelegram('<p>run <code>php artisan</code> now</p>'))
        ->toBe('run <code>php artisan</code> now');

    expect(TelegramHtml::toTelegram('<pre><code>echo &quot;hi&quot;;</code></pre>'))
        ->toBe('<pre><code>echo &quot;hi&quot;;</code></pre>');
});

test('pre keeps language class from code child', function () {
    expect(TelegramHtml::toTelegram('<pre><code class="language-php">$x=1;</code></pre>'))
        ->toBe('<pre><code class="language-php">$x=1;</code></pre>');
});

test('blockquotes map to telegram blockquote', function () {
    expect(TelegramHtml::toTelegram('<blockquote><p>wise words</p></blockquote>'))
        ->toBe('<blockquote>wise words</blockquote>');
});

test('images and figures are dropped', function () {
    expect(TelegramHtml::toTelegram('<p>hi<img src="x.png"></p><figure><img src="y.png"></figure>'))
        ->toBe('hi');
});

test('collapses excess newlines and trims', function () {
    expect(TelegramHtml::toTelegram('<p>a</p><p></p><p></p><p>b</p>'))
        ->toBe("a\n\nb");
});

test('non-breaking spaces become spaces', function () {
    expect(TelegramHtml::toTelegram('<p>a&nbsp;b</p>'))->toBe('a b');
});

test('empty html returns empty string', function () {
    expect(TelegramHtml::toTelegram(''))->toBe('');
    expect(TelegramHtml::toTelegram('   '))->toBe('');
});

test('multibyte text survives conversion', function () {
    expect(TelegramHtml::toTelegram('<p>ሰላም <strong>አለም</strong> 💪</p>'))
        ->toBe('ሰላም <b>አለም</b> 💪');
});
