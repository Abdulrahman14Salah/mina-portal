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
        Schema::create('payment_stage_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_type_id')->constrained('visa_types')->onDelete('cascade');
            $table->unsignedTinyInteger('stage');
            $table->string('name', 100);
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->timestamps();

            $table->unique(['visa_type_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_stage_configs');
    }
};
