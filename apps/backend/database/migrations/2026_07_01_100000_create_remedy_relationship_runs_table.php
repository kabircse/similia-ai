<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedy_relationship_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();

            $table->foreignId('patient_visit_id')
                ->nullable()
                ->constrained('patient_visits')
                ->nullOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('primary_remedy_id')
                ->nullable()
                ->constrained('remedies')
                ->nullOnDelete();

            $table->string('primary_remedy_code', 80)->nullable();
            $table->string('primary_remedy_name');

            $table->foreignId('comparison_remedy_id')
                ->nullable()
                ->constrained('remedies')
                ->nullOnDelete();

            $table->string('comparison_remedy_code', 80)->nullable();
            $table->string('comparison_remedy_name')->nullable();

            $table->string('purpose')->default('general');
            $table->string('status')->default('completed');
            $table->string('response_language', 20)->default('auto');

            $table->jsonb('case_snapshot')->nullable();
            $table->jsonb('prescription_snapshot')->nullable();
            $table->jsonb('follow_up_snapshot')->nullable();
            $table->jsonb('retrieved_sources')->nullable();
            $table->jsonb('settings')->nullable();

            $table->text('relationship_summary')->nullable();
            $table->text('sequence_guidance')->nullable();
            $table->text('antidote_guidance')->nullable();
            $table->text('inimical_warning')->nullable();
            $table->text('complementary_note')->nullable();

            $table->jsonb('cautions')->nullable();
            $table->jsonb('doctor_review_points')->nullable();
            $table->jsonb('suggested_questions')->nullable();

            $table->text('safety_note')->nullable();
            $table->text('error_message')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['primary_remedy_id']);
            $table->index(['comparison_remedy_id']);
            $table->index(['purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedy_relationship_runs');
    }
};
