<?php

namespace App\Enums;

/**
 * The wizard sections saved to `nutrition_profiles`, one JSON column each.
 * Step 1 is the intro screen; sections map to steps 2–13.
 */
enum WizardSection: string
{
    case Goal = 'goal';
    case Body = 'body';
    case Activity = 'activity';
    case Pace = 'pace';
    case Diet = 'diet';
    case LikedFoods = 'liked_foods';
    case Avoidances = 'avoidances';
    case Habits = 'habits';
    case Schedule = 'schedule';
    case Budget = 'budget';
    case Cooking = 'cooking';
    case Obstacles = 'obstacles';

    /**
     * The 1-based wizard step this section renders on.
     */
    public function step(): int
    {
        return match ($this) {
            self::Goal => 2,
            self::Body => 3,
            self::Activity => 4,
            self::Pace => 5,
            self::Diet => 6,
            self::LikedFoods => 7,
            self::Avoidances => 8,
            self::Habits => 9,
            self::Schedule => 10,
            self::Budget => 11,
            self::Cooking => 12,
            self::Obstacles => 13,
        };
    }

    /**
     * The section rendered on a given wizard step, or null for the intro step.
     */
    public static function fromStep(int $step): ?self
    {
        foreach (self::cases() as $section) {
            if ($section->step() === $step) {
                return $section;
            }
        }

        return null;
    }

    /**
     * All section route values, for route parameter constraints.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
