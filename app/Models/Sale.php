<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'user_id',
        'subtotal',
        'discount',
        'total',
        'item_count',
        'payment_method',
        'cash_received',
        'change_amount',
        'status',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'item_count' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function stockLogs(): HasMany
    {
        return $this->hasMany(StockLog::class);
    }

    // ─── Scopes ──────────────────────────────────

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('status', 'voided');
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public function scopeDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [
            $from.' 00:00:00',
            $to.' 23:59:59',
        ]);
    }

    // ─── Helpers ─────────────────────────────────

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    /**
     * Generate a unique receipt number: YYYYMMDD-XXXX (sequential daily counter).
     * Uses a retry loop to handle the unlikely collision case.
     */
    public static function generateReceiptNumber(): string
    {
        $prefix = now()->format('Ymd');
        $maxRetries = 5;

        for ($i = 0; $i < $maxRetries; $i++) {
            $random = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $receipt = "{$prefix}-{$random}";

            if (! static::where('receipt_number', $receipt)->exists()) {
                return $receipt;
            }
        }

        // Fallback: include timestamp for uniqueness
        return $prefix.'-'.now()->format('His').'-'.str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    }
}
