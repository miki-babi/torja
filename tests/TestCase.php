<?php

namespace Tests;

use App\Models\Trainer;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * The member app gates on having a coach, so tests acting as a plain
     * member get linked to a fresh trainer on sign-in — mirroring the
     * production invariant that members arrive through a coach's bot or
     * invite. Set to false to act as a coachless member (gate tests).
     */
    protected bool $autoLinkActingMemberToCoach = true;

    public function actingAs($user, $guard = null)
    {
        if ($this->autoLinkActingMemberToCoach
            && $user instanceof User
            && ! $user->isAdmin()
            && ! $user->isTrainer()
            && ! $user->trainers()->exists()) {
            Trainer::factory()->create()->clients()->attach($user->id);
        }

        return parent::actingAs($user, $guard);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
