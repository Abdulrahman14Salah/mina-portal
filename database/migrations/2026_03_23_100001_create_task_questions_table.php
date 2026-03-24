<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_task_id')->constrained('workflow_tasks')->cascadeOnDelete();
            $table->string('prompt', 500);
            $table->boolean('required')->default(true);
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_questions');
    }
};
