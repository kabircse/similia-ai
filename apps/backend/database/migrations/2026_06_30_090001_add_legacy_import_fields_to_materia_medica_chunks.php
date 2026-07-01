<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materia_medica_chunks', function (Blueprint $table) {
            $table->foreignId('materia_medica_source_id')
                ->nullable()
                ->after('id')
                ->constrained('materia_medica_sources')
                ->nullOnDelete();

            $table->unsignedBigInteger('external_id')->nullable()->after('materia_medica_source_id');
            $table->unsignedBigInteger('external_mm_id')->nullable()->after('external_id');
            $table->unsignedBigInteger('external_remedy_id')->nullable()->after('external_mm_id');
            $table->unsignedInteger('chunk_index')->default(0)->after('section');
            $table->string('content_hash', 64)->nullable()->after('content');
            $table->string('language', 20)->nullable()->after('content_hash');

            $table->index(['source', 'external_id']);
            $table->index(['external_mm_id', 'external_remedy_id']);
            $table->index(['materia_medica_source_id', 'remedy_id']);
            $table->index('content_hash');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            DB::statement(
                'CREATE INDEX IF NOT EXISTS materia_medica_chunks_content_trgm_idx
                 ON materia_medica_chunks USING gin (content gin_trgm_ops)'
            );

            DB::statement(
                'CREATE INDEX IF NOT EXISTS materia_medica_chunks_remedy_section_idx
                 ON materia_medica_chunks (remedy_id, section)'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS materia_medica_chunks_content_trgm_idx');
            DB::statement('DROP INDEX IF EXISTS materia_medica_chunks_remedy_section_idx');
        }

        Schema::table('materia_medica_chunks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('materia_medica_source_id');

            $table->dropColumn([
                'external_id',
                'external_mm_id',
                'external_remedy_id',
                'chunk_index',
                'content_hash',
                'language',
            ]);
        });
    }
};
