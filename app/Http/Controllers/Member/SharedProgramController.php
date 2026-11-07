<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramWorkout;
use App\Models\ExerciseProgramWorkoutItem;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\Payments\CheckoutLauncher;
use App\Services\Programs\ProgramAccessGranter;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SharedProgramController extends Controller
{
    public function __construct(
        private readonly ProgramAccessGranter $grants,
        private readonly CheckoutLauncher $launcher,
    ) {}

    /**
     * Public read-only view of a shared program, addressed by its share
     * token. Guests can view; claiming needs login and Pro.
     */
    public function show(Request $request, ExerciseProgram $program): Response
    {
        $program->load(['workouts.items', 'user']);

        $user = $request->user();

        app(Tracker::class)->record('shared_program.viewed', subject: $program, properties: [
            'owner_id' => $program->user_id,
            'is_owner' => $user?->id === $program->user_id,
        ]);

        $exerciseSlugs = $program->workouts
            ->flatMap(fn ($workout) => $workout->items->pluck('exercise_id'))
            ->unique()
            ->all();

        $exercises = Exercise::query()
            ->whereIn('slug', $exerciseSlugs)
            ->get()
            ->keyBy('slug');

        $todaysSessions = $user === null
            ? []
            : $user->workoutSessions()
                ->with(['sets', 'playlist:id,exercise_program_workout_id'])
                ->whereDate('date', now()->toDateString())
                ->whereHas('playlist', fn ($query) => $query->where('exercise_program_id', $program->id))
                ->get()
                ->filter(fn (WorkoutSession $session) => $session->playlist?->exercise_program_workout_id !== null)
                ->mapWithKeys(fn (WorkoutSession $session) => [
                    $session->playlist->exercise_program_workout_id => $session->toFrontend(),
                ])
                ->all();

        return Inertia::render('member/shared-program', [
            'program' => [
                'name' => $program->localizedName(),
                'owner' => $program->user->name,
                'description' => $program->localizedDescription(),
                'image_url' => $program->image_url,
                'highlights' => $program->localizedHighlights(),
                'workouts' => $program->workouts
                    ->map(fn (ExerciseProgramWorkout $workout): array => [
                        'id' => $workout->id,
                        'title' => $workout->title,
                        'exercises' => $workout->items
                            ->map(function (ExerciseProgramWorkoutItem $item) use ($exercises): ?array {
                                $exercise = $exercises->get($item->exercise_id);

                                return $exercise === null ? null : [
                                    ...$exercise->toSummary(),
                                    'gif_url' => $exercise->gif_url,
                                    'sets' => $item->sets,
                                    'reps' => $item->reps,
                                ];
                            })
                            ->filter()
                            ->values()
                            ->all(),
                    ])
                    ->all(),
            ],
            'todaysSessions' => (object) $todaysSessions,
            'isOwner' => $user?->id === $program->user_id,
            'isPro' => $user?->isPro() ?? false,
            'isCoachProgram' => $program->user->isTrainer(),
            'priceEtb' => $program->price_etb,
            'canClaim' => $user !== null && $this->canClaim($user, $program),
            'alreadyClaimed' => $user !== null && $this->grants->hasAccess($user, $program),
            'saveUrl' => route('programs.shared.save', $program->share_token),
            'startUrl' => route('programs.shared.start', $program->share_token),
            'checkoutUrl' => $program->isFree()
                ? null
                : route('programs.shared.checkout', $program->share_token),
            'providers' => [
                'chapa' => filled(config('services.chapa.secret_key')),
                'verify_checkout' => filled(config('services.verify_checkout.api_key')),
                'jami' => filled(config('services.jami.token')),
            ],
            'proUrl' => route('pro.show'),
            'homeUrl' => $user !== null ? route('member.home') : route('explore.index'),
            'editUrl' => $user?->isAdmin() ? route('admin.programs.edit', $program) : null,
        ]);
    }

    /**
     * Claim a program: record the access row and copy each workout into the
     * member's playlists. Requires Pro; idempotent.
     */
    public function save(Request $request, ExerciseProgram $program): JsonResponse
    {
        $this->authorizeClaim($request->user(), $program);

        $granted = $this->grants->grant($request->user(), $program);

        if ($granted) {
            app(Tracker::class)->record('shared_program.saved', subject: $program, properties: [
                'owner_id' => $program->user_id,
            ]);
        }

        return response()->json(['claimed' => true], $granted ? 201 : 200);
    }

    /**
     * Start (or resume) today's session for one program day. Claims the
     * program first when the member hasn't yet, so the session logs against
     * the member's own copy of that workout. Requires Pro.
     */
    public function start(Request $request, ExerciseProgram $program): JsonResponse
    {
        $user = $request->user();

        $this->authorizeClaim($user, $program);

        $data = $request->validate([
            'workout_id' => ['required', 'integer', Rule::exists('exercise_program_workouts', 'id')->where('exercise_program_id', $program->id)],
        ]);

        $workout = $program->workouts()->whereKey($data['workout_id'])->firstOrFail();

        if ($this->grants->grant($user, $program)) {
            app(Tracker::class)->record('shared_program.saved', subject: $program, properties: [
                'owner_id' => $program->user_id,
            ]);
        }

        $playlist = $this->grants->playlistForWorkout($user, $program, $workout);

        $session = WorkoutSession::where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->where('exercise_playlist_id', $playlist?->id)
            ->first();

        $resumed = $session !== null;

        $session ??= WorkoutSession::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'exercise_playlist_id' => $playlist?->id,
        ]);

        app(Tracker::class)->record('shared_program.workout_started', subject: $program, properties: [
            'workout_id' => $workout->id,
            'resumed' => $resumed,
        ]);

        return response()->json(
            ['session' => $session->load('sets')->toFrontend(), 'claimed' => true],
            $resumed ? 200 : 201,
        );
    }

    /**
     * Start a paid purchase for a priced program through the normal
     * provider checkout. Buyers of a trainer-authored plan are linked as
     * clients automatically.
     */
    public function checkout(Request $request, ExerciseProgram $program): SymfonyResponse|RedirectResponse
    {
        abort_if($program->isFree(), 422);

        $user = $request->user();

        $author = $program->user;

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
            'amount' => $program->price_etb,
            'currency' => 'ETB',
            'status' => 'pending',
            'purpose' => 'program_purchase',
            'payable_type' => $program->getMorphClass(),
            'payable_id' => $program->id,
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
                'message' => __('Approve the payment on your phone — the program unlocks once confirmed.'),
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
    private function canClaim(User $user, ExerciseProgram $program): bool
    {
        if (! $program->user->isTrainer()) {
            return $user->isPro();
        }

        if ($user->id === $program->user_id) {
            return true;
        }

        if (! $program->isFree()) {
            return false;
        }

        return $user->isPro() || $user->isClientOf($program->user);
    }

    /**
     * Gate a free claim: paid programs must go through checkout, everyone
     * else needs Pro or a client link to the coach.
     */
    private function authorizeClaim(User $user, ExerciseProgram $program): void
    {
        abort_unless($this->canClaim($user, $program), 403);
    }
}
