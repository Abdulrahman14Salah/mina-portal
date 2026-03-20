<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visa_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visa_type_id')->constrained()->restrictOnDelete();
            $table->string('reference_number', 15)->unique()->nullable();
            $table->string('status', 30)->default('pending_review');
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('nationality', 100);
            $table->string('country_of_residence', 100);
            $table->string('job_title', 150);
            $table->string('employment_type', 50);
            $table->decimal('monthly_income', 10, 2);
            $table->unsignedTinyInteger('adults_count')->default(1);
            $table->unsignedTinyInteger('children_count')->default(0);
            $table->date('application_start_date');
            $table->text('notes')->nullable();
            $table->boolean('agreed_to_terms')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visa_applications');
    }
};
