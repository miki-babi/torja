<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member's weekly coach check-in: weight, a note, and progress
 * photos/voice notes. One row per week — re-submitting updates it. The
 * trainer's reply shows up on the same row in the history list.
 */
class CheckInController extends Controller
{
    /**
     * This week's check-in (for the form) plus recent history with replies.
     */
    public function index(Request $request): Response
    {
        $checkIns = $request->user()->checkIns()
            ->with('media')
            ->limit(12)
            ->get();

        $current = $checkIns->first(fn (CheckIn $checkIn): bool => $checkIn->isThisWeek());

        return Inertia::render('member/check-ins', [
            'current' => $current === null ? null : $this->serialize($current),
            'history' => $checkIns
                ->reject(fn (CheckIn $checkIn): bool => $checkIn->isThisWeek())
                ->values()
                ->map(fn (CheckIn $checkIn): array => $this->serialize($checkIn)),
            'week_of' => CheckIn::currentWeek()->toDateString(),
        ]);
    }

    /**
     * Create or update this week's check-in. Media files are classified by
     * mime type: images → photo, audio → voice, video → video.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'weight_kg' => ['nullable', 'numeric', 'min:20', 'max:400'],
            'note' => ['nullable', 'string', 'max:2000'],
            'media' => ['nullable', 'array', 'max:6'],
            'media.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,audio/mpeg,audio/mp4,audio/webm,audio/ogg,audio/wav,video/mp4,video/webm,video/quicktime', 'max:25600'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $checkIn = $request->user()->checkIns()
                ->whereDate('week_of', CheckIn::currentWeek())
                ->firstOrNew();
            $checkIn->fill([
                'week_of' => CheckIn::currentWeek()->toDateString(),
                'weight_kg' => $data['weight_kg'] ?? null,
                'note' => $data['note'] ?? null,
            ])->save();

            foreach ($request->file('media', []) as $file) {
                /** @var UploadedFile $file */
                $checkIn->media()->create([
                    'path' => $file->store("check-ins/{$request->user()->id}", 'public'),
                    'kind' => $this->kindFor($file),
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Check-in saved — your coach will see it.'),
        ]);

        return to_route('member.check-ins.index');
    }

    /**
     * The media bucket for an uploaded file.
     */
    private function kindFor(UploadedFile $file): string
    {
        return match (true) {
            str_starts_with($file->getMimeType(), 'audio/') => 'voice',
            str_starts_with($file->getMimeType(), 'video/') => 'video',
            default => 'photo',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CheckIn $checkIn): array
    {
        return [
            'id' => $checkIn->id,
            'week_of' => $checkIn->week_of->toDateString(),
            'weight_kg' => $checkIn->weight_kg === null ? null : (float) $checkIn->weight_kg,
            'note' => $checkIn->note,
            'trainer_reply' => $checkIn->trainer_reply,
            'replied_at' => $checkIn->replied_at,
            'media' => $checkIn->media->map(fn ($media): array => [
                'id' => $media->id,
                'url' => $media->url(),
                'kind' => $media->kind,
            ])->all(),
        ];
    }
}
