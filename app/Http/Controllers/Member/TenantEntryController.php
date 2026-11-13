<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ExerciseProgram;
use App\Models\Trainer;
use App\Models\TrainerPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant entry point — the URL trainers set as their bot's Mini App URL and
 * hand out as their public page link. Guests see a lightweight profile page
 * with the trainer's shared programs and plans; members get linked to the
 * tenant and land in the app.
 */
class TenantEntryController extends Controller
{
    /**
     * Show the public trainer page to guests; link members and land them
     * in the member app.
     */
    public function __invoke(Request $request, Trainer $trainer): Response|RedirectResponse
    {
        abort_if($trainer->isSuspended(), 404);

        $request->session()->put('tenant', $trainer->slug);

        $user = $request->user();

        if ($user === null || ! $user->isClient()) {
            return $user === null
                ? $this->publicPage($trainer)
                : to_route('dashboard');
        }

        $linked = ! $user->isClientOf($trainer->user);

        $trainer->clients()->syncWithoutDetaching([$user->id]);
        $trainer->markClientOpened($user);

        if ($linked) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __("You're now training with :name.", ['name' => $trainer->display_name]),
            ]);
        }

        return to_route('member.home');
    }

    /**
     * The shareable coach page: profile plus the trainer's shared programs
     * and purchasable coaching plans.
     */
    private function publicPage(Trainer $trainer): Response
    {
        $trainer->loadMissing('user:id,name,avatar_path');

        return Inertia::render('public/trainer-page', [
            'trainer' => [
                'name' => $trainer->display_name,
                'avatar' => $trainer->user?->avatar,
                'bio' => $trainer->bio,
                'specialty' => $trainer->specialty,
                'languages' => $trainer->languages ?? [],
                'years_experience' => $trainer->years_experience,
                'slug' => $trainer->slug,
            ],
            'programs' => $trainer->user?->exercisePrograms()
                ->whereNotNull('share_token')
                ->withCount('workouts')
                ->latest()
                ->get()
                ->map(fn (ExerciseProgram $program): array => [
                    'name' => $program->localizedName(),
                    'description' => $program->localizedDescription(),
                    'is_free' => $program->isFree(),
                    'price_etb' => $program->price_etb,
                    'workouts_count' => $program->workouts_count,
                    'url' => route('programs.shared.show', $program->share_token),
                ])
                ->all() ?? [],
            'plans' => $trainer->plans()
                ->where('is_active', true)
                ->latest()
                ->get()
                ->map(fn (TrainerPlan $plan): array => [
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'kind_label' => TrainerPlan::KINDS[$plan->kind] ?? $plan->kind,
                    'price_etb' => $plan->price_etb,
                    'duration_days' => $plan->duration_days,
                    'url' => route('member.plans.show', $plan),
                ])
                ->all(),
            'register_url' => route('register'),
            'login_url' => route('login'),
        ]);
    }
}
