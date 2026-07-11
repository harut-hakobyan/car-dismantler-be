<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->delete();
        Permission::query()->delete();

        $permissions = [
            'users.view' => ['View users', 'Users'],
            'users.manage' => ['Manage users', 'Users'],
            'roles.manage' => ['Manage roles', 'Roles'],
            'cars.manage' => ['Manage cars', 'Inventory'],
            'parts.manage' => ['Manage parts', 'Inventory'],
            'orders.manage' => ['Manage orders', 'Sales'],
            'customers.manage' => ['Manage customers', 'Sales'],
        ];

        foreach ($permissions as $name => [$label, $group]) {
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $owner = Role::query()->firstOrCreate([
            'name' => 'Owner',
            'guard_name' => 'web',
        ]);
        $manager = Role::query()->firstOrCreate([
            'name' => 'Manager',
            'guard_name' => 'web',
        ]);
        $sales = Role::query()->firstOrCreate([
            'name' => 'Sales',
            'guard_name' => 'web',
        ]);

        $owner->syncPermissions(array_keys($permissions));
        $manager->syncPermissions([
            'cars.manage',
            'parts.manage',
            'orders.manage',
            'customers.manage',
        ]);
        $sales->syncPermissions([
            'orders.manage',
            'customers.manage',
        ]);
    }
}
