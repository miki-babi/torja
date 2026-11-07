<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\ExerciseProgram;
use App\Models\MealProgram;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trainer home: setup checklist, headline stats (clients, plans, revenue
 * from program sales), and the most recent client sign-ups.
 */
class DashboardController extends Controller
{
    /**
     * Render the trainer dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $trainer = $user->ensureTrainer();

        $revenue = (float) Payment::query()
            ->where('status', 'success')
            ->where(fn ($query) => $query
                ->where(fn ($inner) => $inner
                    ->where('payable_type', ExerciseProgram::class)
                    ->whereIn('payable_id', $user->exercisePrograms()->select('id')))
                ->orWhere(fn ($inner) => $inner
                    ->where('payable_type', MealProgram::class)
                    ->whereIn('payable_id', $user->mealPrograms()->select('id'))))
            ->sum('amount');

        return Inertia::render('trainer/dashboard', [
            'trainer' => [
                'display_name' => $trainer->display_name,
                'slug' => $trainer->slug,
                'invite_url' => $trainer->miniAppUrl(),
                'bot_connected' => $trainer->hasConnectedBot(),
                'bot_username' => $trainer->bot_username,
                'webhook_registered' => $trainer->webhook_registered_at !== null,
            ],
            'stats' => [
                'clients' => $trainer->clients()->count(),
                'programs' => $user->exercisePrograms()->count(),
                'meal_programs' => $user->mealPrograms()->count(),
                'broadcasts' => $trainer->broadcasts()->count(),
                'revenue' => $revenue,
            ],
            'recentClients' => $trainer->clients()
                ->withPivot('created_at')
                ->latest('trainer_clients.created_at')
                ->limit(8)
                ->get()
                ->map(fn (User $client): array => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'username' => $client->username,
                    'avatar' => $client->avatar,
                    'joined' => $client->pivot->created_at?->toDateString(),
                ])
                ->all(),
        ]);
    }
}
