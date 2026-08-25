<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $query = Warehouse::latest();

        // Closure-grouped so the OR stays contained and doesn't cancel out the
        // status filter: AND (name LIKE ... OR location LIKE ...)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('active', $request->input('active') == '1');
        }

        $warehouses = $query->paginate(20)->withQueryString();

        // The Products and Stock Quantity columns used to be hardcoded "0".
        //
        // We reuse currentStockRows() rather than inventing a second stock
        // calculation — it already applies the one rule (SUM(IN) - SUM(OUT)
        // per product per warehouse). One query, then a fold in PHP:
        //   products = distinct products actually held here (stock > 0)
        //   quantity = total units held here
        $warehouseStock = [];

        foreach (StockMovement::currentStockRows()->get() as $row) {
            $stock = (int) $row->current_stock;

            if ($stock <= 0) {
                continue;
            }

            $warehouseStock[$row->warehouse_id]['products'] = ($warehouseStock[$row->warehouse_id]['products'] ?? 0) + 1;
            $warehouseStock[$row->warehouse_id]['quantity'] = ($warehouseStock[$row->warehouse_id]['quantity'] ?? 0) + $stock;
        }

        return view('warehouses.index', compact('warehouses', 'warehouseStock'));
    }

    public function create()
    {
        return view('warehouses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'active' => 'required|boolean',
        ]);

        Warehouse::create($validated);

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Warehouse created successfully.');
    }

    public function show(Warehouse $warehouse)
    {
        return view('warehouses.show', compact('warehouse'));
    }

    public function edit(Warehouse $warehouse)
    {
        return view('warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'active' => 'required|boolean',
        ]);

        $warehouse->update($validated);

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse)
    {
        // Receipts, issues, transfers and the movement ledger all hold a
        // RESTRICT foreign key on this warehouse, so the database will refuse
        // this delete once anything has moved through it. We ask first and
        // explain, rather than surfacing a raw constraint violation.
        if ($warehouse->hasStockHistory()) {
            return back()->with('error', "\"{$warehouse->name}\" has stock history and cannot be deleted. Mark it inactive instead.");
        }

        $warehouse->delete();

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Warehouse deleted successfully.');
    }
}
