<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('potency_guidance_runs', function (Blueprint $table) {
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

            $table->foreignId('prescription_id')
                ->nullable()
                ->constrained('patient_prescriptions')
                ->nullOnDelete();

            $table->foreignId('remedy_id')
                ->nullable()
                ->constrained('remedies')
                ->nullOnDelete();

            $table->string('remedy_code', 80)->nullable();
            $table->string('remedy_name')->nullable();

            $table->string('case_phase')->nullable();
            $table->string('status')->default('completed');

            $table->jsonb('case_snapshot')->nullable();
            $table->jsonb('prescription_snapshot')->nullable();
            $table->jsonb('follow_up_snapshot')->nullable();
            $table->jsonb('retrieved_sources')->nullable();
            $table->jsonb('settings')->nullable();

            $table->string('vitality_level')->nullable();
            $table->string('sensitivity_level')->nullable();
            $table->string('pathology_depth')->nullable();

            $table->text('guidance_summary')->nullable();
            $table->text('repetition_guidance')->nullable();
            $table->text('wait_and_watch_guidance')->nullable();
            $table->text('aggravation_guidance')->nullable();

            $table->jsonb('cautions')->nullable();
            $table->jsonb('follow_up_questions')->nullable();
            $table->jsonb('doctor_review_points')->nullable();

            $table->text('safety_note')->nullable();
            $table->text('error_message')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['prescription_id']);
            $table->index(['remedy_id']);
            $table->index(['case_phase', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('potency_guidance_runs');
    }
};
