<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->filled('active')) {
            $query->where('active', $request->input('active') == '1');
        }

        $products = $query->paginate(20)->withQueryString();

        // Build a stock map so Blade can show the current stock for each product
        // without running a separate query per row (which would be an N+1 problem).
        //
        // We run exactly 2 queries, both scoped to the products on this page:
        //   - one SUM query for their IN movements, grouped by product_id
        //   - one SUM query for their OUT movements, grouped by product_id
        // Then we combine them into $productStocks[productId] = currentStock.
        $productIds = $products->pluck('id');

        $ins = StockMovement::whereIn('product_id', $productIds)
            ->where('type', StockMovement::TYPE_IN)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->pluck('total', 'product_id');

        $outs = StockMovement::whereIn('product_id', $productIds)
            ->where('type', StockMovement::TYPE_OUT)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->pluck('total', 'product_id');

        $productStocks = [];
        foreach ($products as $product) {
            $in  = $ins[$product->id]  ?? 0;
            $out = $outs[$product->id] ?? 0;
            $productStocks[$product->id] = max(0, (int)$in - (int)$out);
        }

        $categories = Category::select('id', 'name')->orderBy('name')->get();

        return view('products.index', compact('products', 'productStocks', 'categories'));
    }

    public function create()
    {
        $categories = Category::select('id', 'name')->orderBy('name')->get();
        return view('products.create', compact('categories'));
    }

    /**
     * Match the SKU to the form the model will actually store.
     *
     * Product::booted() upper-cases the SKU on save, so "iph-15" and "IPH-15"
     * are one and the same key in the table. Validating the raw input would let
     * the second spelling past a case-sensitive unique rule and then hit the
     * unique index as a 500. Normalising first means the rule compares the value
     * that is really going to be written.
     */
    private function normalizeSku(Request $request): void
    {
        if ($request->has('sku')) {
            $request->merge(['sku' => strtoupper((string) $request->input('sku'))]);
        }
    }

    public function store(Request $request)
    {
        $this->normalizeSku($request);

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'minimum_stock' => 'required|integer|min:0',
            'active' => 'required|boolean',
        ]);

        Product::create($validated);

        return redirect()->route('products.index')->with('success', __('Product created successfully.'));
    }

    public function show(Product $product)
    {
        // Total stock across all warehouses, using the same IN - OUT rule
        // as everywhere else (no separate calculation is introduced here).
        $totalIn = StockMovement::where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_IN)
            ->sum('quantity');

        $totalOut = StockMovement::where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_OUT)
            ->sum('quantity');

        $currentStock = max(0, (int) $totalIn - (int) $totalOut);

        $movements = StockMovement::where('product_id', $product->id)
            ->with('warehouse')
            ->latest()
            ->take(20)
            ->get();

        return view('products.show', compact('product', 'currentStock', 'movements'));
    }

    public function edit(Product $product)
    {
        $categories = Category::select('id', 'name')->orderBy('name')->get();
        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $this->normalizeSku($request);

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($product->id)],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'minimum_stock' => 'required|integer|min:0',
            'active' => 'required|boolean',
        ]);

        $product->update($validated);

        return redirect()->route('products.index')->with('success', __('Product updated successfully.'));
    }

    public function destroy(Product $product)
    {
        // The ledger and every stock document hold a RESTRICT foreign key on
        // product_id, so the database will refuse this delete outright once the
        // product has been received, issued or transferred. We ask first and
        // explain, instead of letting that surface as an unhandled constraint
        // violation. Deactivating is the correct way to retire a product whose
        // history must stay readable.
        if ($product->hasStockHistory()) {
            return back()->with('error', __('":name" appears on stock documents and cannot be deleted. Mark it inactive instead.', ['name' => $product->name]));
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', __('Product deleted successfully.'));
    }
}
