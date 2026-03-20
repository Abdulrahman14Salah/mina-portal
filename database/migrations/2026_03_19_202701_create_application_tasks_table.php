<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('visa_applications')->cascadeOnDelete();
            $table->foreignId('workflow_step_template_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('reviewer_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_tasks');
    }
};
