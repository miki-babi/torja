<?php

namespace App\Models;

use Database\Factories\UserTraitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Materialized per-user trait row, recomputed nightly (and after key
 * events) from nutrition-profile answers, tracked events and domain tables.
 * Powers segmentation for retargeting and engagement.
 *
 * @property int $user_id
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 * @property string|null $last_route_name
 * @property string|null $channel
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $referrer_host
 * @property string|null $wizard_status
 * @property int|null $wizard_step
 * @property string|null $predicted_goal
 * @property string|null $goal_source
 * @property string|null $primary_goal
 * @property array<int, string>|null $motivations
 * @property string|null $plan_slug
 * @property bool $wants_coach
 * @property string|null $budget_amount_etb
 * @property array<int, string>|null $equipment
 * @property int $sessions_7d
 * @property int $sessions_30d
 * @property int $total_sessions
 * @property int $playlists_count
 * @property int $shared_playlists_count
 * @property int $share_saves_received
 * @property int $pageviews_7d
 * @property int $pageviews_30d
 * @property array<string, int>|null $feature_views_30d
 * @property int $food_logs_30d
 * @property int $water_logs_30d
 * @property int $habit_completions_30d
 * @property int $weight_logs_count
 * @property string|null $latest_weight_kg
 * @property string|null $weight_delta_30d
 * @property bool $paid
 * @property Carbon|null $computed_at
 */
#[Fillable(['user_id', 'first_seen_at', 'last_seen_at', 'last_route_name', 'channel', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer_host', 'wizard_status', 'wizard_step', 'predicted_goal', 'goal_source', 'primary_goal', 'motivations', 'plan_slug', 'wants_coach', 'budget_amount_etb', 'equipment', 'sessions_7d', 'sessions_30d', 'total_sessions', 'playlists_count', 'shared_playlists_count', 'share_saves_received', 'pageviews_7d', 'pageviews_30d', 'feature_views_30d', 'food_logs_30d', 'water_logs_30d', 'habit_completions_30d', 'weight_logs_count', 'latest_weight_kg', 'weight_delta_30d', 'paid', 'computed_at'])]
class UserTrait extends Model
{
    /** @use HasFactory<UserTraitFactory> */
    use HasFactory;

    /**
     * The table has no surrogate key; user_id is the primary key.
     */
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'motivations' => 'array',
            'equipment' => 'array',
            'feature_views_30d' => 'array',
            'wants_coach' => 'boolean',
            'paid' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
