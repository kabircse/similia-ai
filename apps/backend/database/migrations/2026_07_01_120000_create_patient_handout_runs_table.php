<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_handout_runs', function (Blueprint $table) {
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

            $table->foreignId('prescription_review_run_id')
                ->nullable()
                ->constrained('prescription_review_runs')
                ->nullOnDelete();

            $table->string('status')->default('draft');
            $table->string('handout_type')->default('prescription');

            $table->string('response_language', 20)->default('auto');
            $table->string('resolved_language', 20)->nullable();

            $table->string('title')->nullable();

            $table->text('patient_summary')->nullable();
            $table->text('medicine_instruction')->nullable();
            $table->text('diet_lifestyle_instruction')->nullable();
            $table->text('follow_up_instruction')->nullable();
            $table->text('warning_instruction')->nullable();

            $table->jsonb('case_snapshot')->nullable();
            $table->jsonb('prescription_snapshot')->nullable();
            $table->jsonb('clinic_snapshot')->nullable();
            $table->jsonb('review_snapshot')->nullable();

            $table->jsonb('warning_signs')->nullable();
            $table->jsonb('do_and_dont')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->text('footer_note')->nullable();
            $table->text('safety_note')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('printed_at')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['prescription_id']);
            $table->index(['status', 'handout_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_handout_runs');
    }
};
