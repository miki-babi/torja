<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\ExerciseProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Today's workout — the client's landing screen. Shows the coach-assigned
 * program and which day is next (the first workout with no session this
 * week), plus a weekly check-in nudge and a this-week stats strip.
 */
class TodayController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $coach = $user->activeCoach();

        $programs = $user->programAccess()
            ->whereHas('program', fn ($query) => $query->where('user_id', $coach?->user_id ?? -1))
            ->with(['program.workouts:id,exercise_program_id,title,position'])
            ->latest('id')
            ->get()
            ->map->program
            ->filter();

        $program = $programs->first();

        $doneWorkoutIds = $user->workoutSessions()
            ->whereDate('date', '>=', now()->startOfWeek())
            ->whereNotNull('exercise_playlist_id')
            ->with('playlist:id,exercise_program_workout_id')
            ->get()
            ->pluck('playlist.exercise_program_workout_id')
            ->filter()
            ->all();

        $workouts = $program === null ? [] : $program->workouts
            ->map(fn ($workout): array => [
                'id' => $workout->id,
                'title' => $workout->title,
                'exercises_count' => $workout->items()->count(),
                'done' => in_array($workout->id, $doneWorkoutIds, true),
            ])
            ->values()
            ->all();

        $nextWorkout = collect($workouts)->firstWhere('done', false)
            ?? collect($workouts)->first();

        $checkinDue = ! CheckIn::query()
            ->where('user_id', $user->id)
            ->whereDate('week_of', CheckIn::currentWeek())
            ->exists();

        return Inertia::render('member/today', [
            'userName' => Str::before(trim((string) $user->name), ' ') ?: 'there',
            'streak' => $user->activityStreak(),
            'program' => $program === null ? null : [
                'id' => $program->id,
                'name' => $program->localizedName(),
                'image_url' => $program->image_url,
                'url' => route('member.programs.show', $program),
                'workouts_count' => $program->workouts->count(),
            ],
            'workouts' => $workouts,
            'nextWorkoutId' => $nextWorkout['id'] ?? null,
            'nextWorkoutUrl' => isset($nextWorkout)
                ? route('member.program-workouts.play', $nextWorkout['id'])
                : null,
            'programs' => $programs->skip(1)->map(fn (ExerciseProgram $other): array => [
                'id' => $other->id,
                'name' => $other->localizedName(),
                'url' => route('member.programs.show', $other),
            ])->values()->all(),
            'checkinDue' => $checkinDue,
            'checkinUrl' => route('member.check-ins.index'),
            'weekStats' => [
                'sessions' => $user->workoutSessions()
                    ->whereDate('date', '>=', now()->startOfWeek())
                    ->has('sets')
                    ->count(),
            ],
        ]);
    }
}
