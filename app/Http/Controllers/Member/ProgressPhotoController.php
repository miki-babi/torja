<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ProgressPhoto;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProgressPhotoController extends Controller
{
    /**
     * Store an uploaded progress photo on the public disk. `taken_on`
     * defaults to today so members can backfill older shots.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'taken_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $path = $data['photo']->store(
            "progress-photos/{$request->user()->id}",
            'public',
        );

        $photo = ProgressPhoto::create([
            'user_id' => $request->user()->id,
            'path' => $path,
            'taken_on' => $data['taken_on'] ?? today()->toDateString(),
        ]);

        app(Tracker::class)->record('progress.photo_uploaded', subject: $photo);

        return response()->json([
            'photo' => [
                'id' => $photo->id,
                'url' => $photo->url(),
                'taken_on' => $photo->taken_on->toDateString(),
            ],
        ], 201);
    }

    /**
     * Remove one progress photo, deleting the stored file with it.
     */
    public function destroy(Request $request, ProgressPhoto $progressPhoto): JsonResponse
    {
        abort_unless($progressPhoto->user_id === $request->user()->id, 403);

        Storage::disk('public')->delete($progressPhoto->path);
        $progressPhoto->delete();

        return response()->json(['ok' => true]);
    }
}
