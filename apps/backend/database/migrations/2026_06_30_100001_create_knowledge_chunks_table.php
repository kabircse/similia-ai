<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('knowledge_source_id')
                ->constrained('knowledge_sources')
                ->cascadeOnDelete();

            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('source')->default('legacy_sql');
            $table->unsignedBigInteger('external_id')->nullable();

            $table->string('source_type')->default('general');
            $table->string('book_code')->index();

            $table->unsignedInteger('section_no')->default(0);
            $table->unsignedInteger('chunk_index')->default(0);

            $table->string('title')->nullable();
            $table->text('summary')->nullable();

            $table->text('content');
            $table->string('content_hash', 64)->nullable();

            $table->string('language', 20)->nullable();
            $table->string('source_ref')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique(['source', 'external_id', 'chunk_index']);

            $table->index(['knowledge_source_id', 'section_no']);
            $table->index(['source_type', 'book_code']);
            $table->index(['language']);
            $table->index('content_hash');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE knowledge_chunks ADD COLUMN embedding vector(384)');
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            DB::statement(
                'CREATE INDEX IF NOT EXISTS knowledge_chunks_content_trgm_idx
                 ON knowledge_chunks USING gin (content gin_trgm_ops)'
            );

            DB::statement(
                'CREATE INDEX IF NOT EXISTS knowledge_chunks_title_trgm_idx
                 ON knowledge_chunks USING gin (title gin_trgm_ops)'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS knowledge_chunks_content_trgm_idx');
            DB::statement('DROP INDEX IF EXISTS knowledge_chunks_title_trgm_idx');
        }

        Schema::dropIfExists('knowledge_chunks');
    }
};
