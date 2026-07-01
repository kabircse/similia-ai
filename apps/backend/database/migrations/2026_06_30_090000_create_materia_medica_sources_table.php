<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materia_medica_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('legacy_sql');
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('name');
            $table->string('author')->nullable();
            $table->string('abbreviation')->nullable();
            $table->string('edition')->nullable();
            $table->unsignedInteger('remedies_count')->default(0);
            $table->string('language', 20)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['name', 'abbreviation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materia_medica_sources');
    }
};
