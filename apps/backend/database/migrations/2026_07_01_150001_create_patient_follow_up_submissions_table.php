<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_follow_up_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_portal_invitation_id')
                ->constrained('patient_portal_invitations')
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('source_patient_visit_id')
                ->nullable()
                ->constrained('patient_visits')
                ->nullOnDelete();

            $table->foreignId('converted_patient_visit_id')
                ->nullable()
                ->constrained('patient_visits')
                ->nullOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('status')->default('new');

            $table->string('response_language', 20)->default('auto');
            $table->string('resolved_language', 20)->nullable();

            $table->string('overall_change')->nullable();
            $table->boolean('medicine_taken')->nullable();

            $table->longText('main_changes')->nullable();
            $table->longText('current_symptoms')->nullable();
            $table->longText('new_symptoms')->nullable();
            $table->longText('aggravation_notes')->nullable();
            $table->longText('other_medicines')->nullable();
            $table->longText('general_notes')->nullable();
            $table->longText('red_flag_notes')->nullable();
            $table->longText('patient_questions')->nullable();

            $table->string('general_energy')->nullable();
            $table->string('sleep')->nullable();
            $table->string('appetite')->nullable();
            $table->string('mood')->nullable();
            $table->string('preferred_contact_time')->nullable();

            $table->jsonb('answers')->nullable();
            $table->jsonb('detected_red_flags')->nullable();

            $table->text('doctor_note')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('converted_at')->nullable();

            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
            $table->index(['source_patient_visit_id', 'created_at']);
            $table->index(['converted_patient_visit_id']);
            $table->index(['doctor_id', 'status']);
            $table->index(['overall_change']);
            $table->index(['submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_follow_up_submissions');
    }
};
