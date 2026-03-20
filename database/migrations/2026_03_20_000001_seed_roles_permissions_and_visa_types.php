<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Roles
        $admin    = Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
        $client   = Role::firstOrCreate(['name' => 'client',   'guard_name' => 'web']);
        $reviewer = Role::firstOrCreate(['name' => 'reviewer', 'guard_name' => 'web']);

        // Permissions
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
            'payments.pay',
            'payments.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin->syncPermissions($permissions);
        $client->syncPermissions(['dashboard.client', 'documents.upload', 'payments.pay']);
        $reviewer->syncPermissions(['dashboard.reviewer', 'tasks.view', 'tasks.advance', 'tasks.reject', 'documents.download', 'documents.reviewer-upload']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Roles and permissions are dropped automatically when the spatie tables are dropped
    }
};
