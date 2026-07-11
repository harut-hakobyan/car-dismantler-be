<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->withCount('users')
            ->with('permissions')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'users' => $role->users_count,
                'status' => 'active',
            ]);

        return response()->json($roles);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $role->name) {
            $oldName = $role->name;
            $role->update(['name' => $validated['name']]);

            User::query()
                ->where('role', $oldName)
                ->update(['role' => $validated['name']]);
        }

        $role->syncPermissions($validated['permissions']);
        $role->refresh();

        return response()->json([
            'message' => 'Role updated',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'users' => $role->users()->count(),
                'status' => 'active',
            ],
        ]);
    }
}
