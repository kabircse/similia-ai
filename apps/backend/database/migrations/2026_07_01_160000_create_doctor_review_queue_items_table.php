<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_review_queue_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();

            $table->foreignId('patient_visit_id')
                ->nullable()
                ->constrained('patient_visits')
                ->nullOnDelete();

            $table->foreignId('patient_follow_up_submission_id')
                ->nullable()
                ->constrained('patient_follow_up_submissions')
                ->nullOnDelete();

            $table->string('category')->default('portal_submission');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');

            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('doctor_note')->nullable();
            $table->string('action_url')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('in_review_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();

            $table->jsonb('red_flags')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['category', 'patient_follow_up_submission_id'],
                'review_queue_submission_unique'
            );
            $table->index(['doctor_id', 'status']);
            $table->index(['doctor_id', 'priority', 'status']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['category', 'status']);
            $table->index(['submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_review_queue_items');
    }
};
