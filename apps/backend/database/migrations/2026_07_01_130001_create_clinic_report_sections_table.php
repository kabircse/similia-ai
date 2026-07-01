<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_report_sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_report_run_id')
                ->constrained('clinic_report_runs')
                ->cascadeOnDelete();

            $table->string('section_key');
            $table->string('category')->default('summary');
            $table->unsignedInteger('sort_order')->default(1);

            $table->string('title');
            $table->longText('content');

            $table->jsonb('metrics')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique(['clinic_report_run_id', 'section_key']);
            $table->index(['clinic_report_run_id', 'sort_order']);
            $table->index(['category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_report_sections');
    }
};
