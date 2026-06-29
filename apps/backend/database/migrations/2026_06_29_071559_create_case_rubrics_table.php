<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_rubrics', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_visit_id')
                ->constrained('patient_visits')
                ->cascadeOnDelete();

            $table->foreignId('repertory_rubric_id')
                ->constrained('repertory_rubrics')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('symptom_type')->default('general');
            $table->string('importance')->default('important');
            $table->unsignedTinyInteger('weight')->default(3);
            $table->boolean('is_essential')->default(false);
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['patient_visit_id', 'repertory_rubric_id']);
            $table->index(['patient_visit_id', 'importance']);
            $table->index(['patient_visit_id', 'is_essential']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_rubrics');
    }
};