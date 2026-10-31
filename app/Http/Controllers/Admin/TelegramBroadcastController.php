<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TelegramBroadcastStatus;
use App\Enums\TelegramBroadcastType;
use App\Enums\TelegramButtonKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTelegramBroadcastRequest;
use App\Http\Requests\Admin\UpdateTelegramBroadcastRequest;
use App\Jobs\DispatchTelegramBroadcast;
use App\Jobs\RecallTelegramBroadcastRun;
use App\Jobs\SendTelegramBroadcastChunk;
use App\Models\TelegramBroadcast;
use App\Models\TelegramBroadcastRun;
use App\Models\User;
use App\Support\Segments;
use App\Support\Telegram\BroadcastAudience;
use App\Support\Telegram\BroadcastQueue;
use App\Support\Telegram\BroadcastSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin broadcast composer: CRUD for rich Telegram campaigns plus the
 * actions around them — test sends, send-now, scheduling and recurring,
 * per-recipient stats, resending failures and recalling sent messages.
 */
class TelegramBroadcastController extends Controller
{
    public function index(): Response
    {
        $broadcasts = TelegramBroadcast::query()
            ->with('runs')
            ->latest()
            ->paginate(20)
            ->through(fn (TelegramBroadcast $b): array => $this->summary($b));

        return Inertia::render('admin/broadcasts/index', [
            'broadcasts' => $broadcasts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/broadcasts/edit', $this->composerProps(null));
    }

    public function store(StoreTelegramBroadcastRequest $request): RedirectResponse
    {
        $broadcast = TelegramBroadcast::create(
            $request->payload() + ['created_by' => $request->user()->id],
        );

        Log::info('broadcast.saved', [
            'broadcast_id' => $broadcast->id,
            'action' => 'created',
            'send_mode' => (string) $request->input('send_mode', 'draft'),
        ]);

        $this->applySendMode($broadcast, (string) $request->input('send_mode', 'draft'));

        return to_route('admin.broadcasts.show', $broadcast);
    }

    public function show(TelegramBroadcast $broadcast, Request $request): Response
    {
        $status = $request->string('status')->toString() ?: null;
        $runId = $request->integer('run') ?: null;

        $deliveries = $broadcast->deliveries()
            ->with('user:id,name,username,telegram_username,phone')
            ->when($runId, fn ($q) => $q->where('run_id', $runId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($d): array => [
                'id' => $d->id,
                'run_id' => $d->run_id,
                'status' => $d->status,
                'error' => $d->error,
                'sent_at' => $d->sent_at?->toDateTimeString(),
                'deleted_at' => $d->deleted_at?->toDateTimeString(),
                'user' => $d->user === null ? null : [
                    'id' => $d->user->id,
                    'name' => $d->user->name,
                    'username' => $d->user->username,
                    'telegram_username' => $d->user->telegram_username,
                    'phone' => $d->user->phone,
                ],
            ]);

        return Inertia::render('admin/broadcasts/show', [
            'broadcast' => $this->detail($broadcast),
            'deliveries' => $deliveries,
            'filters' => ['status' => $status, 'run' => $runId],
        ]);
    }

    public function edit(TelegramBroadcast $broadcast): Response
    {
        abort_unless($broadcast->isEditable(), 403, 'Only drafts and scheduled broadcasts can be edited.');

        return Inertia::render('admin/broadcasts/edit', $this->composerProps($broadcast));
    }

    public function update(UpdateTelegramBroadcastRequest $request, TelegramBroadcast $broadcast): RedirectResponse
    {
        abort_unless($broadcast->isEditable(), 403);

        $broadcast->update($request->payload());

        $this->applySendMode($broadcast, (string) $request->input('send_mode', 'draft'));

        return to_route('admin.broadcasts.show', $broadcast);
    }

    public function destroy(TelegramBroadcast $broadcast): RedirectResponse
    {
        abort_unless($broadcast->status === TelegramBroadcastStatus::Draft, 403);

        $broadcast->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Broadcast deleted.')]);

        return to_route('admin.broadcasts.index');
    }

    /**
     * Send the saved payload to the admin's own linked chat.
     */
    public function sendTest(Request $request, TelegramBroadcast $broadcast, BroadcastSender $sender): RedirectResponse
    {
        $chatId = $request->user()->telegramRecipientId();

        if ($chatId === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Link your own Telegram first — see your member profile.'),
            ]);

            return back();
        }

        $result = $sender->sendToChat($broadcast, $chatId);

        Inertia::flash('toast', $result->ok
            ? ['type' => 'success', 'message' => __('Test sent — check your Telegram.')]
            : ['type' => 'error', 'message' => 'Telegram: '.($result->description ?? 'send failed')]);

        return back();
    }

    /**
     * Dispatch a saved draft/scheduled broadcast right now.
     */
    public function sendNow(TelegramBroadcast $broadcast): RedirectResponse
    {
        abort_unless($broadcast->isEditable(), 403);

        $broadcast->forceFill(['status' => 'sending'])->save();
        BroadcastQueue::dispatch(new DispatchTelegramBroadcast($broadcast->id, 'manual'));

        Log::info('broadcast.queued', [
            'broadcast_id' => $broadcast->id,
            'trigger' => 'manual',
            'queue' => config('queue.default'),
            'sync' => (bool) config('services.telegram.broadcast_sync'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Broadcast queued for sending.')]);

        return back();
    }

    public function pause(TelegramBroadcast $broadcast): RedirectResponse
    {
        abort_unless($broadcast->status === TelegramBroadcastStatus::Scheduled, 403);

        $broadcast->forceFill(['status' => 'paused'])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Broadcast paused.')]);

        return back();
    }

    public function resume(TelegramBroadcast $broadcast): RedirectResponse
    {
        abort_unless($broadcast->status === TelegramBroadcastStatus::Paused, 403);

        $broadcast->forceFill([
            'status' => 'scheduled',
            'next_run_at' => $broadcast->next_run_at?->isPast()
                ? ($broadcast->nextRunAfter(now()) ?? now())
                : $broadcast->next_run_at,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Broadcast resumed.')]);

        return back();
    }

    public function cancel(TelegramBroadcast $broadcast): RedirectResponse
    {
        abort_if(in_array($broadcast->status, [TelegramBroadcastStatus::Sent, TelegramBroadcastStatus::Cancelled], true), 403);

        $broadcast->forceFill(['status' => 'cancelled', 'next_run_at' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Broadcast cancelled — pending sends are skipped.')]);

        return back();
    }

    /**
     * Requeue only the failed deliveries of a run.
     */
    public function resendFailed(TelegramBroadcast $broadcast, TelegramBroadcastRun $run): RedirectResponse
    {
        abort_unless($run->broadcast_id === $broadcast->id, 404);

        $ids = $run->deliveries()->where('status', 'failed')->pluck('id');

        if ($ids->isEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('No failed deliveries to retry.')]);

            return back();
        }

        $run->deliveries()->where('status', 'failed')->update(['status' => 'pending']);

        $delay = 0;
        foreach ($ids->chunk(25) as $chunk) {
            BroadcastQueue::dispatch(
                new SendTelegramBroadcastChunk($run->id, $chunk->all()),
                $delay++,
            );
        }

        $run->update(['finished_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Retrying :count failed deliveries.', ['count' => $ids->count()])]);

        return back();
    }

    /**
     * Recall — deleteMessage for every stored message id in the run.
     */
    public function recall(TelegramBroadcast $broadcast, TelegramBroadcastRun $run): RedirectResponse
    {
        abort_unless($run->broadcast_id === $broadcast->id, 404);

        $ids = $run->deliveries()
            ->where('status', 'sent')
            ->whereNull('deleted_at')
            ->whereNotNull('message_ids')
            ->pluck('id');

        if ($ids->isEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Nothing left to recall.')]);

            return back();
        }

        $delay = 0;
        foreach ($ids->chunk(25) as $chunk) {
            BroadcastQueue::dispatch(
                new RecallTelegramBroadcastRun($run->id, $chunk->all()),
                $delay++,
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recalling :count messages.', ['count' => $ids->count()])]);

        return back();
    }

    /**
     * Live recipient count for the composer audience picker.
     */
    public function audienceCount(Request $request): JsonResponse
    {
        $audience = $request->validate([
            'audience' => ['required', 'array'],
            'audience.everyone' => ['nullable', 'boolean'],
            'audience.segments' => ['nullable', 'array'],
            'audience.segments.*' => ['string', Rule::in(array_keys(Segments::labels()))],
            'audience.user_ids' => ['nullable', 'array'],
            'audience.user_ids.*' => ['integer'],
        ])['audience'];

        return response()->json(['count' => BroadcastAudience::count($audience)]);
    }

    /**
     * Telegram-linked user search for the audience picker.
     */
    public function usersSearch(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        $users = User::query()
            ->telegramReachable()
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%")
                        ->orWhere('telegram_username', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'username', 'telegram_username', 'phone'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'label' => $u->name,
                'detail' => $u->telegram_username !== null
                    ? '@'.$u->telegram_username
                    : ($u->phone ?? $u->username ?? '#'.$u->id),
            ]);

        return response()->json(['users' => $users]);
    }

    /**
     * Media picked in the composer — stored on the public disk; Telegram
     * uploads it on first send, then the cached file_id takes over.
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        $kind = (string) $request->input('kind', 'photo');

        $rules = match ($kind) {
            'photo' => ['image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'video' => ['mimes:mp4,mov,webm,m4v', 'max:51200'],
            'animation' => ['mimes:mp4,gif', 'max:51200'],
            default => ['file', 'max:51200'],
        };

        $request->validate([
            'kind' => ['required', Rule::in(['photo', 'video', 'document', 'animation'])],
            'file' => array_merge(['required'], $rules),
        ]);

        $path = $request->file('file')->store('broadcasts', 'public');

        if ($path === false) {
            return response()->json(['error' => 'Upload failed'], 500);
        }

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Move the broadcast into the state the composer's submit picked.
     */
    private function applySendMode(TelegramBroadcast $broadcast, string $sendMode): void
    {
        Log::info('broadcast.send_mode', [
            'broadcast_id' => $broadcast->id,
            'send_mode' => $sendMode,
            'queue' => config('queue.default'),
        ]);

        if ($sendMode === 'now') {
            $broadcast->forceFill(['status' => 'sending'])->save();
            BroadcastQueue::dispatch(new DispatchTelegramBroadcast($broadcast->id, 'manual'));

            return;
        }

        if ($sendMode === 'schedule') {
            $broadcast->forceFill([
                'status' => 'scheduled',
                'next_run_at' => $broadcast->scheduled_at,
            ])->save();

            return;
        }

        if ($broadcast->status !== TelegramBroadcastStatus::Paused) {
            $broadcast->forceFill(['status' => 'draft', 'next_run_at' => null])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function composerProps(?TelegramBroadcast $broadcast): array
    {
        $segments = collect(Segments::labels())
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'count' => Segments::query($key)->count(),
            ])->values()->all();

        $linkedCount = User::query()->telegramReachable()->count();

        $pickedUsers = $broadcast === null
            ? []
            : User::query()
                ->whereIn('id', $broadcast->audience['user_ids'] ?? [])
                ->get(['id', 'name', 'username', 'telegram_username', 'phone'])
                ->map(fn (User $u): array => [
                    'id' => $u->id,
                    'label' => $u->name,
                    'detail' => $u->telegram_username !== null
                        ? '@'.$u->telegram_username
                        : ($u->phone ?? $u->username ?? '#'.$u->id),
                ])->all();

        return [
            'broadcast' => $broadcast === null ? null : $this->detail($broadcast),
            'pickedUsers' => $pickedUsers,
            'segments' => $segments,
            'linkedCount' => $linkedCount,
            'types' => TelegramBroadcastType::values(),
            'buttonKinds' => TelegramButtonKind::values(),
            'appUrl' => rtrim((string) config('app.url'), '/'),
        ];
    }

    /**
     * List-row payload.
     *
     * @return array<string, mixed>
     */
    private function summary(TelegramBroadcast $broadcast): array
    {
        $lastRun = $broadcast->runs->sortByDesc('id')->first();

        return [
            'id' => $broadcast->id,
            'name' => $broadcast->name,
            'type' => $broadcast->type->value,
            'status' => $broadcast->status->value,
            'recurrence' => $broadcast->recurrence,
            'audience' => BroadcastAudience::describe($broadcast->audience ?? []),
            'next_run_at' => $broadcast->next_run_at?->toDateTimeString(),
            'last_run_at' => $broadcast->last_run_at?->toDateTimeString(),
            'last_run' => $lastRun === null ? null : [
                'id' => $lastRun->id,
                'recipients_count' => $lastRun->recipients_count,
                'sent_count' => $lastRun->sent_count,
                'failed_count' => $lastRun->failed_count,
                'blocked_count' => $lastRun->blocked_count,
            ],
        ];
    }

    /**
     * Full payload for show/edit pages.
     *
     * @return array<string, mixed>
     */
    private function detail(TelegramBroadcast $broadcast): array
    {
        return $this->summary($broadcast) + [
            'body_html' => $broadcast->body_html,
            'telegram_text' => $broadcast->telegram_text,
            'media' => array_map(function (array $item): array {
                // Give the composer a display URL for disk-backed uploads.
                if (($item['url'] ?? null) === null
                    && ($item['source'] ?? '') === 'path'
                    && ($item['value'] ?? '') !== '') {
                    $item['url'] = Storage::disk('public')->url($item['value']);
                }

                return $item;
            }, $broadcast->media ?? []),
            'buttons' => $broadcast->buttons,
            'poll' => $broadcast->poll,
            'options' => $broadcast->options,
            'audience_config' => $broadcast->audience,
            'scheduled_at' => $broadcast->scheduled_at?->toDateTimeString(),
            'editable' => $broadcast->isEditable(),
            'runs' => $broadcast->runs()
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (TelegramBroadcastRun $r): array => [
                    'id' => $r->id,
                    'trigger' => $r->trigger,
                    'recipients_count' => $r->recipients_count,
                    'sent_count' => $r->sent_count,
                    'failed_count' => $r->failed_count,
                    'blocked_count' => $r->blocked_count,
                    'deleted_count' => $r->deleted_count,
                    'started_at' => $r->started_at?->toDateTimeString(),
                    'finished_at' => $r->finished_at?->toDateTimeString(),
                ])->all(),
        ];
    }
}
