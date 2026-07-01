<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repertory_rubrics', function (Blueprint $table) {
            $table->string('import_key')->nullable()->unique()->after('id');
        });

        Schema::table('repertory_rubric_remedies', function (Blueprint $table) {
            $table->string('import_key')->nullable()->unique()->after('id');
        });

        Schema::table('materia_medica_chunks', function (Blueprint $table) {
            $table->string('import_key')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('materia_medica_chunks', function (Blueprint $table) {
            $table->dropColumn('import_key');
        });

        Schema::table('repertory_rubric_remedies', function (Blueprint $table) {
            $table->dropColumn('import_key');
        });

        Schema::table('repertory_rubrics', function (Blueprint $table) {
            $table->dropColumn('import_key');
        });
    }
};
