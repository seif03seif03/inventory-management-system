<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Distributor;

class DistributorController extends Controller
{
    public function index(Request $request)
    {
        $query = Distributor::latest();

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

        $distributors = $query->paginate(20)->withQueryString();
        return view('distributors.index', compact('distributors'));
    }

    public function create()
    {
        return view('distributors.create');
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

        Distributor::create($validated);

        return redirect()->route('distributors.index')->with('success', 'Distributor created successfully.');
    }

    public function show(Distributor $distributor)
    {
        return view('distributors.show', compact('distributor'));
    }

    public function edit(Distributor $distributor)
    {
        return view('distributors.edit', compact('distributor'));
    }

    public function update(Request $request, Distributor $distributor)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'active' => 'required|boolean',
        ]);

        $distributor->update($validated);

        return redirect()->route('distributors.index')->with('success', 'Distributor updated successfully.');
    }

    public function destroy(Distributor $distributor)
    {
        // stock_outs holds a RESTRICT foreign key on distributor_id, so deleting
        // a distributor named on an issue would orphan that issue and the
        // database refuses it. We ask first and explain.
        if ($distributor->hasStockHistory()) {
            return back()->with('error', "\"{$distributor->name}\" is named on stock issues and cannot be deleted. Mark it inactive instead.");
        }

        $distributor->delete();

        return redirect()->route('distributors.index')->with('success', 'Distributor deleted successfully.');
    }
}
