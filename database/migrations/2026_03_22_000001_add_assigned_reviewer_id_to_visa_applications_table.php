<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->foreignId('assigned_reviewer_id')
                ->nullable()
                ->after('visa_type_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'assigned_reviewer_id');
            $table->dropColumn('assigned_reviewer_id');
        });
    }
};
