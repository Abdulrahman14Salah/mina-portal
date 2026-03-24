<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->string('type', 50)->default('upload')->change();
        });
    }

    public function down(): void
    {
        // Clear rows that use types not in the original enum before reverting.
        DB::table('application_tasks')->whereNotIn('type', ['upload', 'text', 'both'])->delete();

        Schema::table('application_tasks', function (Blueprint $table) {
            $table->enum('type', ['upload', 'text', 'both'])->default('upload')->change();
        });
    }
};
