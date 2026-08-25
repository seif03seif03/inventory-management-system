<?php

namespace App\Providers;

use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\WarehouseTransfer;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // stock_movements records its source document as reference_type +
        // reference_id — exactly the shape of a Laravel polymorphic relation,
        // but storing readable strings ('stock_in') instead of class names.
        //
        // Registering the map here is what lets StockMovement::reference()
        // resolve the correct document while the stored values stay as the
        // migration documents them. Without it, Eloquent would treat
        // 'stock_in' as a class name and fail to resolve.
        Relation::morphMap([
            StockMovement::REFERENCE_STOCK_IN  => StockIn::class,
            StockMovement::REFERENCE_STOCK_OUT => StockOut::class,
            StockMovement::REFERENCE_TRANSFER  => WarehouseTransfer::class,
        ]);
    }
}
