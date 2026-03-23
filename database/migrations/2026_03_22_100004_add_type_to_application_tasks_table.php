<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->enum('type', ['upload', 'text', 'both'])->default('upload')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
