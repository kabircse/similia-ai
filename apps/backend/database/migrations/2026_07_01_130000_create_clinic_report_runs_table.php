<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_report_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('created_by_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('scope_doctor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('report_type')->default('monthly');
            $table->string('status')->default('completed');

            $table->string('response_language', 20)->default('auto');
            $table->string('resolved_language', 20)->nullable();

            $table->date('period_start');
            $table->date('period_end');

            $table->string('title')->nullable();

            $table->text('executive_summary')->nullable();
            $table->text('clinical_activity_summary')->nullable();
            $table->text('outcome_summary')->nullable();
            $table->text('remedy_summary')->nullable();
            $table->text('safety_summary')->nullable();
            $table->text('finance_summary')->nullable();
            $table->text('follow_up_summary')->nullable();

            $table->jsonb('key_metrics')->nullable();
            $table->jsonb('dashboard_snapshot')->nullable();
            $table->jsonb('recommendations')->nullable();
            $table->jsonb('limitations')->nullable();

            $table->text('safety_note')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('exported_at')->nullable();
            $table->timestamp('printed_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['created_by_id', 'created_at']);
            $table->index(['scope_doctor_id', 'period_start', 'period_end']);
            $table->index(['report_type', 'status']);
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_report_runs');
    }
};
