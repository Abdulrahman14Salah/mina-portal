<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('application_tasks')
            ->where('status', 'completed')
            ->update(['status' => 'approved']);
    }

    public function down(): void
    {
        DB::table('application_tasks')
            ->where('status', 'approved')
            ->update(['status' => 'completed']);
    }
};
