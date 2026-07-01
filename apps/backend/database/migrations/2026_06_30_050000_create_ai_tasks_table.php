<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
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

            $table->string('type');
            $table->string('status')->default('queued');

            $table->string('title');
            $table->text('message')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);

            $table->jsonb('payload')->nullable();
            $table->jsonb('result')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tasks');
    }
};
