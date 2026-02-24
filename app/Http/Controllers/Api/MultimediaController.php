<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Multimedia;
use Illuminate\Support\Str;

class MultimediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Multimedia::latest('date')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:video,photo',
            'thumbnail' => 'nullable|string',
            'url' => 'nullable|string',
            'gallery' => 'nullable|array',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'author' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['title']) . '-' . time();
        
        // Auto-set author if not provided and user is authenticated
        if (empty($validated['author']) && $request->user()) {
            $validated['author'] = $request->user()->name;
        }

        $multimedia = Multimedia::create($validated);

        return response()->json($multimedia, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (is_numeric($id)) {
            $multimedia = Multimedia::find($id);
        } else {
            $multimedia = Multimedia::where('slug', $id)->first();
        }

        if (!$multimedia) {
            return response()->json(['message' => 'Multimedia not found'], 404);
        }
        
        // Increment views
        $multimedia->increment('views');

        return $multimedia;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $multimedia = Multimedia::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:video,photo',
            'thumbnail' => 'nullable|string',
            'url' => 'nullable|string',
            'gallery' => 'nullable|array',
            'description' => 'nullable|string',
            'date' => 'sometimes|date',
            'author' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']) . '-' . $multimedia->id;
        }

        $multimedia->update($validated);

        return response()->json($multimedia);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $multimedia = Multimedia::findOrFail($id);
        $multimedia->delete();
        return response()->json(['message' => 'Multimedia deleted successfully']);
    }
}
