<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->enum('type', ['upload', 'text', 'both'])->default('upload')->change();
        });
    }
};
