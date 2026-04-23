<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $posPermissions = [
            'pos.open-session',
            'pos.close-session',
            'pos.sell',
            'pos.void',
            'pos.discount',
        ];

        foreach ($posPermissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (['super-admin', 'tenant-owner', 'cashier'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        Role::findByName('super-admin', 'web')->syncPermissions($posPermissions);
        Role::findByName('cashier', 'web')->syncPermissions($posPermissions);
        Role::findByName('tenant-owner', 'web')->syncPermissions($posPermissions);
    }
}
