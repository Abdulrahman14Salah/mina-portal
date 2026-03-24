<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->foreignId('workflow_step_template_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->foreignId('workflow_step_template_id')
                ->nullable(false)
                ->change();
        });
    }
};
