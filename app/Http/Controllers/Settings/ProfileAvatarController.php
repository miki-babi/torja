<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileAvatarController extends Controller
{
    /**
     * Store an uploaded profile photo on the public disk under
     * avatars/{user_id}, replacing any previous file.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();
        $user->updateAvatar($data['avatar']);

        return response()->json(['avatar' => $user->avatar]);
    }

    /**
     * Remove the user's profile photo and its stored file.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->deleteAvatar();

        return response()->json(['avatar' => null]);
    }
}
