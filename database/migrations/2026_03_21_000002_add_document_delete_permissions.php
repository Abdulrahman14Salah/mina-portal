<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $deleteOwn = Permission::firstOrCreate(['name' => 'documents.delete-own', 'guard_name' => 'web']);
        $delete = Permission::firstOrCreate(['name' => 'documents.delete',     'guard_name' => 'web']);

        Role::findByName('client', 'web')->givePermissionTo($deleteOwn);
        Role::findByName('admin', 'web')->givePermissionTo($delete);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', 'documents.delete-own')->delete();
        Permission::where('name', 'documents.delete')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
