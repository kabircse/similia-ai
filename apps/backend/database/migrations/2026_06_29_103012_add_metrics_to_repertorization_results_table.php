<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('repertorization_results', function (Blueprint $table) {
            $table->jsonb('metrics')->nullable()->after('missing_important_rubrics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repertorization_results', function (Blueprint $table) {
            $table->dropColumn('metrics');
        });
    }
};
