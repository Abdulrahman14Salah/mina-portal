<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_sections', function (Blueprint $table) {
            $table->unique(['visa_type_id', 'position'], 'workflow_sections_visa_position_unique');
        });

        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->unique(['workflow_section_id', 'position'], 'workflow_tasks_section_position_unique');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_sections', function (Blueprint $table) {
            $table->dropUnique('workflow_sections_visa_position_unique');
        });

        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->dropUnique('workflow_tasks_section_position_unique');
        });
    }
};
