<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();

            $table->foreignId('patient_visit_id')
                ->nullable()
                ->constrained('patient_visits')
                ->nullOnDelete();

            $table->string('category', 80);
            $table->string('action', 120);

            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();

            $table->jsonb('metadata')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();

            $table->string('ip_address', 80)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['category', 'action']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
