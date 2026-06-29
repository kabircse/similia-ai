<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repertorization_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('repertorization_run_id')
                ->constrained('repertorization_runs')
                ->cascadeOnDelete();

            $table->string('remedy_code', 40);
            $table->string('remedy_name');

            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedInteger('rubric_coverage')->default(0);
            $table->unsignedInteger('essential_coverage')->default(0);
            $table->unsignedInteger('rank')->default(0);

            $table->jsonb('supporting_rubrics')->nullable();
            $table->jsonb('missing_important_rubrics')->nullable();

            $table->timestamps();

            $table->unique(['repertorization_run_id', 'remedy_code']);
            $table->index(['repertorization_run_id', 'rank']);
            $table->index('remedy_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repertorization_results');
    }
};