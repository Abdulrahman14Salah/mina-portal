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
            // Restore the FK-supporting index on visa_type_id before dropping
            // the composite unique — MySQL won't drop a unique index that is
            // the sole index backing a foreign key constraint.
            $table->index('visa_type_id', 'workflow_sections_visa_type_id_index');
            $table->dropUnique('workflow_sections_visa_position_unique');
        });

        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->index('workflow_section_id', 'workflow_tasks_workflow_section_id_index');
            $table->dropUnique('workflow_tasks_section_position_unique');
        });
    }
};
