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
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
        }

        $products = $query->get();

        // Build a stock map so Blade can show the current stock for each product
        // without running a separate query per row (which would be an N+1 problem).
        //
        // We run exactly 2 queries:
        //   - one SUM query for all IN movements, grouped by product_id
        //   - one SUM query for all OUT movements, grouped by product_id
        // Then we combine them into $productStocks[productId] = currentStock.
        $ins = StockMovement::where('type', StockMovement::TYPE_IN)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->pluck('total', 'product_id');

        $outs = StockMovement::where('type', StockMovement::TYPE_OUT)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->pluck('total', 'product_id');

        $productStocks = [];
        foreach ($products as $product) {
            $in  = $ins[$product->id]  ?? 0;
            $out = $outs[$product->id] ?? 0;
            $productStocks[$product->id] = max(0, (int)$in - (int)$out);
        }

        return view('products.index', compact('products', 'productStocks'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        return view('products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'minimum_stock' => 'required|integer|min:0',
            'active' => 'required|boolean',
        ]);

        Product::create($validated);

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function show(Product $product)
    {
        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories = Category::orderBy('name')->get();
        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'minimum_stock' => 'required|integer|min:0',
            'active' => 'required|boolean',
        ]);

        $product->update($validated);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }
}
