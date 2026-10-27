<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use Illuminate\Http\RedirectResponse;

class ShortLinkController extends Controller
{
    /**
     * Resolve a short share code to its destination URL.
     */
    public function __invoke(string $code): RedirectResponse
    {
        $link = ShortLink::query()->where('code', $code)->firstOrFail();

        $link->increment('clicks');

        return redirect()->to($link->url);
    }
}
