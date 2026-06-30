<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_question_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('case_question_session_id')
                ->constrained('case_question_sessions')
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('patient_visit_id')
                ->constrained('patient_visits')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('parent_message_id')->nullable();

            $table->string('role');
            $table->string('message_type')->default('question');
            $table->string('status')->default('pending');

            $table->string('question_key')->nullable();
            $table->string('category')->nullable();
            $table->string('importance')->default('normal');

            $table->longText('content');

            $table->jsonb('extracted_update')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamp('answered_at')->nullable();

            $table->timestamps();

            $table->foreign('parent_message_id')
                ->references('id')
                ->on('case_question_messages')
                ->nullOnDelete();

            $table->index(['case_question_session_id', 'created_at']);
            $table->index(['patient_visit_id', 'role']);
            $table->index(['status', 'message_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_question_messages');
    }
};
