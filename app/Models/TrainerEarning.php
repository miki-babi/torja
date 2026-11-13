<?php

namespace App\Models;

use Database\Factories\TrainerEarningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The trainer's share of a successful plan payment — gross minus the
 * platform commission. Rows without a payout_id form the trainer's
 * pending payout balance, which staff settle through trainer_payouts.
 *
 * @property int $id
 * @property int $trainer_id
 * @property int|null $client_plan_id
 * @property int $payment_id
 * @property string $gross_amount
 * @property string $commission_amount
 * @property string $net_amount
 * @property int|null $payout_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['trainer_id', 'client_plan_id', 'payment_id', 'gross_amount', 'commission_amount', 'net_amount', 'payout_id'])]
class TrainerEarning extends Model
{
    /** @use HasFactory<TrainerEarningFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    /**
     * The coaching tenant that earned this.
     *
     * @return BelongsTo<Trainer, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    /**
     * The subscription this earning paid for.
     *
     * @return BelongsTo<ClientPlan, $this>
     */
    public function clientPlan(): BelongsTo
    {
        return $this->belongsTo(ClientPlan::class);
    }

    /**
     * The Jami payment behind this earning.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The payout batch that settled this earning, if sent.
     *
     * @return BelongsTo<TrainerPayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(TrainerPayout::class);
    }
}
