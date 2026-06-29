<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_visits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('visit_date');
            $table->string('visit_type')->default('initial'); // initial, follow_up
            $table->string('status')->default('draft'); // draft, completed

            $table->string('case_source')->default('manual'); // manual, raw, mixed
            $table->text('chief_complaint')->nullable();
            $table->text('raw_case_text')->nullable();

            // Manual classical case-taking sections.
            // Later AI will also write into this same structure.
            $table->jsonb('case_sections')->default('{}');

            // These will be used more in Issue #7.
            $table->jsonb('missing_questions')->default('[]');
            $table->jsonb('red_flags')->default('[]');

            $table->text('doctor_notes')->nullable();
            $table->date('next_follow_up_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'visit_date']);
            $table->index(['doctor_id', 'visit_date']);
            $table->index(['doctor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_visits');
    }
};