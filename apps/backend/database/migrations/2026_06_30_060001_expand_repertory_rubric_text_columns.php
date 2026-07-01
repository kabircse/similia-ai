<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repertory_rubrics', function ($table) {
            $table->dropIndex(['rubric_text']);
        });

        DB::statement('ALTER TABLE repertory_rubrics ALTER COLUMN rubric_path TYPE TEXT');
        DB::statement('ALTER TABLE repertory_rubrics ALTER COLUMN rubric_text TYPE TEXT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE repertory_rubrics ALTER COLUMN rubric_path TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE repertory_rubrics ALTER COLUMN rubric_text TYPE VARCHAR(255)');

        Schema::table('repertory_rubrics', function ($table) {
            $table->index('rubric_text');
        });
    }
};
