<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_step_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_document_required')->default(false);
            $table->timestamps();

            $table->unique(['visa_type_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_templates');
    }
};
