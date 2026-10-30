<?php

namespace App\Models;

use App\Enums\TelegramBroadcastStatus;
use App\Enums\TelegramBroadcastType;
use Carbon\CarbonInterface;
use Database\Factories\TelegramBroadcastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A composed Telegram broadcast — message payload, audience, schedule —
 * plus its send state. `body_html` keeps the Tiptap source for editing;
 * `telegram_text` is the converted parse_mode=HTML payload actually sent.
 *
 * @property int $id
 * @property string $name
 * @property TelegramBroadcastType $type
 * @property string|null $body_html
 * @property string|null $telegram_text
 * @property array<int, array<string, mixed>>|null $media
 * @property array<int, array<int, array<string, mixed>>>|null $buttons
 * @property array<string, mixed>|null $poll
 * @property array<string, mixed>|null $options
 * @property array{everyone: bool, segments: array<int, string>, user_ids: array<int, int>} $audience
 * @property TelegramBroadcastStatus $status
 * @property Carbon|null $scheduled_at
 * @property string $recurrence
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_run_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'type', 'body_html', 'telegram_text', 'media', 'buttons', 'poll', 'options', 'audience', 'status', 'scheduled_at', 'recurrence', 'next_run_at', 'last_run_at', 'created_by'])]
class TelegramBroadcast extends Model
{
    /** @use HasFactory<TelegramBroadcastFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TelegramBroadcastType::class,
            'status' => TelegramBroadcastStatus::class,
            'media' => 'array',
            'buttons' => 'array',
            'poll' => 'array',
            'options' => 'array',
            'audience' => 'array',
            'scheduled_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * Every dispatch occurrence of this broadcast.
     *
     * @return HasMany<TelegramBroadcastRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(TelegramBroadcastRun::class, 'broadcast_id');
    }

    /**
     * Per-recipient delivery rows across all runs.
     *
     * @return HasMany<TelegramBroadcastDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(TelegramBroadcastDelivery::class, 'broadcast_id');
    }

    /**
     * The admin who composed the broadcast.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether the composer may still edit the payload.
     */
    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Whether the broadcast repeats on a cadence.
     */
    public function isRecurring(): bool
    {
        return $this->recurrence !== 'none';
    }

    /**
     * The next dispatch time after a run, or null when not recurring.
     */
    public function nextRunAfter(CarbonInterface $from): ?CarbonInterface
    {
        // Work on a mutable copy so either Carbon flavor is safe.
        $from = Carbon::instance($from);

        return match ($this->recurrence) {
            'daily' => $from->addDay(),
            'weekly' => $from->addWeek(),
            'monthly' => $from->addMonth(),
            default => null,
        };
    }

    /**
     * Delivery flags shared by every send* call.
     *
     * @return array{disable_notification: bool, protect_content: bool}
     */
    public function sendFlags(): array
    {
        return [
            'disable_notification' => (bool) ($this->options['disable_notification'] ?? false),
            'protect_content' => (bool) ($this->options['protect_content'] ?? false),
        ];
    }
}
