<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repertory_rubric_remedies', function (Blueprint $table) {
            $table->foreignId('remedy_id')
                ->nullable()
                ->after('repertory_rubric_id')
                ->constrained('remedies')
                ->nullOnDelete();

            $table->index('remedy_id');
        });

        Schema::table('materia_medica_chunks', function (Blueprint $table) {
            $table->foreignId('remedy_id')
                ->nullable()
                ->after('source_title')
                ->constrained('remedies')
                ->nullOnDelete();

            $table->index('remedy_id');
        });

        Schema::table('patient_prescriptions', function (Blueprint $table) {
            $table->foreignId('remedy_id')
                ->nullable()
                ->after('repertorization_result_id')
                ->constrained('remedies')
                ->nullOnDelete();

            $table->index('remedy_id');
        });
    }

    public function down(): void
    {
        Schema::table('patient_prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('remedy_id');
        });

        Schema::table('materia_medica_chunks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('remedy_id');
        });

        Schema::table('repertory_rubric_remedies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('remedy_id');
        });
    }
};
