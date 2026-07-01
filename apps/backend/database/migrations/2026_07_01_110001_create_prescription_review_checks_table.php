<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_review_checks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prescription_review_run_id')
                ->constrained('prescription_review_runs')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('check_key');
            $table->string('category')->default('general');
            $table->string('severity')->default('normal');
            $table->string('status')->default('pending');

            $table->boolean('is_required')->default(true);
            $table->boolean('is_blocking')->default(false);

            $table->string('title');
            $table->text('description')->nullable();
            $table->text('ai_assessment')->nullable();
            $table->text('doctor_note')->nullable();

            $table->timestamp('doctor_confirmed_at')->nullable();

            $table->jsonb('evidence')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique(['prescription_review_run_id', 'check_key']);
            $table->index(['prescription_review_run_id', 'status']);
            $table->index(['category', 'severity']);
            $table->index(['is_required', 'is_blocking']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_review_checks');
    }
};
