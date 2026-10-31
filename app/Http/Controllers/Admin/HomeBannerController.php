<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\HomeBanners;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home banner editor: the {image, url} carousel shown on the member
 * home screen between the quick-action chips and the meal card. Clearing
 * the list hides the carousel entirely.
 */
class HomeBannerController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/home-banners/edit', [
            'banners' => HomeBanners::banners(),
            'resolved' => HomeBanners::forMember(),
        ]);
    }

    /**
     * Save the whole banner list as one setting.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(HomeBanners::rules());

        Setting::set(HomeBanners::SETTING_KEY, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Home banners saved.'),
        ]);

        return back();
    }

    /**
     * Drop the saved banners so the home screen hides the carousel.
     */
    public function destroy(): RedirectResponse
    {
        Setting::forget(HomeBanners::SETTING_KEY);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Home banners cleared.'),
        ]);

        return back();
    }

    /**
     * Banner image picked in the editor — returns the public-disk path to
     * store and a resolved URL for the preview.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:4096'],
        ]);

        $path = $request->file('image')->store('home-banners', 'public');

        if ($path === false) {
            return response()->json(['error' => 'Upload failed'], 500);
        }

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
