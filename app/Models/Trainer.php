<?php

namespace App\Models;

use Database\Factories\TrainerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A coaching tenant: the trainer-facing account that owns a client roster,
 * authored programs, and (optionally) a dedicated Telegram bot whose mini
 * app is the client-facing surface. Clients are ordinary users linked
 * through the `trainer_clients` pivot.
 *
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string|null $brand_name
 * @property string|null $bio
 * @property string|null $specialty
 * @property array<int, string>|null $languages
 * @property int|null $years_experience
 * @property string|null $certification_path
 * @property string|null $bot_token
 * @property string|null $bot_username
 * @property string|null $mini_app_name
 * @property string|null $webhook_secret
 * @property Carbon|null $webhook_registered_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $menu_button_set_at
 * @property string|null $payout_method
 * @property string|null $payout_account
 * @property string|null $offer_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $display_name
 */
#[Fillable(['user_id', 'slug', 'brand_name', 'bio', 'specialty', 'languages', 'years_experience', 'certification_path', 'bot_token', 'bot_username', 'mini_app_name', 'webhook_secret', 'webhook_registered_at', 'menu_button_set_at', 'payout_method', 'payout_account', 'offer_text', 'suspended_at', 'reminders_enabled', 'quiet_hours_start', 'quiet_hours_end', 'lead_form_mode'])]
#[Hidden(['bot_token', 'webhook_secret'])]
class Trainer extends Model
{
    /** @use HasFactory<TrainerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bot_token' => 'encrypted',
            'languages' => 'array',
            'webhook_registered_at' => 'datetime',
            'suspended_at' => 'datetime',
            'menu_button_set_at' => 'datetime',
            'reminders_enabled' => 'boolean',
        ];
    }

    /**
     * The login account behind this trainer.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Members linked to this trainer as clients.
     *
     * @return BelongsToMany<User, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trainer_clients')
            ->withPivot('last_opened_at')
            ->withTimestamps();
    }

    /**
     * Lead-form modes: `required` = guests must answer before claiming,
     * `optional` = questionnaire shown but skippable, `off` = no form.
     *
     * @var list<string>
     */
    public const LEAD_FORM_MODES = ['required', 'optional', 'off'];

    /**
     * The trainer's custom claim-form questions, in display order.
     *
     * @return HasMany<TrainerLeadField, $this>
     */
    public function leadFields(): HasMany
    {
        return $this->hasMany(TrainerLeadField::class)->orderBy('position');
    }

    /**
     * The claim form this trainer's guests fill: custom questions when
     * configured, the preset questionnaire otherwise. Custom answers are
     * keyed by question label; preset answers keep their stable keys.
     *
     * @return list<array{key: string, label: string, type: string, options: list<array{value: string, label: string}>, required: bool}>
     */
    public function leadFormFields(): array
    {
        $fields = $this->leadFields()->get();

        if ($fields->isEmpty()) {
            return ProgramLead::presetFields();
        }

        return $fields->map(fn (TrainerLeadField $field): array => [
            'key' => $field->label,
            'label' => $field->label,
            'type' => $field->type,
            'options' => collect($field->options ?? [])
                ->map(fn (string $option): array => ['value' => $option, 'label' => $option])
                ->values()
                ->all(),
            'required' => $field->required,
        ])->all();
    }

    /**
     * Link a user as this trainer's client. A trainer can never be their
     * own client — the pivot row is skipped and `false` returned.
     */
    public function addClient(User $user): bool
    {
        if ($user->id === $this->user_id) {
            return false;
        }

        $this->clients()->syncWithoutDetaching([$user->id]);

        return true;
    }

    /**
     * Stamp the client's link as having just opened the app through this
     * trainer's surface — feeds `User::activeCoach()` so the member app
     * shows the coach whose bot or invite launched it.
     */
    public function markClientOpened(User $user): void
    {
        $this->clients()->updateExistingPivot($user->id, [
            'last_opened_at' => now(),
        ]);
    }

    /**
     * Text broadcasts this trainer sent to clients.
     *
     * @return HasMany<TrainerBroadcast, $this>
     */
    public function broadcasts(): HasMany
    {
        return $this->hasMany(TrainerBroadcast::class);
    }

    /**
     * Prospective clients captured through this trainer's free programs.
     *
     * @return HasMany<ProgramLead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(ProgramLead::class);
    }

    /**
     * Coaching plans this trainer sells on their public page.
     *
     * @return HasMany<TrainerPlan, $this>
     */
    public function plans(): HasMany
    {
        return $this->hasMany(TrainerPlan::class);
    }

    /**
     * Earnings ledger entries accrued from plan sales.
     *
     * @return HasMany<TrainerEarning, $this>
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(TrainerEarning::class);
    }

    /**
     * Payout batches staff have created/sent to this trainer.
     *
     * @return HasMany<TrainerPayout, $this>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(TrainerPayout::class);
    }

    /**
     * Exercise programs authored by the trainer's account.
     *
     * @return HasMany<ExerciseProgram, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(ExerciseProgram::class, 'user_id', 'user_id');
    }

    /**
     * Meal programs authored by the trainer's account.
     *
     * @return HasMany<MealProgram, $this>
     */
    public function mealPrograms(): HasMany
    {
        return $this->hasMany(MealProgram::class, 'user_id', 'user_id');
    }

    /**
     * The name clients see — the brand name when set, else the account name.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->brand_name
            ?? $this->user?->name
            ?? 'Coach');
    }

    /**
     * Whether a Telegram bot token has been connected.
     */
    /**
     * The trainer is suspended by staff — locked out of the trainer
     * area and their public page is hidden.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function hasConnectedBot(): bool
    {
        return $this->bot_token !== null;
    }

    /**
     * The public mini-app/invite URL clients use to reach this trainer.
     */
    public function miniAppUrl(): string
    {
        return url("/t/{$this->slug}");
    }

    /**
     * Deep link into the trainer's Telegram bot — where clients land after
     * claiming a program. Null when no bot is connected.
     */
    public function botUrl(): ?string
    {
        return $this->bot_username === null ? null : "https://t.me/{$this->bot_username}";
    }

    /**
     * The Telegram webhook URL this trainer's bot should post updates to.
     */
    public function webhookUrl(): string
    {
        return route('telegram.tenant.webhook', $this->slug);
    }

    /**
     * A unique tenant slug derived from a display name — `<name>-<random>`.
     */
    public static function mintSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'coach';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (static::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
