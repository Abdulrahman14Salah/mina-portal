<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_task_id')->constrained('application_tasks')->cascadeOnDelete();
            $table->foreignId('task_question_id')->constrained('task_questions')->cascadeOnDelete();
            $table->text('answer');
            $table->timestamps();

            $table->unique(['application_task_id', 'task_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_answers');
    }
};
