<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExerciseProgram;
use App\Models\MealProgram;
use App\Models\Payment;
use App\Models\Trainer;
use App\Models\TrainerClient;
use App\Models\User;
use App\Services\Trainer\BotConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform admin management of coach tenants: overview stats, trainer
 * detail, promote/demote, suspend a trainer's bot, and delete a tenant.
 */
class TrainersController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Trainer::query()
            ->with(['user:id,name,email,avatar_path,created_at'])
            ->withCount(['clients', 'programs', 'mealPrograms'])
            ->latest();

        $search = trim((string) $request->string('search'));

        if ($search !== '') {
            $query->where(fn ($inner) => $inner
                ->where('slug', 'like', "%{$search}%")
                ->orWhere('brand_name', 'like', "%{$search}%")
                ->orWhere('bot_username', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($users) => $users
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")));
        }

        $trainers = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/trainers/index', [
            'trainers' => $trainers->through(fn (Trainer $trainer): array => [
                'id' => $trainer->id,
                'name' => $trainer->display_name,
                'slug' => $trainer->slug,
                'avatar' => $trainer->user?->avatar,
                'email' => $trainer->user?->email,
                'bot_username' => $trainer->bot_username,
                'bot_connected' => $trainer->hasConnectedBot(),
                'clients_count' => $trainer->clients_count,
                'programs_count' => $trainer->programs_count,
                'meal_programs_count' => $trainer->meal_programs_count,
                'revenue' => $this->revenueFor($trainer),
                'joined' => $trainer->created_at->toDateString(),
            ]),
            'filters' => ['search' => $search],
            'stats' => [
                'trainers' => Trainer::count(),
                'clients' => TrainerClient::count(),
                'revenue' => (float) Payment::query()
                    ->where('status', 'success')
                    ->where(fn ($q) => $q
                        ->where('payable_type', ExerciseProgram::class)
                        ->orWhere('payable_type', MealProgram::class))
                    ->sum('amount'),
            ],
        ]);
    }

    public function show(Trainer $trainer): Response
    {
        $trainer->load([
            'user:id,name,email,username,avatar_path,created_at,telegram_id',
            'clients' => fn ($query) => $query->latest('trainer_clients.created_at')->limit(10),
            'programs' => fn ($query) => $query->latest()->limit(10),
            'mealPrograms' => fn ($query) => $query->latest()->limit(10),
            'broadcasts' => fn ($query) => $query->latest()->limit(10),
        ]);

        return Inertia::render('admin/trainers/show', [
            'trainer' => [
                'id' => $trainer->id,
                'slug' => $trainer->slug,
                'invite_url' => $trainer->miniAppUrl(),
                'bot_connected' => $trainer->hasConnectedBot(),
                'bot_username' => $trainer->bot_username,
                'webhook_registered' => $trainer->webhook_registered_at !== null,
                'user' => [
                    'id' => $trainer->user->id,
                    'name' => $trainer->user->name,
                    'email' => $trainer->user->email,
                    'avatar' => $trainer->user->avatar,
                    'joined' => $trainer->user->created_at->toDateString(),
                    'on_telegram' => $trainer->user->telegram_id !== null,
                ],
                'revenue' => $this->revenueFor($trainer),
                'counts' => [
                    'clients' => $trainer->clients()->count(),
                    'programs' => $trainer->programs()->count(),
                    'meal_programs' => $trainer->mealPrograms()->count(),
                    'broadcasts' => $trainer->broadcasts()->count(),
                ],
            ],
            'clients' => $trainer->clients->map(fn (User $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'avatar' => $client->avatar,
                'joined' => $client->pivot->created_at?->toDateString(),
            ])->all(),
            'programs' => $trainer->programs->map(fn (ExerciseProgram $program): array => [
                'id' => $program->id,
                'name' => $program->localizedName(),
                'price_etb' => $program->price_etb,
                'is_free' => $program->isFree(),
                'shared' => $program->share_token !== null,
            ])->all(),
            'mealPrograms' => $trainer->mealPrograms->map(fn (MealProgram $program): array => [
                'id' => $program->id,
                'name' => $program->localizedName(),
                'price_etb' => $program->price_etb,
                'is_free' => $program->isFree(),
                'shared' => $program->share_token !== null,
            ])->all(),
            'broadcasts' => $trainer->broadcasts->map(fn ($broadcast): array => [
                'id' => $broadcast->id,
                'body' => $broadcast->body,
                'sent_count' => $broadcast->sent_count,
                'created_at' => $broadcast->created_at->toDateTimeString(),
            ])->all(),
        ]);
    }

    /**
     * Promote an existing member to trainer by email.
     */
    public function promote(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $validated['email'])->first();

        if ($user === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('No member found with that email.')]);

            return back();
        }

        if ($user->isTrainer()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __(':name is already a trainer.', ['name' => $user->name])]);

            return back();
        }

        $user->forceFill(['role' => 'trainer'])->save();
        $user->ensureTrainer();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now a trainer.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Revoke the trainer role; the tenant data stays intact.
     */
    public function demote(Trainer $trainer): RedirectResponse
    {
        $trainer->user->forceFill(['role' => 'member'])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name demoted — they keep their coach data but lose trainer access.', ['name' => $trainer->display_name]),
        ]);

        return back();
    }

    /**
     * Disconnect a trainer's bot — deletes the webhook and clears the token.
     */
    public function suspendBot(Trainer $trainer, BotConnector $connector): RedirectResponse
    {
        $connector->disconnect($trainer);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bot disconnected for :name.', ['name' => $trainer->display_name])]);

        return back();
    }

    /**
     * Delete the trainer tenant. The user account and their authored
     * programs stay; client links are removed.
     */
    public function destroy(Trainer $trainer): RedirectResponse
    {
        $name = $trainer->display_name;

        $trainer->clients()->detach();
        $trainer->broadcasts()->delete();
        $trainer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trainer :name deleted.', ['name' => $name])]);

        return to_route('admin.trainers.index');
    }

    /**
     * Read-only revenue from successful payments on this trainer's plans.
     */
    private function revenueFor(Trainer $trainer): float
    {
        return (float) Payment::query()
            ->where('status', 'success')
            ->where(fn ($query) => $query
                ->where(fn ($inner) => $inner
                    ->where('payable_type', ExerciseProgram::class)
                    ->whereIn('payable_id', $trainer->programs()->select('id')))
                ->orWhere(fn ($inner) => $inner
                    ->where('payable_type', MealProgram::class)
                    ->whereIn('payable_id', $trainer->mealPrograms()->select('id'))))
            ->sum('amount');
    }
}
