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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('visa_applications')->onDelete('cascade');
            $table->unsignedTinyInteger('stage');
            $table->string('name', 100);
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->enum('status', ['pending', 'due', 'paid', 'failed'])->default('pending');
            $table->string('stripe_session_id', 255)->nullable();
            $table->string('stripe_payment_intent_id', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
