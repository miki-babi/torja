<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Tenant entry point — the URL trainers set as their bot's Mini App URL and
 * hand out as the client invite link. The slug is kept in the session so a
 * Mini App sign-in can bind the new account to the right tenant; a member
 * who is already logged in is linked immediately.
 */
class TenantEntryController extends Controller
{
    /**
     * Link the current member (or arm the session for the next sign-in)
     * and land on the member app.
     */
    public function __invoke(Request $request, Trainer $trainer): RedirectResponse
    {
        $request->session()->put('tenant', $trainer->slug);

        $user = $request->user();

        if ($user === null) {
            return to_route('login');
        }

        $linked = ! $user->isClientOf($trainer->user);

        $trainer->clients()->syncWithoutDetaching([$user->id]);

        if ($linked) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __("You're now training with :name.", ['name' => $trainer->display_name]),
            ]);
        }

        return to_route('member.home');
    }
}
