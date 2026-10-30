<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string|null $phone
 * @property Carbon|null $phone_verified_at
 * @property string|null $email
 * @property string $role
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property array<string, mixed>|null $attribution
 * @property string|null $telegram_chat_id
 * @property string|null $telegram_link_token
 * @property int|null $telegram_id
 * @property string|null $telegram_username
 * @property string|null $referral_code
 * @property int|null $referred_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'username', 'phone', 'email', 'password', 'locale', 'telegram_chat_id', 'telegram_link_token', 'telegram_id', 'telegram_username', 'referral_code', 'referred_by_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'telegram_link_token', 'attribution'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'attribution' => 'array',
            'telegram_id' => 'integer',
        ];
    }

    /**
     * Normalize email to lowercase on write.
     *
     * @return Attribute<string|null, never>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => filled($value) ? Str::lower($value) : null,
        );
    }

    /**
     * Normalize username to lowercase on write.
     *
     * @return Attribute<string|null, never>
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => filled($value) ? Str::lower($value) : null,
        );
    }

    /**
     * Normalize phone to canonical `+<digits>` form on write.
     *
     * @return Attribute<string|null, never>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => PhoneNumber::normalize($value),
        );
    }

    /**
     * The user's primary login identifier, used as the Fortify username field
     * value (e.g. the TOTP account label in 2FA QR codes).
     *
     * @return Attribute<string, never>
     */
    protected function login(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->email
                ?? $this->phone
                ?? $this->username
                ?? ($this->telegram_id !== null ? "tg:{$this->telegram_id}" : "user-{$this->getKey()}"),
        );
    }

    /**
     * Accounts without an email address have nothing to verify.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email === null || $this->email_verified_at !== null;
    }

    /**
     * Skip sending the verification notification when the account has no email.
     */
    public function sendEmailVerificationNotification(): void
    {
        if ($this->email === null) {
            return;
        }

        parent::sendEmailVerificationNotification();
    }

    /**
     * The address the verification notification should be sent to.
     */
    public function getEmailForVerification(): string
    {
        return (string) $this->email;
    }

    /**
     * The user's nutrition-wizard profile.
     *
     * @return HasOne<NutritionProfile, $this>
     */
    public function nutritionProfile(): HasOne
    {
        return $this->hasOne(NutritionProfile::class);
    }

    /**
     * The user's exercise playlists.
     *
     * @return HasMany<ExercisePlaylist, $this>
     */
    public function exercisePlaylists(): HasMany
    {
        return $this->hasMany(ExercisePlaylist::class)->orderBy('name');
    }

    /**
     * The user's workout sessions.
     *
     * @return HasMany<WorkoutSession, $this>
     */
    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }

    /**
     * Exercise programs authored by this user (admins).
     *
     * @return HasMany<ExerciseProgram, $this>
     */
    public function exercisePrograms(): HasMany
    {
        return $this->hasMany(ExerciseProgram::class);
    }

    /**
     * Programs this user has claimed (saved or bought).
     *
     * @return HasMany<ExerciseProgramAccess, $this>
     */
    public function programAccess(): HasMany
    {
        return $this->hasMany(ExerciseProgramAccess::class);
    }

    /**
     * Meal programs authored by this user (admins).
     *
     * @return HasMany<MealProgram, $this>
     */
    public function mealPrograms(): HasMany
    {
        return $this->hasMany(MealProgram::class);
    }

    /**
     * Meal programs this user has claimed (saved or bought).
     *
     * @return HasMany<MealProgramAccess, $this>
     */
    public function mealProgramAccess(): HasMany
    {
        return $this->hasMany(MealProgramAccess::class);
    }

    /**
     * Meal plans owned by this user — copies of claimed meal programs.
     * The global seeded plans have user_id null and do not appear here.
     *
     * @return HasMany<MealPlan, $this>
     */
    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class)->orderBy('name');
    }

    /**
     * The user's daily habits.
     *
     * @return HasMany<Habit, $this>
     */
    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class);
    }

    /**
     * The user's plan-unlock payment attempts.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The user's materialized trait row used for segmentation.
     *
     * @return HasOne<UserTrait, $this>
     */
    public function traits(): HasOne
    {
        return $this->hasOne(UserTrait::class);
    }

    /**
     * The user's body-weight check-ins.
     *
     * @return HasMany<WeightLog, $this>
     */
    public function weightLogs(): HasMany
    {
        return $this->hasMany(WeightLog::class)->orderByDesc('logged_on');
    }

    /**
     * The user's food-diary entries.
     *
     * @return HasMany<FoodDiaryEntry, $this>
     */
    public function foodDiaryEntries(): HasMany
    {
        return $this->hasMany(FoodDiaryEntry::class);
    }

    /**
     * The ambassador who referred this user, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_id');
    }

    /**
     * Users this ambassador referred.
     *
     * @return HasMany<User, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_id');
    }

    /**
     * Commissions this ambassador earned from referred Pro purchases.
     *
     * @return HasMany<ReferralCommission, $this>
     */
    public function referralCommissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class, 'affiliate_user_id');
    }

    /**
     * This ambassador's payout requests.
     *
     * @return HasMany<ReferralPayout, $this>
     */
    public function referralPayouts(): HasMany
    {
        return $this->hasMany(ReferralPayout::class);
    }

    /**
     * The user's ambassador invite code, minted on first use.
     */
    public function ensureReferralCode(): string
    {
        if ($this->referral_code !== null) {
            return $this->referral_code;
        }

        do {
            $code = Str::lower(Str::random(8));
        } while (self::query()->where('referral_code', $code)->exists());

        $this->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    /**
     * The shareable invite URL carrying this user's referral code.
     */
    public function inviteUrl(): ?string
    {
        if ($this->referral_code === null) {
            return null;
        }

        return route('invite.show', $this->referral_code);
    }

    /**
     * Approved-but-unpaid commission total — the ambassador's payable
     * wallet balance. Commissions locked into an open payout request are
     * excluded so they can't be requested twice.
     */
    public function ambassadorBalance(): float
    {
        return (float) $this->referralCommissions()
            ->where('status', 'approved')
            ->whereNull('payout_id')
            ->sum('amount');
    }

    /**
     * Whether the user has admin-area access.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Whether the user holds the lifetime Pro membership — a successful
     * `pro` payment. Legacy per-program purchases don't count; they live
     * on the access rows instead.
     */
    public function isPro(): bool
    {
        return $this->payments()
            ->where('status', 'success')
            ->where('purpose', 'pro')
            ->exists();
    }

    /**
     * Consecutive days with any recorded activity — a workout session or a
     * habit log. If today has none yet, the streak counts from yesterday.
     */
    public function activityStreak(): int
    {
        $lookup = $this->activityDateLookup();
        $streak = 0;
        $day = today()->copy();

        if (! isset($lookup[$day->toDateString()])) {
            $day = $day->subDay();
        }
        while (isset($lookup[$day->toDateString()])) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /**
     * The last seven days, oldest first, flagging the ones with activity.
     *
     * @return array<int, array{date: string, label: string, active: bool}>
     */
    public function activityWeek(): array
    {
        $lookup = $this->activityDateLookup();

        return collect(range(6, 0))
            ->map(function (int $offset) use ($lookup): array {
                $day = today()->subDays($offset);

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->format('D'),
                    'active' => isset($lookup[$day->toDateString()]),
                ];
            })
            ->all();
    }

    /**
     * Lookup set of every date with recorded activity — a workout session
     * or a habit log.
     *
     * @return array<string, true>
     */
    private function activityDateLookup(): array
    {
        $dates = $this->workoutSessions()->pluck('date')
            ->merge(
                HabitLog::query()
                    ->whereIn('habit_id', $this->habits()->select('id'))
                    ->pluck('date')
            )
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all();

        return array_fill_keys($dates, true);
    }
}
