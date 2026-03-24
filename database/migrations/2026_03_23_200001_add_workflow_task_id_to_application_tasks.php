<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->foreignId('workflow_task_id')
                ->nullable()
                ->after('workflow_step_template_id')
                ->constrained('workflow_tasks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_task_id');
        });
    }
};
