<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_analysis_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('patient_visit_id')
                ->constrained('patient_visits')
                ->cascadeOnDelete();

            $table->foreignId('previous_visit_id')
                ->nullable()
                ->constrained('patient_visits')
                ->nullOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('prescription_id')
                ->nullable()
                ->constrained('patient_prescriptions')
                ->nullOnDelete();

            $table->string('status')->default('completed');
            $table->string('response_level')->nullable();
            $table->decimal('progress_score', 8, 2)->default(0);

            $table->jsonb('previous_case_snapshot')->nullable();
            $table->jsonb('current_case_snapshot')->nullable();
            $table->jsonb('prescription_snapshot')->nullable();

            $table->text('analysis_summary')->nullable();
            $table->text('remedy_response_assessment')->nullable();

            $table->jsonb('improvement_points')->nullable();
            $table->jsonb('worsening_points')->nullable();
            $table->jsonb('unchanged_points')->nullable();
            $table->jsonb('new_symptoms')->nullable();
            $table->jsonb('old_symptoms_returned')->nullable();
            $table->jsonb('possible_aggravation_signs')->nullable();
            $table->jsonb('red_flags')->nullable();

            $table->jsonb('suggested_follow_up_questions')->nullable();
            $table->jsonb('doctor_review_points')->nullable();
            $table->jsonb('recommended_next_steps')->nullable();

            $table->text('safety_note')->nullable();
            $table->text('error_message')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['previous_visit_id']);
            $table->index(['response_level', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_analysis_runs');
    }
};
