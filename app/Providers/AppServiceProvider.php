<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
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
        // reference_id, and activity_logs records its subject as subject_type +
        // subject_id — both polymorphic, both storing readable strings
        // ('stock_in') rather than PHP class paths.
        //
        // Registering the map here is what lets StockMovement::reference() and
        // ActivityLog::subject() resolve the right class. It also keeps both
        // tables legible and means renaming or moving a model class does not
        // invalidate existing history.
        Relation::morphMap([
            StockMovement::REFERENCE_STOCK_IN  => StockIn::class,
            StockMovement::REFERENCE_STOCK_OUT => StockOut::class,
            StockMovement::REFERENCE_TRANSFER  => WarehouseTransfer::class,

            'product'     => Product::class,
            'category'    => Category::class,
            'supplier'    => Supplier::class,
            'distributor' => Distributor::class,
            'warehouse'   => Warehouse::class,
            'user'        => User::class,
        ]);
    }
}
