<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The member's reminder opt-out — one flag on the user that the
 * reminders:send command checks before DMing them through a trainer bot.
 */
class RemindersController extends Controller
{
    /**
     * Toggle whether the member receives Telegram reminders.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $request->user()->update(['reminders_enabled' => $data['enabled']]);

        return response()->json(['reminders_enabled' => $data['enabled']]);
    }
}
