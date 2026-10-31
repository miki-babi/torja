<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\Concerns\RedirectsUsersByRole;
use App\Listeners\BindReferrer;
use App\Models\User;
use App\Support\Telegram\InitData;
use App\Support\Telegram\UserResolver;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Telegram deep-link sign-in. The login page calls `init` to mint a one-time
 * token, sends the user to `t.me/<bot>?start=login_<token>`, then polls
 * `status` until the bot webhook binds the token to a user, and finally calls
 * `complete` to create the web session.
 *
 * `webapp` serves the Mini App flow: the auth screen detects signed
 * `initData`, posts it here, and the verified Telegram user is signed in
 * (creating an account on the fly when unknown) with no round-trip. An
 * ambassador `startapp=ref_<code>` start param binds the referrer, both
 * on sign-in and for already-authenticated Mini App sessions.
 */
class TelegramLoginController extends Controller
{
    use RedirectsUsersByRole;

    /**
     * Seconds a login token stays valid.
     */
    private const TTL = 600;

    /**
     * Mint a pending login token and return the bot deep link.
     */
    public function init(Request $request): JsonResponse
    {
        $bot = config('services.telegram.bot_username');

        if (empty($bot)) {
            return response()->json(['error' => 'Telegram login is not configured.'], 503);
        }

        $token = 'login_'.Str::random(48);

        Cache::put($this->key($token), ['user_id' => null], self::TTL);

        return response()->json([
            'url' => "https://t.me/{$bot}?start={$token}",
            'token' => $token,
            'expires_in' => self::TTL,
        ]);
    }

    /**
     * Report whether the token is still pending, ready, or expired.
     */
    public function status(Request $request): JsonResponse
    {
        $entry = $this->entry($request);

        if ($entry === null) {
            return response()->json(['status' => 'expired'], 410);
        }

        return response()->json([
            'status' => $entry['user_id'] === null ? 'pending' : 'ready',
        ]);
    }

    /**
     * Consume a ready token and log the bound user in.
     */
    public function complete(Request $request): JsonResponse
    {
        $entry = $this->entry($request);

        if ($entry === null) {
            return response()->json(['status' => 'expired'], 410);
        }

        if ($entry['user_id'] === null) {
            return response()->json(['status' => 'pending'], 409);
        }

        $user = User::find($entry['user_id']);

        if ($user === null) {
            Cache::forget($this->key((string) $request->input('token')));

            return response()->json(['status' => 'expired'], 410);
        }

        Auth::guard('web')->login($user);
        Cache::forget($this->key((string) $request->input('token')));
        $request->session()->regenerate();

        return response()->json([
            'redirect' => redirect()->intended($this->homeFor($user))->getTargetUrl(),
        ]);
    }

    /**
     * Verify Mini App `initData` and sign the embedded user in immediately.
     */
    public function webapp(Request $request): JsonResponse
    {
        Log::info('telegram.webapp hit', ['has_init_data' => $request->filled('init_data')]);

        $botToken = config('services.telegram.bot_token');

        if (empty($botToken)) {
            Log::warning('telegram.webapp rejected: bot token not configured');

            return response()->json(['error' => 'Telegram login is not configured.'], 503);
        }

        $validated = $request->validate([
            'init_data' => ['required', 'string', 'max:8192'],
        ]);

        $initData = InitData::verify($validated['init_data'], $botToken, $failure);
        $from = $initData?->user();

        $resolver = app(UserResolver::class);

        if ($from === null || $resolver->telegramId($from) === null) {
            Log::warning('telegram.webapp rejected: invalid init data', [
                'reason' => $from === null ? ($failure ?? 'no-user') : 'no-telegram-id',
            ]);

            return response()->json(['status' => 'invalid'], 401);
        }

        // initData carries no chat id — but a private chat_id equals the
        // user id, so when the member allowed bot DMs we can persist the
        // recipient address for broadcasts and reminders right away.
        $chatId = ($from['allows_write_to_pm'] ?? false) === true
            ? (string) $resolver->telegramId($from)
            : null;

        $user = $resolver->resolve($from, $chatId);

        Log::info('telegram.webapp signed in', [
            'user_id' => $user->id,
            'created' => $user->wasRecentlyCreated,
        ]);

        $this->bindInvite($initData, $user);

        if (! $request->user()?->is($user)) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return response()->json([
            'redirect' => redirect()->intended($this->homeFor($user))->getTargetUrl(),
        ]);
    }

    /**
     * Bind the ambassador behind a `ref_<code>` Mini App start param —
     * the Mini App equivalent of the `/r/{code}` web invite link.
     */
    private function bindInvite(InitData $initData, User $user): void
    {
        $param = $initData->startParam();

        if ($param === null || preg_match('/^ref_([\w-]{1,512})$/', $param, $matches) !== 1) {
            return;
        }

        $referrer = User::query()->where('referral_code', $matches[1])->first();

        if ($referrer === null) {
            return;
        }

        app(Tracker::class)->record('ambassador.clicked', $user, [
            'referrer_id' => $referrer->id,
        ], $referrer);

        app(BindReferrer::class)->bind($user, $matches[1]);
    }

    /**
     * Fire-and-forget client log sink: the auth screen reports what it saw
     * (payload found, sign-in failure status) so Mini App sign-in attempts
     * leave a trail in the log.
     */
    public function debug(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_.-]{2,60}$/'],
            'context' => ['nullable', 'array', 'max:20'],
        ]);

        Log::info('telegram.webapp.client', $validated);

        return response()->json(['ok' => true]);
    }

    /**
     * The cache key for a login token.
     */
    private function key(string $token): string
    {
        return "tg-login:{$token}";
    }

    /**
     * Read the pending login entry for the request's token.
     *
     * @return array{user_id: int|null}|null
     */
    private function entry(Request $request): ?array
    {
        $token = (string) $request->input('token');

        if ($token === '' || strlen($token) > 64) {
            return null;
        }

        $entry = Cache::get($this->key($token));

        return is_array($entry) && array_key_exists('user_id', $entry) ? $entry : null;
    }
}
