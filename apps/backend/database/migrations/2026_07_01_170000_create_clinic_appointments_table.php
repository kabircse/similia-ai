<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_appointments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('patient_visit_id')
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

            $table->string('appointment_type')->default('follow_up');
            $table->string('source')->default('manual');
            $table->string('status')->default('scheduled');

            $table->timestamp('scheduled_start_at');
            $table->timestamp('scheduled_end_at')->nullable();
            $table->string('timezone', 80)->default('Asia/Dhaka');

            $table->string('title')->nullable();
            $table->text('reason')->nullable();
            $table->text('doctor_note')->nullable();
            $table->text('patient_instruction')->nullable();

            $table->string('contact_method')->default('phone');
            $table->boolean('send_reminders')->default(true);
            $table->jsonb('reminder_minutes_before')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('no_show_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'scheduled_start_at']);
            $table->index(['patient_id', 'scheduled_start_at']);
            $table->index(['patient_visit_id']);
            $table->index(['status', 'scheduled_start_at']);
            $table->index(['appointment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_appointments');
    }
};
