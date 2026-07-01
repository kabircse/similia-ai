<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_progress_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('follow_up_analysis_run_id')
                ->constrained('follow_up_analysis_runs')
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('patient_visit_id')
                ->constrained('patient_visits')
                ->cascadeOnDelete();

            $table->string('category')->nullable();
            $table->string('symptom');
            $table->string('change_status')->default('unchanged');

            $table->unsignedTinyInteger('previous_intensity')->nullable();
            $table->unsignedTinyInteger('current_intensity')->nullable();

            $table->decimal('change_score', 8, 2)->default(0);

            $table->text('evidence')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['follow_up_analysis_run_id', 'change_status']);
            $table->index(['patient_visit_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_progress_items');
    }
};
