<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $labels = [
            'users.view' => ['View users', 'Users'],
            'users.manage' => ['Manage users', 'Users'],
            'roles.manage' => ['Manage roles', 'Roles'],
            'cars.manage' => ['Manage cars', 'Inventory'],
            'parts.manage' => ['Manage parts', 'Inventory'],
            'orders.manage' => ['Manage orders', 'Sales'],
            'customers.manage' => ['Manage customers', 'Sales'],
        ];

        $permissions = Permission::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'key' => $permission->name,
                'label' => $labels[$permission->name][0] ?? $permission->name,
                'group' => $labels[$permission->name][1] ?? 'General',
            ]);

        return response()->json($permissions);
    }
}
