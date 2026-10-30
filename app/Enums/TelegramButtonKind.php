<?php

namespace App\Enums;

/**
 * Every InlineKeyboardButton flavour the Bot API exposes. `Callback` maps
 * to callback_data handled by the webhook action registry; `Pay` only
 * renders inside invoice messages but is serialized for completeness.
 */
enum TelegramButtonKind: string
{
    case Url = 'url';
    case WebApp = 'web_app';
    case Callback = 'callback';
    case LoginUrl = 'login_url';
    case SwitchInlineQuery = 'switch_inline_query';
    case SwitchInlineQueryCurrentChat = 'switch_inline_query_current_chat';
    case SwitchInlineQueryChosenChat = 'switch_inline_query_chosen_chat';
    case CopyText = 'copy_text';
    case Pay = 'pay';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
