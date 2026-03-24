<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->string('type', 50)->default('upload')->change();
        });
    }

    public function down(): void
    {
        // Delete all rows before reverting to the original enum, because
        // seeded data uses values ('question', 'payment', 'info') that don't
        // fit in the old enum('upload', 'text', 'both').
        DB::table('workflow_tasks')->delete();

        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->enum('type', ['upload', 'text', 'both'])->default('upload')->change();
        });
    }
};
