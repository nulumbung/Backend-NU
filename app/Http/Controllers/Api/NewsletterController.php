<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Newsletter::orderBy('created_at', 'desc')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:newsletters,email',
        ]);

        $newsletter = Newsletter::create([
            'email' => $validated['email'],
            'is_active' => true,
            'subscribed_at' => now(),
        ]);

        return response()->json($newsletter, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $newsletter = Newsletter::findOrFail($id);
        return response()->json($newsletter);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $newsletter = Newsletter::findOrFail($id);

        $validated = $request->validate([
            'email' => 'sometimes|email|unique:newsletters,email,' . $newsletter->id,
            'is_active' => 'sometimes|boolean',
        ]);

        $newsletter->update($validated);

        return response()->json($newsletter);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $newsletter = Newsletter::findOrFail($id);
        $newsletter->delete();
        return response()->json(['message' => 'Newsletter subscriber deleted successfully']);
    }

    /**
     * Public method to subscribe (no auth required)
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:newsletters,email',
        ]);

        $newsletter = Newsletter::create([
            'email' => $validated['email'],
            'is_active' => true,
            'subscribed_at' => now(),
        ]);

        return response()->json(['message' => 'Successfully subscribed to newsletter'], 201);
    }
}
