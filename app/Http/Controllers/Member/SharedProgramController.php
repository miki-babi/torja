<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramWorkout;
use App\Models\ExerciseProgramWorkoutItem;
use App\Models\WorkoutSession;
use App\Services\Programs\ProgramAccessGranter;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SharedProgramController extends Controller
{
    public function __construct(
        private readonly ProgramAccessGranter $grants,
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
            'alreadyClaimed' => $user !== null && $this->grants->hasAccess($user, $program),
            'saveUrl' => route('programs.shared.save', $program->share_token),
            'startUrl' => route('programs.shared.start', $program->share_token),
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
        abort_unless($request->user()->isPro(), 403);

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

        abort_unless($user->isPro(), 403);

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
}
