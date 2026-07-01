<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repertory_rubrics', function (Blueprint $table) {
            $table->foreignId('repertory_source_id')
                ->nullable()
                ->after('id')
                ->constrained('repertory_sources')
                ->nullOnDelete();

            $table->unsignedBigInteger('external_id')->nullable()->after('repertory_source_id');
            $table->unsignedBigInteger('external_repertory_id')->nullable()->after('external_id');
            $table->unsignedInteger('medicine_count')->default(0)->after('rubric_text');
            $table->unsignedTinyInteger('default_weight')->default(1)->after('medicine_count');
            $table->boolean('is_selectable')->default(true)->after('default_weight');

            $table->index(['source', 'external_id']);
            $table->index(['repertory_source_id', 'chapter']);
            $table->index('is_selectable');
        });

        Schema::table('repertory_rubric_remedies', function (Blueprint $table) {
            $table->foreignId('repertory_source_id')
                ->nullable()
                ->after('id')
                ->constrained('repertory_sources')
                ->nullOnDelete();

            $table->unsignedBigInteger('external_id')->nullable()->after('repertory_source_id');
            $table->unsignedBigInteger('external_rubric_id')->nullable()->after('external_id');
            $table->unsignedBigInteger('external_remedy_id')->nullable()->after('external_rubric_id');

            $table->index(['source', 'external_id']);
            $table->index(['external_rubric_id', 'external_remedy_id']);
            $table->index('repertory_source_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            DB::statement(
                'CREATE INDEX IF NOT EXISTS repertory_rubrics_rubric_path_trgm_idx
                 ON repertory_rubrics USING gin (rubric_path gin_trgm_ops)'
            );

            DB::statement(
                'CREATE INDEX IF NOT EXISTS repertory_rubrics_rubric_text_trgm_idx
                 ON repertory_rubrics USING gin (rubric_text gin_trgm_ops)'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS repertory_rubrics_rubric_path_trgm_idx');
            DB::statement('DROP INDEX IF EXISTS repertory_rubrics_rubric_text_trgm_idx');
        }

        Schema::table('repertory_rubric_remedies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repertory_source_id');
            $table->dropColumn([
                'external_id',
                'external_rubric_id',
                'external_remedy_id',
            ]);
        });

        Schema::table('repertory_rubrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repertory_source_id');
            $table->dropColumn([
                'external_id',
                'external_repertory_id',
                'medicine_count',
                'default_weight',
                'is_selectable',
            ]);
        });
    }
};
