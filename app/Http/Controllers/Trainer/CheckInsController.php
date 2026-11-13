<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The trainer's check-in board: which clients checked in this week, which
 * missed it, and which are still waiting on a reply — plus the reply
 * action itself.
 */
class CheckInsController extends Controller
{
    /**
     * Needs-attention list plus this week's check-ins to review.
     */
    public function index(Request $request): Response
    {
        $trainer = $request->user()->ensureTrainer();
        $weekStart = CheckIn::currentWeek();

        $clients = $trainer->clients()->select('users.id', 'users.name')->get();
        $clientIds = $clients->pluck('id');

        $weekCheckIns = CheckIn::query()
            ->with('media', 'user:id,name')
            ->whereIn('user_id', $clientIds)
            ->whereDate('week_of', $weekStart)
            ->get()
            ->keyBy('user_id');

        $attention = $clients->map(function (User $client) use ($weekCheckIns): array {
            $checkIn = $weekCheckIns->get($client->id);

            return [
                'id' => $client->id,
                'name' => $client->name,
                'status' => $checkIn === null
                    ? 'missing'
                    : ($checkIn->needsReply() ? 'awaiting_reply' : 'done'),
                'check_in_id' => $checkIn?->id,
            ];
        })->sortBy(fn (array $row): int => match ($row['status']) {
            'missing' => 0,
            'awaiting_reply' => 1,
            default => 2,
        })->values();

        return Inertia::render('trainer/check-ins', [
            'attention' => $attention,
            'review' => $weekCheckIns
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (CheckIn $checkIn): array => $this->serialize($checkIn)),
            'week_of' => $weekStart->toDateString(),
        ]);
    }

    /**
     * Reply to a client's check-in.
     */
    public function reply(Request $request, CheckIn $checkIn): RedirectResponse
    {
        abort_unless($request->user()->isTrainerOf($checkIn->user), 403);

        $data = $request->validate([
            'reply' => ['required', 'string', 'max:2000'],
        ]);

        $checkIn->update([
            'trainer_reply' => $data['reply'],
            'replied_by' => $request->user()->id,
            'replied_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reply sent.'),
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CheckIn $checkIn): array
    {
        return [
            'id' => $checkIn->id,
            'client_name' => $checkIn->user->name,
            'week_of' => $checkIn->week_of->toDateString(),
            'weight_kg' => $checkIn->weight_kg === null ? null : (float) $checkIn->weight_kg,
            'note' => $checkIn->note,
            'trainer_reply' => $checkIn->trainer_reply,
            'needs_reply' => $checkIn->needsReply(),
            'media' => $checkIn->media->map(fn ($media): array => [
                'id' => $media->id,
                'url' => $media->url(),
                'kind' => $media->kind,
            ])->all(),
        ];
    }
}
