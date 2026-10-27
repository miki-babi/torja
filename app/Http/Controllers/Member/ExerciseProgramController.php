<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramWorkout;
use App\Models\ExerciseProgramWorkoutItem;
use App\Models\WorkoutSession;
use App\Services\Programs\ProgramAccessGranter;
use App\Support\ExerciseCatalog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member's own copy of a claimed exercise program: its workouts (as
 * playlists) with a start-session action per day. Only members holding a
 * claim (or admins) can open it.
 */
class ExerciseProgramController extends Controller
{
    public function __construct(
        private readonly ProgramAccessGranter $grants,
    ) {}

    /**
     * Program detail for a claiming member: copied workouts plus recent
     * sessions so a day can be started and logged inline.
     */
    public function show(Request $request, ExerciseProgram $program): Response
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $this->grants->hasAccess($user, $program), 404);

        $playlists = $user->exercisePlaylists()
            ->where('exercise_program_id', $program->id)
            ->whereNull('generated_for')
            ->with('items')
            ->orderBy('id')
            ->get();

        $sessions = $user->workoutSessions()
            ->with('sets')
            ->orderByDesc('date')
            ->limit(20)
            ->get();

        $exerciseIds = $playlists->flatMap->items->pluck('exercise_id')
            ->merge($sessions->flatMap->sets->pluck('exercise_id'))
            ->unique()
            ->all();

        app(Tracker::class)->record('member_program.viewed', subject: $program, properties: [
            'owner_id' => $program->user_id,
        ]);

        return Inertia::render('member/program-show', [
            'program' => [
                'id' => $program->id,
                'name' => $program->localizedName(),
                'image_url' => $program->image_url,
            ],
            'workouts' => $playlists->map->toFrontend()->all(),
            'sessions' => $sessions->map->toFrontend()->all(),
            'exerciseSummaries' => ExerciseCatalog::namesFor($exerciseIds),
            'viewSourceUrl' => $program->share_token === null
                ? null
                : route('programs.shared.show', $program->share_token),
        ]);
    }

    /**
     * Guided workout player for one program day. Claims the program first
     * when the member hasn't yet, then opens (or resumes) today's session
     * on their copy of that workout. Requires Pro or an existing claim.
     */
    public function play(Request $request, ExerciseProgramWorkout $workout): Response
    {
        $user = $request->user();
        $program = $workout->program;

        abort_unless($user->isPro() || $this->grants->hasAccess($user, $program), 403);

        $this->grants->grant($user, $program);

        $playlist = $this->grants->playlistForWorkout($user, $program, $workout);

        $session = WorkoutSession::where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->where('exercise_playlist_id', $playlist?->id)
            ->first() ?? WorkoutSession::create([
                'user_id' => $user->id,
                'date' => now()->toDateString(),
                'exercise_playlist_id' => $playlist?->id,
            ]);

        $workout->load('items');

        $exercises = Exercise::query()
            ->whereIn('slug', $workout->items->pluck('exercise_id'))
            ->get()
            ->keyBy('slug');

        app(Tracker::class)->record('member_program.workout_played', subject: $program, properties: [
            'workout_id' => $workout->id,
        ]);

        return Inertia::render('member/workout-player', [
            'program' => [
                'name' => $program->localizedName(),
            ],
            'workout' => [
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
            ],
            'session' => $session->load('sets')->toFrontend(),
            'backUrl' => route('programs.shared.show', $program->share_token),
        ]);
    }
}
