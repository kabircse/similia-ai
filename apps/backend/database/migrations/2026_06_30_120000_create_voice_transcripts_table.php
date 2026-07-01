<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_transcripts', function (Blueprint $table) {
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

            $table->string('language', 20)->default('bn-BD');
            $table->string('source')->default('browser_speech_recognition');
            $table->string('status')->default('completed');

            $table->longText('transcript_text');
            $table->jsonb('segments')->nullable();

            $table->boolean('merged_to_case_text')->default(false);
            $table->string('merge_mode')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_transcripts');
    }
};
