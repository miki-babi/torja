<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $avatar_path
 * @property-read string|null $avatar
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
#[Fillable(['name', 'username', 'phone', 'email', 'password', 'locale', 'telegram_chat_id', 'telegram_link_token', 'telegram_id', 'telegram_username', 'referral_code', 'referred_by_id', 'gym_id', 'leaderboard_opted_in'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'telegram_link_token', 'attribution'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['avatar'];

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
            'leaderboard_opted_in' => 'boolean',
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
     * Public URL of the user's profile photo, stored on the public disk.
     *
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->avatar_path === null
                ? null
                : Storage::disk('public')->url($this->avatar_path),
        );
    }

    /**
     * Store a new profile photo on the public disk, replacing the old
     * file if one exists.
     */
    public function updateAvatar(UploadedFile $file): void
    {
        $this->deleteAvatarFile();

        $this->avatar_path = $file->store("avatars/{$this->id}", 'public');
        $this->save();
    }

    /**
     * Remove the user's profile photo and its stored file.
     */
    public function deleteAvatar(): void
    {
        if ($this->avatar_path === null) {
            return;
        }

        $this->deleteAvatarFile();

        $this->avatar_path = null;
        $this->save();
    }

    /**
     * Delete the stored avatar file if the column points at one.
     */
    private function deleteAvatarFile(): void
    {
        if ($this->avatar_path !== null) {
            Storage::disk('public')->delete($this->avatar_path);
        }
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
     * Recap posts this member shared to the home feed.
     *
     * @return HasMany<Recap, $this>
     */
    public function recaps(): HasMany
    {
        return $this->hasMany(Recap::class);
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
     * The user's body-measurement check-ins, newest first.
     *
     * @return HasMany<BodyMeasurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class)->orderByDesc('logged_on');
    }

    /**
     * The user's progress photos, newest first.
     *
     * @return HasMany<ProgressPhoto, $this>
     */
    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class)->orderByDesc('taken_on');
    }

    /**
     * Friendships this user initiated or received.
     *
     * @return HasMany<Friendship, $this>
     */
    public function friendships(): HasMany
    {
        return $this->hasMany(Friendship::class);
    }

    /**
     * Accepted friend ids in both directions.
     *
     * @return array<int>
     */
    public function friendIds(): array
    {
        return Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q
                ->where('user_id', $this->id)
                ->orWhere('friend_id', $this->id))
            ->get(['user_id', 'friend_id'])
            ->map(fn (Friendship $f): int => $f->user_id === $this->id
                ? $f->friend_id
                : $f->user_id)
            ->all();
    }

    /**
     * Squads the user belongs to on the Ranks page.
     *
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->withTimestamps();
    }

    /**
     * The gym this member picked from the shared list.
     *
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Set the member's gym and move their gym-chat seat along with it —
     * joining the new room and leaving the old one.
     */
    public function setGym(?Gym $gym): void
    {
        $previous = $this->gym_id;

        if ($previous === $gym?->id) {
            return;
        }

        $this->gym()->associate($gym);
        $this->save();

        Conversation::where('gym_id', $previous)->first()?->removeParticipant($this);

        if ($gym !== null) {
            Conversation::forGym($gym)->addParticipant($this);
        }
    }

    /**
     * Chat rooms the user sits in.
     *
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
            ->withPivot(['last_read_message_id', 'muted_at', 'joined_at', 'last_notified_at'])
            ->withTimestamps();
    }

    /**
     * Total unread chat messages across every room the user sits in —
     * drives the badge on the Ranks chats entry.
     */
    public function unreadChatCount(): int
    {
        return (int) DB::table('messages')
            ->join('conversation_user', function ($join): void {
                $join->on('conversation_user.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_user.user_id', '=', $this->id);
            })
            ->whereNull('messages.deleted_at')
            ->where('messages.user_id', '!=', $this->id)
            ->whereRaw('messages.id > COALESCE(conversation_user.last_read_message_id, 0)')
            ->count();
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
     * Pro memberships granted to this user without payment — referral
     * rewards and admin gifts, including revoked ones (kept for audit).
     *
     * @return HasMany<ProGrant, $this>
     */
    public function proGrants(): HasMany
    {
        return $this->hasMany(ProGrant::class);
    }

    /**
     * The grant currently unlocking Pro, if any.
     *
     * @return HasOne<ProGrant, $this>
     */
    public function activeProGrant(): HasOne
    {
        return $this->hasOne(ProGrant::class)
            ->whereNull('revoked_at')
            ->latest('id');
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
     * The shareable invite URL carrying this user's referral code — a
     * Telegram Mini App link (`t.me/<bot>[/<app>]?startapp=ref_<code>`)
     * when the bot is configured, falling back to the web `/r/{code}`
     * route otherwise.
     */
    public function inviteUrl(): ?string
    {
        if ($this->referral_code === null) {
            return null;
        }

        $bot = config('services.telegram.bot_username');

        if (! is_string($bot) || $bot === '') {
            return route('invite.show', $this->referral_code);
        }

        $app = config('services.telegram.mini_app_name');
        $app = is_string($app) && $app !== '' ? "/{$app}" : '';

        return "https://t.me/{$bot}{$app}?startapp=ref_{$this->referral_code}";
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
     * Whether the user holds a paid lifetime Pro membership — a
     * successful `pro` payment. Legacy per-program purchases don't
     * count; they live on the access rows instead.
     */
    public function isPaidPro(): bool
    {
        return $this->payments()
            ->where('status', 'success')
            ->where('purpose', 'pro')
            ->exists();
    }

    /**
     * Whether the user holds a granted Pro membership — earned via the
     * ambassador referral milestone or given out by an admin, and not
     * revoked.
     */
    public function isGrantedPro(): bool
    {
        return $this->proGrants()->active()->exists();
    }

    /**
     * Whether the user holds any Pro membership — paid or granted.
     */
    public function isPro(): bool
    {
        return $this->isPaidPro() || $this->isGrantedPro();
    }

    /**
     * Which kind of Pro the user holds — `paid` takes precedence when a
     * granted member later buys the membership outright.
     */
    public function proType(): ?string
    {
        if ($this->isPaidPro()) {
            return 'paid';
        }

        return $this->isGrantedPro() ? 'granted' : null;
    }

    /**
     * Users the bot can reach by DM — a linked private chat, or a
     * Telegram identity (a private chat_id equals the user id) whose
     * message permission we may not have recorded yet.
     */
    public function scopeTelegramReachable(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->whereNotNull('telegram_chat_id')
            ->orWhereNotNull('telegram_id'));
    }

    /**
     * The chat id the bot should DM this user on — the linked private
     * chat when known, else the Telegram user id (identical for 1:1
     * chats).
     */
    public function telegramRecipientId(): ?string
    {
        $id = $this->telegram_chat_id ?? $this->telegram_id;

        return $id === null ? null : (string) $id;
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
