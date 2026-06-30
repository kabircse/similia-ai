<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedy_suggestion_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('remedy_suggestion_run_id')
                ->constrained('remedy_suggestion_runs')
                ->cascadeOnDelete();

            $table->foreignId('remedy_id')
                ->nullable()
                ->constrained('remedies')
                ->nullOnDelete();

            $table->string('remedy_code', 80)->nullable();
            $table->string('remedy_name');
            $table->unsignedInteger('rank')->default(1);

            $table->decimal('confidence_score', 8, 2)->default(0);
            $table->decimal('repertory_score', 8, 2)->default(0);
            $table->decimal('materia_medica_score', 8, 2)->default(0);
            $table->decimal('knowledge_score', 8, 2)->default(0);

            $table->text('summary')->nullable();

            $table->jsonb('matching_points')->nullable();
            $table->jsonb('differentiating_points')->nullable();
            $table->jsonb('missing_questions')->nullable();

            $table->jsonb('evidence_matrix')->nullable();
            $table->jsonb('repertory_evidence')->nullable();
            $table->jsonb('materia_medica_evidence')->nullable();
            $table->jsonb('potency_considerations')->nullable();
            $table->jsonb('relationship_notes')->nullable();
            $table->jsonb('medical_safety_notes')->nullable();

            $table->jsonb('source_chunks')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['remedy_suggestion_run_id', 'rank']);
            $table->index(['remedy_id']);
            $table->index(['remedy_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedy_suggestion_items');
    }
};
