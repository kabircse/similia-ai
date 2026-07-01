<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedy_relationship_findings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('remedy_relationship_run_id')
                ->constrained('remedy_relationship_runs')
                ->cascadeOnDelete();

            $table->foreignId('related_remedy_id')
                ->nullable()
                ->constrained('remedies')
                ->nullOnDelete();

            $table->string('related_remedy_code', 80)->nullable();
            $table->string('related_remedy_name')->nullable();

            $table->string('relationship_type')->default('unknown');
            $table->string('direction')->nullable();

            $table->unsignedInteger('rank')->default(1);
            $table->decimal('confidence_score', 8, 2)->default(0);

            $table->text('summary')->nullable();
            $table->text('clinical_note')->nullable();
            $table->text('caution')->nullable();

            $table->jsonb('evidence')->nullable();
            $table->jsonb('source_chunks')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['remedy_relationship_run_id', 'rank']);
            $table->index(['relationship_type']);
            $table->index(['related_remedy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedy_relationship_findings');
    }
};
