<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('clinic_name')->default('Similia AI Clinic');
            $table->string('tagline')->nullable();

            $table->string('doctor_display_name')->nullable();
            $table->string('doctor_qualification')->nullable();

            $table->string('phone', 80)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            $table->text('address')->nullable();
            $table->string('logo_url')->nullable();

            $table->string('default_currency', 10)->default('BDT');
            $table->decimal('default_consultation_fee', 12, 2)->default(0);
            $table->decimal('default_followup_fee', 12, 2)->default(0);
            $table->boolean('medicine_fee_included')->default(true);

            $table->text('prescription_footer')->nullable();
            $table->text('case_sheet_footer')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index('doctor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_settings');
    }
};
