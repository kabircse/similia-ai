<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_review_runs', function (Blueprint $table) {
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
            $table->string('potency')->nullable();
            $table->string('repetition')->nullable();

            $table->string('status')->default('completed');
            $table->string('review_status')->default('needs_doctor_review');
            $table->decimal('safety_score', 8, 2)->default(0);
            $table->string('response_language', 20)->default('auto');

            $table->jsonb('case_snapshot')->nullable();
            $table->jsonb('prescription_snapshot')->nullable();
            $table->jsonb('remedy_suggestion_snapshot')->nullable();
            $table->jsonb('potency_guidance_snapshot')->nullable();
            $table->jsonb('relationship_snapshot')->nullable();
            $table->jsonb('follow_up_snapshot')->nullable();

            $table->text('review_summary')->nullable();
            $table->text('decision_guidance')->nullable();
            $table->text('risk_summary')->nullable();

            $table->jsonb('red_flags')->nullable();
            $table->jsonb('missing_information')->nullable();
            $table->jsonb('doctor_review_points')->nullable();
            $table->jsonb('recommended_actions')->nullable();

            $table->text('safety_note')->nullable();
            $table->text('error_message')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['prescription_id']);
            $table->index(['review_status', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_review_runs');
    }
};
