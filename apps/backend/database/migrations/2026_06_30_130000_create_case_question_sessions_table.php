<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_question_sessions', function (Blueprint $table) {
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

            $table->string('status')->default('active');
            $table->string('language', 20)->default('bn-BD');
            $table->string('mode')->default('ai_missing_questions');

            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('answered_questions')->default(0);

            $table->jsonb('case_snapshot')->nullable();
            $table->jsonb('settings')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'status']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_question_sessions');
    }
};
