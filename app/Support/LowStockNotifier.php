<?php

namespace App\Support;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Works out the low-stock alerts a given user should see in the navbar bell.
 *
 * There is deliberately no notifications table and no notifications CRUD:
 * a low-stock alert is not an event to be stored and marked read, it is a
 * standing condition derived from the ledger. Storing it would immediately
 * risk disagreeing with the stock it describes.
 *
 * Recipients are decided ONLY by the receive_notifications permission plus a
 * usable phone number — never by role. See User::scopeNotifiable().
 */
class LowStockNotifier
{
    /**
     * Alerts for this user, or an empty collection when they are not a
     * recipient. The permission check happens here rather than in the view so
     * a caller cannot accidentally bypass it.
     */
    public static function for(?User $user, int $limit = 10)
    {
        if (! $user || ! $user->canReceiveNotifications()) {
            return collect();
        }

        return self::query()
            ->orderByRaw('(minimum_stock - current_stock) desc')
            ->limit($limit)
            ->get();
    }

    public static function countFor(?User $user): int
    {
        if (! $user || ! $user->canReceiveNotifications()) {
            return 0;
        }

        return self::query()->count();
    }

    /**
     * Every user who should be alerted, for a future SMS or WhatsApp channel to
     * iterate. Each row is guaranteed to carry a phone number, so a sender can
     * rely on $user->phone being present.
     *
     * Nothing is sent anywhere today — no provider is configured, and inventing
     * a fake one would be worse than leaving the seam honest and empty.
     */
    public static function recipients()
    {
        return User::notifiable()->get();
    }

    /**
     * The same low-stock rule the dashboard and the low-stock report use, so
     * the bell can never disagree with them: reuse currentStockRows() rather
     * than writing a third stock calculation.
     */
    private static function query()
    {
        return DB::query()
            ->fromSub(StockMovement::currentStockRows(), 'stock_rows')
            ->where('product_active', true)
            ->where('minimum_stock', '>', 0)
            ->whereColumn('current_stock', '<=', 'minimum_stock');
    }
}
