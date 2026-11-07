<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Models\MealProgram;
use App\Models\MealProgramMeal;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\CheckoutLauncher;
use App\Services\Programs\MealProgramAccessGranter;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SharedMealProgramController extends Controller
{
    public function __construct(
        private readonly MealProgramAccessGranter $grants,
        private readonly CheckoutLauncher $launcher,
    ) {}

    /**
     * Public read-only view of a shared meal program, addressed by its share
     * token. Guests can view; claiming needs login and Pro.
     */
    public function show(Request $request, MealProgram $mealProgram): Response
    {
        $mealProgram->load(['meals.items.food', 'user']);

        $user = $request->user();

        app(Tracker::class)->record('shared_meal_program.viewed', subject: $mealProgram, properties: [
            'owner_id' => $mealProgram->user_id,
            'is_owner' => $user?->id === $mealProgram->user_id,
        ]);

        $claimedPlan = $user !== null
            ? $this->grants->claimedPlan($user, $mealProgram)
            : null;

        return Inertia::render('member/shared-meal-program', [
            'program' => [
                'name' => $mealProgram->localizedName(),
                'owner' => $mealProgram->user->name,
                'description' => $mealProgram->localizedDescription(),
                'image_url' => $mealProgram->image_url,
                'highlights' => $mealProgram->localizedHighlights(),
                'days' => $mealProgram->meals
                    ->groupBy('day')
                    ->map(fn ($meals, int $day): array => [
                        'day' => $day,
                        'meals' => $meals
                            ->map(fn ($meal): array => [
                                'type' => $meal->meal_type->value,
                                'items' => $this->mealItems($meal),
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ],
            'isOwner' => $user?->id === $mealProgram->user_id,
            'isPro' => $user?->isPro() ?? false,
            'isCoachProgram' => $mealProgram->user->isTrainer(),
            'priceEtb' => $mealProgram->price_etb,
            'canClaim' => $user !== null && $this->canClaim($user, $mealProgram),
            'alreadyClaimed' => $claimedPlan !== null || ($user !== null && $this->grants->hasAccess($user, $mealProgram)),
            'saveUrl' => route('meal-programs.shared.save', $mealProgram->share_token),
            'checkoutUrl' => $mealProgram->isFree()
                ? null
                : route('meal-programs.shared.checkout', $mealProgram->share_token),
            'providers' => [
                'chapa' => filled(config('services.chapa.secret_key')),
                'verify_checkout' => filled(config('services.verify_checkout.api_key')),
                'jami' => filled(config('services.jami.token')),
            ],
            'proUrl' => route('pro.show'),
            'homeUrl' => $user !== null ? route('member.home') : route('explore.index'),
            'editUrl' => $user?->isTrainer() && $mealProgram->user_id === $user->id
                ? route('trainer.meal-programs.edit', $mealProgram)
                : null,
            'claimedPlanUrl' => $claimedPlan !== null
                ? route('member.meal-programs.show', $claimedPlan)
                : null,
        ]);
    }

    /**
     * Claim a meal program: record the access row and copy its days into a
     * member-owned meal plan. Requires Pro; idempotent.
     */
    public function save(Request $request, MealProgram $mealProgram): JsonResponse
    {
        $this->authorizeClaim($request->user(), $mealProgram);

        $granted = $this->grants->grant($request->user(), $mealProgram);

        if ($granted) {
            app(Tracker::class)->record('shared_meal_program.saved', subject: $mealProgram, properties: [
                'owner_id' => $mealProgram->user_id,
            ]);
        }

        $plan = $this->grants->claimedPlan($request->user(), $mealProgram);

        return response()->json([
            'claimed' => true,
            'plan_url' => $plan !== null ? route('member.meal-programs.show', $plan) : null,
        ], $granted ? 201 : 200);
    }

    /**
     * Start a paid purchase for a priced meal program through the normal
     * provider checkout. Buyers of a trainer-authored plan are linked as
     * clients automatically.
     */
    public function checkout(Request $request, MealProgram $mealProgram): SymfonyResponse|RedirectResponse
    {
        abort_if($mealProgram->isFree(), 422);

        $user = $request->user();

        $author = $mealProgram->user;

        if ($author->isTrainer()) {
            $author->trainer?->clients()->syncWithoutDetaching([$user->id]);
        }

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:chapa,verify_checkout,jami'],
            'gateway' => ['nullable', 'string', 'in:telebirr,mpesa,cbe'],
            'customer_phone' => ['required_with:gateway', 'nullable', 'string', 'min:9', 'max:16'],
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'provider' => $validated['provider'],
            'tx_ref' => "tp-{$user->id}-".Str::uuid(),
            'idempotency_key' => 'checkout_'.Str::uuid(),
            'amount' => $mealProgram->price_etb,
            'currency' => 'ETB',
            'status' => 'pending',
            'purpose' => 'meal_program_purchase',
            'payable_type' => $mealProgram->getMorphClass(),
            'payable_id' => $mealProgram->id,
            'email' => $user->email ?? "user-{$user->id}@trainpro.local",
            'first_name' => Str::before(trim($user->name).' ', ' '),
            'last_name' => trim(Str::after(trim($user->name).' ', ' ')) ?: null,
        ]);

        app(Tracker::class)->record('checkout.started', $user, [
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
        ], $payment);

        $launch = $this->launcher->start(
            $payment,
            $validated['gateway'] ?? null,
            $validated['customer_phone'] ?? null,
        );

        if ($launch->checkoutUrl !== null) {
            return Inertia::location($launch->checkoutUrl);
        }

        if ($launch->pushInitiated) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Approve the payment on your phone — the meal program unlocks once confirmed.'),
            ]);

            return back();
        }

        $payment->update(['status' => 'failed']);

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('We could not start the payment. Please try again.'),
        ]);

        return back();
    }

    /**
     * Whether the member may claim the program without paying. Platform
     * (admin-authored) programs keep the old rule — Pro claims anything.
     * Trainer-authored programs: the author always can, priced ones are
     * purchase-only, and free ones open to Pro or linked clients.
     */
    private function canClaim(User $user, MealProgram $mealProgram): bool
    {
        if (! $mealProgram->user->isTrainer()) {
            return $user->isPro();
        }

        if ($user->id === $mealProgram->user_id) {
            return true;
        }

        if (! $mealProgram->isFree()) {
            return false;
        }

        return $user->isPro() || $user->isClientOf($mealProgram->user);
    }

    /**
     * Gate a free claim: paid programs must go through checkout, everyone
     * else needs Pro or a client link to the coach.
     */
    private function authorizeClaim(User $user, MealProgram $mealProgram): void
    {
        abort_unless($this->canClaim($user, $mealProgram), 403);
    }

    /**
     * @return array<int, array{name: string, image_url: string|null, portion_note: string|null, macros: array{kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}|null}>
     */
    private function mealItems(MealProgramMeal $meal): array
    {
        return $meal->items
            ->map(fn ($item): array => [
                'name' => $item->food->name_en ?? 'Unknown food',
                'image_url' => $item->food?->image_url,
                'portion_note' => $item->portion_note,
                'macros' => $this->macros($item->food),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}|null
     */
    private function macros(?Food $food): ?array
    {
        if ($food === null) {
            return null;
        }

        return [
            'kcal_per_100g' => $food->kcal_per_100g === null ? null : (float) $food->kcal_per_100g,
            'protein_g' => $food->protein_g === null ? null : (float) $food->protein_g,
            'carbs_g' => $food->carbs_g === null ? null : (float) $food->carbs_g,
            'fat_g' => $food->fat_g === null ? null : (float) $food->fat_g,
        ];
    }
}
