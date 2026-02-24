<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\History;

class HistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return History::orderBy('order', 'asc')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|string',
            'title' => 'required|string',
            'description' => 'required|string',
            'image' => 'nullable|string',
            'order' => 'integer'
        ]);

        $history = History::create($validated);

        return response()->json($history, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return History::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $history = History::findOrFail($id);

        $validated = $request->validate([
            'year' => 'sometimes|string',
            'title' => 'sometimes|string',
            'description' => 'sometimes|string',
            'image' => 'nullable|string',
            'order' => 'integer'
        ]);

        $history->update($validated);

        return response()->json($history);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $history = History::findOrFail($id);
        $history->delete();
        return response()->json(['message' => 'History deleted successfully']);
    }
}
