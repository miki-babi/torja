<?php

namespace App\Models;

use App\Services\Programs\MealProgramAccessGranter;
use App\Services\Programs\ProgramAccessGranter;
use App\Support\Tracking\Tracker;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A payment attempt for the lifetime Pro unlock, via Chapa or Verify
 * Checkout. `tx_ref` is the reference we generate and send to Chapa; for
 * Verify Checkout, `deposit_id` stores the authoritative deposit id and
 * `idempotency_key` the key persisted before the create call.
 * `provider_response` keeps the raw payload from the last initialize/verify
 * call for admin inspection. `purpose` says what the payment buys: `pro`
 * (the default) or, for legacy rows, `program_purchase` /
 * `meal_program_purchase`, in which case `payable` points at the
 * ExerciseProgram or MealProgram that was bought.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $tx_ref
 * @property string|null $deposit_id
 * @property string|null $idempotency_key
 * @property string $amount
 * @property string $currency
 * @property string $status
 * @property string $purpose
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property string $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property array<string, mixed>|null $provider_response
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'provider', 'tx_ref', 'deposit_id', 'idempotency_key', 'amount', 'currency', 'status', 'purpose', 'payable_type', 'payable_id', 'email', 'first_name', 'last_name', 'provider_response', 'paid_at', 'refunded_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'provider' => 'chapa',
        'currency' => 'ETB',
        'status' => 'pending',
        'purpose' => 'pro',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider_response' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The purchasable this legacy payment was for (e.g. an ExerciseProgram),
     * or null for Pro payments.
     *
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether the provider has confirmed this payment.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Whether this attempt goes through Chapa.
     */
    public function isChapa(): bool
    {
        return $this->provider === 'chapa';
    }

    /**
     * Whether this attempt goes through Verify Checkout.
     */
    public function isVerifyCheckout(): bool
    {
        return $this->provider === 'verify_checkout';
    }

    /**
     * Whether this attempt goes through Jami.
     */
    public function isJami(): bool
    {
        return $this->provider === 'jami';
    }

    /**
     * Mark the payment as confirmed by its provider, record the conversion
     * event and fulfill the purchase (Pro unlock, or program access for
     * legacy rows). Single transition point used by the callback, webhook,
     * user re-verify and admin re-verify paths.
     */
    public function markSuccessful(): void
    {
        $this->update([
            'status' => 'success',
            'paid_at' => $this->paid_at ?? now(),
        ]);

        app(Tracker::class)->record('payment.succeeded', $this->user, [
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'tx_ref' => $this->tx_ref,
            'provider' => $this->provider,
            'purpose' => $this->purpose,
        ], $this);

        $payable = $this->payable;

        if ($payable instanceof ExerciseProgram) {
            app(ProgramAccessGranter::class)->grant($this->user, $payable, $this);
        } elseif ($payable instanceof MealProgram) {
            app(MealProgramAccessGranter::class)->grant($this->user, $payable, $this);
        }
    }
}
