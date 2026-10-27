<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Store an uploaded image on the public disk under $directory, replacing the
 * model's existing file. Returns the path to persist — the previous path
 * when no new file was uploaded or the store failed, null for fresh models.
 */
trait StoresPublicImage
{
    /**
     * @param  Model&object{image_path: string|null}  $model
     */
    private function storeImage(Request $request, string $directory, ?Model $model = null): ?string
    {
        if (! $request->hasFile('image')) {
            return $model?->image_path;
        }

        $path = $request->file('image')->store($directory, 'public');

        if ($path === false) {
            return $model?->image_path;
        }

        if ($model?->image_path !== null) {
            Storage::disk('public')->delete($model->image_path);
        }

        return $path;
    }
}
