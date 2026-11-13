<?php

namespace App\Http\Responses\Concerns;

use App\Models\ProgramLead;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Shared post-auth landing: admins go to the admin area, everyone else to
 * the configured home path. Used by the Fortify response classes.
 */
trait RedirectsUsersByRole
{
    private function homeFor(?Authenticatable $user): string
    {
        if ($user instanceof User) {
            $this->linkPendingTenant($user);
            $this->claimPendingLead($user);
        }

        if ($user instanceof User && $user->isStaff()) {
            return route('admin.trainers.index');
        }

        if ($user instanceof User && $user->isTrainer()) {
            return route('trainer.dashboard');
        }

        return (string) config('fortify.home', '/dashboard');
    }

    /**
     * A guest who viewed a trainer's public page before signing in carries
     * the tenant slug in session — link them as that coach's client now.
     */
    private function linkPendingTenant(User $user): void
    {
        $slug = session()->pull('tenant');

        if (! is_string($slug) || $slug === '' || $user->isStaff() || $user->isTrainer()) {
            return;
        }

        $trainer = Trainer::query()->where('slug', $slug)->first();

        if ($trainer === null) {
            return;
        }

        $trainer->clients()->syncWithoutDetaching([$user->id]);
        $trainer->markClientOpened($user);
    }

    /**
     * A lead who submitted the free-program questionnaire carries the row
     * id in session — stamp their new account onto it so the trainer's
     * lead list shows them as converted.
     */
    private function claimPendingLead(User $user): void
    {
        $leadId = session()->pull('program_lead');

        if (! is_int($leadId)) {
            return;
        }

        ProgramLead::query()
            ->whereKey($leadId)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);
    }
}
