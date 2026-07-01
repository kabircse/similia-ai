<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_appointment_reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_appointment_id')
                ->constrained('clinic_appointments')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->string('reminder_type')->default('doctor_task');
            $table->string('channel')->default('in_app');
            $table->string('status')->default('pending');
            $table->unsignedInteger('minutes_before')->default(1440);
            $table->timestamp('due_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('title');
            $table->text('message')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['clinic_appointment_id', 'reminder_type', 'channel', 'minutes_before'],
                'appointment_reminder_unique'
            );
            $table->index(['doctor_id', 'status', 'due_at']);
            $table->index(['patient_id', 'due_at']);
            $table->index(['clinic_appointment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_appointment_reminders');
    }
};
