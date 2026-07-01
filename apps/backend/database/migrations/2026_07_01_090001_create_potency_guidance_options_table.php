<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('potency_guidance_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('potency_guidance_run_id')
                ->constrained('potency_guidance_runs')
                ->cascadeOnDelete();

            $table->string('potency_range');
            $table->string('potency_label')->nullable();

            $table->unsignedInteger('rank')->default(1);
            $table->decimal('suitability_score', 8, 2)->default(0);

            $table->text('rationale')->nullable();
            $table->text('repetition_note')->nullable();
            $table->text('caution')->nullable();

            $table->jsonb('source_chunks')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['potency_guidance_run_id', 'rank']);
            $table->index(['potency_range']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('potency_guidance_options');
    }
};
