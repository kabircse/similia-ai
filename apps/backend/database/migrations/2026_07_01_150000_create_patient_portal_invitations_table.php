<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_portal_invitations', function (Blueprint $table) {
            $table->id();

            $table->uuid('public_id')->unique();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('patient_visit_id')
                ->nullable()
                ->constrained('patient_visits')
                ->nullOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('prescription_id')
                ->nullable()
                ->constrained('patient_prescriptions')
                ->nullOnDelete();

            $table->string('purpose')->default('follow_up_form');
            $table->string('status')->default('active');

            $table->string('response_language', 20)->default('auto');
            $table->string('resolved_language', 20)->nullable();

            $table->string('secret_hash', 64)->unique();
            $table->text('secret_encrypted');
            $table->string('token_prefix', 12)->nullable();

            $table->unsignedInteger('max_submissions')->default(1);
            $table->unsignedInteger('submission_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);

            $table->text('message_to_patient')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_visit_id', 'created_at']);
            $table->index(['doctor_id', 'status']);
            $table->index(['purpose', 'status']);
            $table->index(['expires_at']);
            $table->index(['token_prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_portal_invitations');
    }
};
