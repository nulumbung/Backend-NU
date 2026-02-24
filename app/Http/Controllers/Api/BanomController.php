<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banom;
use Illuminate\Support\Str;

class BanomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Banom::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|string',
            'short_desc' => 'nullable|string',
            'long_desc' => 'nullable|string',
            'history' => 'nullable|string',
            'vision' => 'nullable|string',
            'mission' => 'nullable|array',
            'management' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $banom = Banom::create($validated);

        return response()->json($banom, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (is_numeric($id)) {
            $banom = Banom::find($id);
        } else {
            $banom = Banom::where('slug', $id)->first();
        }

        if (!$banom) {
            return response()->json(['message' => 'Banom not found'], 404);
        }

        return $banom;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $banom = Banom::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'logo' => 'nullable|string',
            'short_desc' => 'nullable|string',
            'long_desc' => 'nullable|string',
            'history' => 'nullable|string',
            'vision' => 'nullable|string',
            'mission' => 'nullable|array',
            'management' => 'nullable|array',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $banom->update($validated);

        return response()->json($banom);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $banom = Banom::findOrFail($id);
        $banom->delete();
        return response()->json(['message' => 'Banom deleted successfully']);
    }
}
