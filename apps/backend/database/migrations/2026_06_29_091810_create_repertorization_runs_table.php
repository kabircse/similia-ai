<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repertorization_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_visit_id')
                ->constrained('patient_visits')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('method')->default('weighted');
            $table->unsignedInteger('total_rubrics')->default(0);
            $table->unsignedInteger('essential_rubrics_count')->default(0);

            $table->jsonb('settings')->nullable();
            $table->jsonb('selected_rubrics_snapshot')->nullable();

            $table->timestamps();

            $table->index(['patient_visit_id', 'method']);
            $table->index(['doctor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repertorization_runs');
    }
};