<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Jobs\SendTrainerBroadcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Simple text broadcasts to the trainer's clients, delivered through the
 * trainer's own bot by the SendTrainerBroadcast job.
 */
class BroadcastsController extends Controller
{
    /**
     * Compose form plus send history.
     */
    public function index(Request $request): Response
    {
        $trainer = $request->user()->ensureTrainer();

        return Inertia::render('trainer/broadcasts/index', [
            'broadcasts' => $trainer->broadcasts()
                ->latest()
                ->paginate(15)
                ->through(fn ($broadcast): array => [
                    'id' => $broadcast->id,
                    'body' => $broadcast->body,
                    'sent_count' => $broadcast->sent_count,
                    'failed_count' => $broadcast->failed_count,
                    'created_at' => $broadcast->created_at->toDateTimeString(),
                ]),
            'clients_count' => $trainer->clients()->count(),
            'bot_connected' => $trainer->hasConnectedBot(),
        ]);
    }

    /**
     * Queue a broadcast to all linked clients.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
        ]);

        $trainer = $request->user()->ensureTrainer();

        if (! $trainer->hasConnectedBot()) {
            return back()->withErrors([
                'body' => __('Connect your bot before broadcasting.'),
            ]);
        }

        $broadcast = $trainer->broadcasts()->create(['body' => $data['body']]);

        SendTrainerBroadcast::dispatch($broadcast->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Broadcast queued — clients will get it on Telegram.'),
        ]);

        return back();
    }
}
