<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Supplier;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::latest();

        // Closure-grouped so the ORs stay contained and don't cancel out the
        // status filter: AND (name LIKE ... OR email LIKE ... OR phone LIKE ...)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('active', $request->input('active') == '1');
        }

        $suppliers = $query->paginate(20)->withQueryString();
        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'active' => 'required|boolean',
        ]);

        Supplier::create($validated);

        return redirect()->route('suppliers.index')->with('success', __('Supplier created successfully.'));
    }

    public function show(Supplier $supplier)
    {
        // The page used to be a static mockup showing one hardcoded supplier's
        // details regardless of which record was opened. These are the real
        // receipts for THIS supplier.
        $receipts = $supplier->stockIns()
            ->with('warehouse')
            ->withSum('items', 'quantity')
            ->latest('receipt_date')
            ->paginate(10);

        return view('suppliers.show', compact('supplier', 'receipts'));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'active' => 'required|boolean',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', __('Supplier updated successfully.'));
    }

    public function destroy(Supplier $supplier)
    {
        // stock_ins holds a RESTRICT foreign key on supplier_id, so deleting a
        // supplier named on a receipt would orphan that receipt and the
        // database refuses it. We ask first and explain.
        if ($supplier->hasStockHistory()) {
            return back()->with('error', __('":name" is named on stock receipts and cannot be deleted. Mark it inactive instead.', ['name' => $supplier->name]));
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('Supplier deleted successfully.'));
    }
}
