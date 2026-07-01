<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_handout_sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_handout_run_id')
                ->constrained('patient_handout_runs')
                ->cascadeOnDelete();

            $table->string('section_key');
            $table->string('category')->default('instruction');
            $table->unsignedInteger('sort_order')->default(1);

            $table->string('title');
            $table->longText('content');

            $table->boolean('is_important')->default(false);

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique(['patient_handout_run_id', 'section_key']);
            $table->index(['patient_handout_run_id', 'sort_order']);
            $table->index(['category', 'is_important']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_handout_sections');
    }
};
