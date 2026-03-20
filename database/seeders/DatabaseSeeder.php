<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(VisaTypeSeeder::class);
        $this->call(WorkflowStepTemplateSeeder::class);
        $this->call(PaymentStageConfigSeeder::class);
    }
}
