<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's order, from the moment it is placed until it is collected.
 *
 * @property int $id
 * @property int $store_id
 * @property OrderStatus $status
 * @property int $queue_number
 */
class Order extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'queue_date',
        'queue_number',
        'token',
        'customer_name',
        'note',
        'status',
        'subtotal',
        'total',
        'item_count',
        'sale_id',
        'cashier_id',
        'placed_at',
        'paid_at',
        'ready_at',
        'collected_at',
        'reject_reason',
        'rejected_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'queue_date' => 'date',
            'queue_number' => 'integer',
            'item_count' => 'integer',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'ready_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    // ─── Scopes ──────────────────────────────────

    /**
     * Still needs someone to do something about it.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', OrderStatus::openValues());
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeAwaitingPayment(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::Placed->value);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('queue_date', today());
    }

    // ─── Helpers ─────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * The next queue number for a store today.
     *
     * Two customers submitting at the same instant can read the same number;
     * the unique index on (store_id, queue_date, queue_number) is what
     * actually prevents a duplicate, and OrderService retries when it fires.
     */
    public static function nextQueueNumber(int $storeId): int
    {
        $highest = static::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->whereDate('queue_date', today())
            ->max('queue_number');

        return ((int) $highest) + 1;
    }
}
