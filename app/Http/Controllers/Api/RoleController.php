<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * List all roles with permissions count and users count.
     */
    public function index()
    {
        $roles = Role::withCount('permissions')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($role) {
                $role->users_count = User::where('role', $role->name)->count();
                return $role;
            });

        return response()->json($roles);
    }

    /**
     * Show role detail with permissions.
     */
    public function show(string $id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $role->users_count = User::where('role', $role->name)->count();

        return response()->json($role);
    }

    /**
     * Create a new role with permissions.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles|regex:/^[a-z0-9_-]+$/',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        if (!empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        $role->load('permissions');
        $role->permissions_count = $role->permissions->count();
        $role->users_count = 0;

        return response()->json($role, 201);
    }

    /**
     * Update a role and its permissions.
     */
    public function update(Request $request, string $id)
    {
        $role = Role::findOrFail($id);

        $rules = [
            'display_name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ];

        // Only allow name change for non-system roles
        if (!$role->is_system) {
            $rules['name'] = ['sometimes', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', Rule::unique('roles')->ignore($role->id)];
        }

        $validated = $request->validate($rules);

        // Don't allow changing the name of system roles
        if ($role->is_system) {
            unset($validated['name']);
        }

        $role->update(array_intersect_key($validated, array_flip(['name', 'display_name', 'description'])));

        if (array_key_exists('permissions', $validated)) {
            $role->permissions()->sync($validated['permissions']);
        }

        $role->load('permissions');
        $role->permissions_count = $role->permissions->count();
        $role->users_count = User::where('role', $role->name)->count();

        return response()->json($role);
    }

    /**
     * Delete a role (only non-system roles).
     */
    public function destroy(string $id)
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['message' => 'Tidak dapat menghapus role bawaan sistem.'], 403);
        }

        $usersCount = User::where('role', $role->name)->count();
        if ($usersCount > 0) {
            return response()->json(['message' => "Tidak dapat menghapus role yang masih digunakan oleh {$usersCount} user."], 422);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json(['message' => 'Role berhasil dihapus.']);
    }

    /**
     * List all permissions grouped.
     */
    public function permissions()
    {
        $permissions = Permission::orderBy('group')->orderBy('display_name')->get();

        $grouped = $permissions->groupBy('group')->map(function ($group, $key) {
            return [
                'group' => $key,
                'permissions' => $group->values(),
            ];
        })->values();

        return response()->json($grouped);
    }
}
