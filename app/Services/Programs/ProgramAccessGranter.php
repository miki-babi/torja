<?php

namespace App\Services\Programs;

use App\Models\ExercisePlaylist;
use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramAccess;
use App\Models\ExerciseProgramWorkout;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Grants a member access to an exercise program by recording a claim row and
 * copying each program workout into the member's account as an
 * ExercisePlaylist ("{program} — {workout}"). Copies are snapshots — later
 * program edits don't propagate.
 */
class ProgramAccessGranter
{
    /**
     * Record the claim and copy the program's workouts. Idempotent: if the
     * member already holds a claim (free save or earlier purchase) nothing is
     * copied again and false is returned.
     */
    public function grant(User $user, ExerciseProgram $program, ?Payment $payment = null): bool
    {
        $program->loadMissing('workouts.items');

        return DB::transaction(function () use ($user, $program, $payment): bool {
            $access = ExerciseProgramAccess::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'exercise_program_id' => $program->id,
                ],
                ['payment_id' => $payment?->id],
            );

            if (! $access->wasRecentlyCreated) {
                return false;
            }

            $program->workouts->each(
                fn (ExerciseProgramWorkout $workout) => $this->copyWorkout($user, $program, $workout),
            );

            return true;
        });
    }

    /**
     * Whether the member already holds a claim on the program.
     */
    public function hasAccess(User $user, ExerciseProgram $program): bool
    {
        return ExerciseProgramAccess::where('user_id', $user->id)
            ->where('exercise_program_id', $program->id)
            ->exists();
    }

    /**
     * The member's copy of one program workout. Copies made before the
     * workout link existed are matched by their generated name.
     */
    public function playlistForWorkout(User $user, ExerciseProgram $program, ExerciseProgramWorkout $workout): ?ExercisePlaylist
    {
        return $user->exercisePlaylists()
            ->where('exercise_program_id', $program->id)
            ->where(fn ($query) => $query
                ->where('exercise_program_workout_id', $workout->id)
                ->orWhere(fn ($legacy) => $legacy
                    ->whereNull('exercise_program_workout_id')
                    ->where('name', "{$program->name} — {$workout->title}")))
            ->orderByRaw('exercise_program_workout_id is null')
            ->first();
    }

    /**
     * Copy one program workout into the member's playlists.
     */
    private function copyWorkout(User $user, ExerciseProgram $program, ExerciseProgramWorkout $workout): void
    {
        $playlist = $user->exercisePlaylists()->create([
            'name' => "{$program->name} — {$workout->title}",
            'exercise_program_id' => $program->id,
            'exercise_program_workout_id' => $workout->id,
        ]);

        $playlist->items()->createMany(
            $workout->items
                ->map(fn ($item): array => [
                    'exercise_id' => $item->exercise_id,
                    'position' => $item->position,
                ])
                ->all(),
        );
    }
}
