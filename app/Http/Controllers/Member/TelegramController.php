<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Member-facing Telegram linking: generates the deep-link token and returns
 * the `t.me/<bot>?start=<token>` URL, or clears the linked chat id.
 */
class TelegramController extends Controller
{
    /**
     * Issue a link token and return the bot deep link.
     */
    public function link(Request $request): JsonResponse
    {
        $bot = config('services.telegram.bot_username');

        if (empty($bot)) {
            return response()->json(['error' => 'Telegram is not configured.'], 503);
        }

        $user = $request->user();
        $user->forceFill(['telegram_link_token' => (string) Str::uuid()])->save();

        return response()->json([
            'url' => "https://t.me/{$bot}?start={$user->telegram_link_token}",
        ]);
    }

    /**
     * Unlink the member's Telegram chat.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $request->user()->forceFill(['telegram_chat_id' => null])->save();

        return response()->json(['ok' => true]);
    }
}
