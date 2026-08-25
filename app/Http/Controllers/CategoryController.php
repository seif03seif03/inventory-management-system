<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::latest();

        // The closure GROUPS the OR conditions, producing
        //   AND (name LIKE ... OR description LIKE ...)
        // Without it the OR would escape and cancel out the status filter
        // below — a search plus a status would silently return every category.
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('active', $request->input('active') == '1');
        }

        $categories = $query->paginate(20)->withQueryString();

        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(Request $request)
    {
        Category::create($this->validatedCategory($request));

        return redirect()
            ->route('categories.index')
            ->with('success', __('Category created successfully.'));
    }

    public function show(Category $category)
    {
        return view('categories.show', compact('category'));
    }

    public function edit(Category $category)
    {
        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validatedCategory($request));

        return redirect()
            ->route('categories.index')
            ->with('success', __('Category updated successfully.'));
    }

    public function destroy(Category $category)
    {
        // products.category_id is RESTRICT as of the 2026_08_25 migration. It
        // used to CASCADE, which meant this button deleted every product in the
        // category and still reported success — silent data loss. Ask first.
        if ($category->products()->exists()) {
            return back()->with('error', __('":name" still has products and cannot be deleted. Move those products to another category first.', ['name' => $category->name]));
        }

        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', __('Category deleted successfully.'));
    }

    private function validatedCategory(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);

        $data['active'] = $request->boolean('active');

        return $data;
    }
}
