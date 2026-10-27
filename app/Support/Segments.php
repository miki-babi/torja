<?php

namespace App\Support;

use App\Models\UserTrait;
use Illuminate\Database\Eloquent\Builder;

/**
 * Named user segments computed over `user_traits`. Each definition is a
 * query callback so segments can be counted, listed, and CSV-exported for
 * Meta Custom Audiences and engagement campaigns.
 */
class Segments
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'fat_loss' => 'Fat-loss seekers',
            'muscle_gain' => 'Muscle builders',
            'recomp' => 'Recomp (lose + gain)',
            'consistent_trainers' => 'Training consistently (3+/week)',
            'slipping' => 'Slipping (was active, quiet this week)',
            'bodyweight_only' => 'Bodyweight-only exercisers',
            'equipped_lifters' => 'Equipped lifters',
            'meal_trackers' => 'Active meal trackers',
            'weight_watchers' => 'Regular weighers',
            'weight_stalled' => 'Stalled weight loss',
            'social_sharers' => 'Playlist sharers',
            'coach_leads' => 'Asked for coaching',
            'paid' => 'Paying customers',
            'window_shoppers' => 'Completed quiz, never paid',
            'dormant_14d' => 'Dormant 14+ days',
            'wizard_abandoned' => 'Abandoned the wizard',
            'plan_ready_unpaid' => 'Plan ready, never paid',
            'checkout_abandoned' => 'Abandoned checkout',
            'never_started' => 'Registered, never started wizard',
        ];
    }

    /**
     * Whether a segment key exists.
     */
    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::labels());
    }

    /**
     * The user_traits query for a segment.
     *
     * @return Builder<UserTrait>
     */
    public static function query(string $key): Builder
    {
        $base = UserTrait::query();

        return match ($key) {
            'fat_loss' => $base->where('primary_goal', 'lose_fat'),
            'muscle_gain' => $base->where('primary_goal', 'build_muscle'),
            'recomp' => $base->where('primary_goal', 'recomp'),
            'consistent_trainers' => $base->where('sessions_7d', '>=', 3),
            'slipping' => $base->where('sessions_7d', 0)->where('sessions_30d', '>=', 3),
            'bodyweight_only' => $base->whereJsonLength('equipment', '>=', 1)
                ->whereJsonDoesntContain('equipment', 'barbell')
                ->whereJsonDoesntContain('equipment', 'dumbbell')
                ->whereJsonDoesntContain('equipment', 'machine')
                ->whereJsonDoesntContain('equipment', 'cable')
                ->whereJsonDoesntContain('equipment', 'kettlebell')
                ->whereJsonDoesntContain('equipment', 'band'),
            'equipped_lifters' => $base->where(function ($q) {
                foreach (['barbell', 'dumbbell', 'machine', 'cable', 'kettlebell'] as $item) {
                    $q->orWhereJsonContains('equipment', $item);
                }
            }),
            'meal_trackers' => $base->where('food_logs_30d', '>=', 5),
            'weight_watchers' => $base->where('weight_logs_count', '>=', 4),
            'weight_stalled' => $base->where('primary_goal', 'lose_fat')
                ->where('weight_logs_count', '>=', 2)
                ->whereBetween('weight_delta_30d', [-1, 1]),
            'social_sharers' => $base->where('shared_playlists_count', '>=', 1),
            'coach_leads' => $base->where('wants_coach', true),
            'paid' => $base->where('paid', true),
            'window_shoppers' => $base->whereNotNull('primary_goal')->where('paid', false),
            'dormant_14d' => $base->where('last_seen_at', '<', now()->subDays(14)),
            'wizard_abandoned' => $base->where('wizard_status', 'in_progress')
                ->where('wizard_step', '>', 1)
                ->where('paid', false)
                ->where('last_seen_at', '<', now()->subDay()),
            'plan_ready_unpaid' => $base->where('wizard_status', 'completed')
                ->where('paid', false),
            'checkout_abandoned' => $base->where('paid', false)
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('payments')
                        ->whereColumn('payments.user_id', 'user_traits.user_id')
                        ->where('payments.status', 'pending')
                        ->where('payments.created_at', '<', now()->subHours(4));
                }),
            'never_started' => $base->whereNull('wizard_status')
                ->where('paid', false),
            default => $base->whereRaw('1 = 0'),
        };
    }
}
