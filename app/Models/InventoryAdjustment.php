<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'warehouse_id',
        'reference_number',
        'adjustment_date',
        'reason',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
    ];

    public const STATUS_COMPLETED = 'completed';

    /**
     * Why the count changed. Kept as a fixed list rather than free text so the
     * reasons can be reported on — "how much did we lose to damage this
     * quarter" is unanswerable if every user types their own wording.
     */
    public const REASON_DAMAGE     = 'damage';
    public const REASON_LOSS       = 'loss';
    public const REASON_THEFT      = 'theft';
    public const REASON_EXPIRY     = 'expiry';
    public const REASON_RECOUNT    = 'recount';
    public const REASON_CORRECTION = 'correction';

    public const REASONS = [
        self::REASON_DAMAGE,
        self::REASON_LOSS,
        self::REASON_THEFT,
        self::REASON_EXPIRY,
        self::REASON_RECOUNT,
        self::REASON_CORRECTION,
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(InventoryAdjustmentItem::class);
    }

    /**
     * Who made the adjustment. Null if their account was later deleted — the
     * document itself is never removed.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Net effect on stock across every line: increases minus decreases.
     * Calculated, never stored — the same rule the ledger uses.
     */
    public function netQuantity(): int
    {
        return $this->items->reduce(
            fn (int $total, $item): int => $total + $item->signedQuantity(),
            0,
        );
    }
}
