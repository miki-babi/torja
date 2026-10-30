<?php

namespace App\Models;

use Database\Factories\ShopOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cash-on-delivery order for one shop product. Prices are snapshotted
 * at order time so later price edits don't rewrite history.
 *
 * @property int $id
 * @property int $user_id
 * @property int $shop_product_id
 * @property int $quantity
 * @property int $unit_price_etb
 * @property int $total_etb
 * @property string $phone
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'shop_product_id', 'quantity', 'unit_price_etb', 'total_etb', 'phone', 'status'])]
class ShopOrder extends Model
{
    /** @use HasFactory<ShopOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_etb' => 'integer',
            'total_etb' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ShopProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Confirm a pending order (admin acknowledged it).
     */
    public function confirm(): bool
    {
        return $this->isPending() && $this->update(['status' => 'confirmed']);
    }

    /**
     * Send a confirmed order out for delivery.
     */
    public function transit(): bool
    {
        return $this->status === 'confirmed'
            && $this->update(['status' => 'on_transit']);
    }

    /**
     * Mark an order on its way (or freshly confirmed) as delivered.
     */
    public function deliver(): bool
    {
        return in_array($this->status, ['confirmed', 'on_transit'], true)
            && $this->update(['status' => 'delivered']);
    }

    /**
     * Delivery failed — the order came back (member unreachable, refused, etc).
     */
    public function markReturned(): bool
    {
        return in_array($this->status, ['confirmed', 'on_transit'], true)
            && $this->update(['status' => 'returned']);
    }

    /**
     * Cancel an order that has not shipped yet.
     */
    public function cancel(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true)
            && $this->update(['status' => 'cancelled']);
    }

    /**
     * Re-open a cancelled or returned order back to pending.
     */
    public function reopen(): bool
    {
        return in_array($this->status, ['cancelled', 'returned'], true)
            && $this->update(['status' => 'pending']);
    }
}
