<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Distributor;

class DistributorController extends Controller
{
    public function index(Request $request)
    {
        $query = Distributor::latest();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
        }

        $distributors = $query->get();
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
        $distributor->delete();

        return redirect()->route('distributors.index')->with('success', 'Distributor deleted successfully.');
    }
}
