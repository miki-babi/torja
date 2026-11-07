<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ExerciseProgramAccess;
use App\Models\MealProgramAccess;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The trainer's client roster: search, detail with progress snapshot, DM
 * shortcut, and unlink. Clients link themselves via the bot or the /t/slug
 * invite — the roster is read-only plus unlink.
 */
class ClientsController extends Controller
{
    /**
     * Paginated client list with search.
     */
    public function index(Request $request): Response
    {
        $trainer = $request->user()->ensureTrainer();

        $search = trim((string) $request->query('search', ''));

        $clients = $trainer->clients()
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->withCount('workoutSessions')
            ->withPivot('created_at')
            ->latest('trainer_clients.created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'username' => $client->username,
                'avatar' => $client->avatar,
                'joined' => $client->pivot->created_at?->toDateString(),
                'sessions_count' => $client->workout_sessions_count,
                'on_telegram' => $client->telegramRecipientId() !== null,
            ]);

        return Inertia::render('trainer/clients/index', [
            'clients' => $clients,
            'filters' => ['search' => $search],
            'invite_url' => $trainer->miniAppUrl(),
        ]);
    }

    /**
     * Client detail: profile, progress snapshot, and assigned plans.
     */
    public function show(Request $request, User $user): Response
    {
        $owner = $request->user();
        $trainer = $owner->ensureTrainer();

        abort_unless($owner->isTrainerOf($user), 404);

        $weekSessions = $user->workoutSessions()
            ->with('sets')
            ->whereDate('date', '>=', now()->startOfWeek())
            ->get();

        $programClaims = ExerciseProgramAccess::query()
            ->where('user_id', $user->id)
            ->whereHas('program', fn ($query) => $query->where('user_id', $owner->id))
            ->with('program:id,name')
            ->latest('id')
            ->get();

        $mealClaims = MealProgramAccess::query()
            ->where('user_id', $user->id)
            ->whereHas('program', fn ($query) => $query->where('user_id', $owner->id))
            ->with('program:id,name')
            ->latest('id')
            ->get();

        return Inertia::render('trainer/clients/show', [
            'client' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'phone' => $user->phone,
                'joined' => $trainer->clients()
                    ->where('users.id', $user->id)
                    ->first()?->pivot->created_at?->toDateString(),
                'on_telegram' => $user->telegramRecipientId() !== null,
                'streak' => $user->activityStreak(),
            ],
            'progress' => [
                'week_sessions' => $weekSessions->filter(
                    fn ($session) => $session->sets->isNotEmpty(),
                )->count(),
                'week_volume' => $weekSessions->sum(
                    fn ($session) => $session->sets->sum(
                        fn ($set) => ($set->weight_kg ?? 0) * ($set->reps ?? 0),
                    ),
                ),
                'sessions' => $user->workoutSessions()
                    ->with('sets')
                    ->orderByDesc('date')
                    ->limit(15)
                    ->get()
                    ->map(fn ($session): array => [
                        'id' => $session->id,
                        'date' => $session->date->toDateString(),
                        'sets' => $session->sets->count(),
                        'volume' => $session->sets->sum(
                            fn ($set) => ($set->weight_kg ?? 0) * ($set->reps ?? 0),
                        ),
                    ])
                    ->all(),
                'weight_logs' => $user->weightLogs()
                    ->orderBy('logged_on')
                    ->limit(30)
                    ->get()
                    ->map(fn ($log): array => [
                        'date' => $log->logged_on->toDateString(),
                        'weight_kg' => (float) $log->weight_kg,
                    ])
                    ->all(),
            ],
            'assigned' => [
                'programs' => $programClaims->map(fn ($access): array => [
                    'id' => $access->program->id,
                    'name' => $access->program->name,
                    'assigned' => $access->created_at->toDateString(),
                ])->all(),
                'meal_programs' => $mealClaims->map(fn ($access): array => [
                    'id' => $access->program->id,
                    'name' => $access->program->name,
                    'assigned' => $access->created_at->toDateString(),
                ])->all(),
            ],
            'assignable' => [
                'programs' => $owner->exercisePrograms()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->all(),
                'meal_programs' => $owner->mealPrograms()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->all(),
            ],
        ]);
    }

    /**
     * Remove the member from the roster. Plan copies already granted are
     * snapshots and stay with the member.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->user()->ensureTrainer()->clients()->detach($user->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Client removed.'),
        ]);

        return to_route('trainer.clients.index');
    }

    /**
     * Open (or create) the DM room with this client.
     */
    public function message(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isTrainerOf($user), 404);

        $conversation = Conversation::dmBetween($request->user(), $user);

        return to_route('member.messages.show', $conversation);
    }
}
