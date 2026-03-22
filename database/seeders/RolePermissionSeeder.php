<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $client = Role::firstOrCreate(['name' => 'client']);
        $reviewer = Role::firstOrCreate(['name' => 'reviewer']);

        $permissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.deactivate',
            'roles.assign',
            'dashboard.admin',
            'dashboard.client',
            'dashboard.reviewer',
            'tasks.view',
            'tasks.advance',
            'tasks.reject',
            'documents.upload',
            'documents.download',
            'documents.admin-upload',
            'documents.reviewer-upload',
            'documents.delete',
            'documents.delete-own',
            'payments.pay',
            'payments.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin->syncPermissions($permissions);
        $client->syncPermissions(['dashboard.client', 'documents.upload', 'documents.delete-own', 'payments.pay']);
        $reviewer->syncPermissions(['dashboard.reviewer', 'tasks.view', 'tasks.advance', 'tasks.reject', 'documents.download', 'documents.reviewer-upload']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
