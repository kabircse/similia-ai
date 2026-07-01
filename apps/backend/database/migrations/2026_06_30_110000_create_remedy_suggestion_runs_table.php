<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedy_suggestion_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('patient_visit_id')
                ->constrained('patient_visits')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('repertorization_run_id')
                ->nullable()
                ->constrained('repertorization_runs')
                ->nullOnDelete();

            $table->string('method')->nullable();
            $table->string('status')->default('completed');
            $table->unsignedTinyInteger('limit')->default(3);

            $table->jsonb('case_snapshot')->nullable();
            $table->jsonb('selected_rubrics_snapshot')->nullable();
            $table->jsonb('retrieved_sources')->nullable();
            $table->jsonb('settings')->nullable();

            $table->text('safety_note')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['method', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedy_suggestion_runs');
    }
};
