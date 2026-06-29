<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_prescriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_visit_id')
                ->unique()
                ->constrained('patient_visits')
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('repertorization_run_id')
                ->nullable()
                ->constrained('repertorization_runs')
                ->nullOnDelete();

            $table->foreignId('repertorization_result_id')
                ->nullable()
                ->constrained('repertorization_results')
                ->nullOnDelete();

            $table->string('source_method')->nullable();

            $table->string('remedy_code', 40)->nullable();
            $table->string('remedy_name');
            $table->string('potency', 40);
            $table->string('repetition')->nullable();

            $table->text('dose_instruction')->nullable();
            $table->text('reason')->nullable();
            $table->text('advice')->nullable();
            $table->text('food_lifestyle_note')->nullable();

            $table->date('follow_up_date')->nullable();

            $table->string('status')->default('draft');
            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'status']);
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_prescriptions');
    }
};
