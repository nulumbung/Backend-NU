<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::withCount('loginDevices')
            ->orderBy('created_at', 'desc')
            ->get();

        if (auth()->check() && auth()->user()->role === 'superadmin') {
            $users->makeVisible('raw_password');
        }

        return $users;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:superadmin,admin,editor,redaksi,user',
            'avatar' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'raw_password' => $validated['password'],
            'role' => $validated['role'],
            'avatar' => $validated['avatar'] ?? null,
            'auth_provider' => 'email',
        ]);

        if (auth()->check() && auth()->user()->role === 'superadmin') {
            $user->makeVisible('raw_password');
        }

        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('loginDevices')->findOrFail($id);
        
        if (auth()->check() && auth()->user()->role === 'superadmin') {
            $user->makeVisible('raw_password');
        }
        
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|in:superadmin,admin,editor,redaksi,user',
            'avatar' => 'nullable|string',
        ]);

        if (isset($validated['password'])) {
            $validated['raw_password'] = $validated['password'];
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        if (auth()->check() && auth()->user()->role === 'superadmin') {
            $user->makeVisible('raw_password');
        }

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting self (optional, but good practice)
        if (auth()->id() == $user->id) {
             return response()->json(['message' => 'Cannot delete your own account'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
