<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materia_medica_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('sample');
            $table->string('source_title')->nullable();
            $table->string('remedy_code', 40);
            $table->string('remedy_name');
            $table->string('section')->nullable();
            $table->text('content');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('remedy_code');
            $table->index('remedy_name');
            $table->index('section');
        });

        DB::statement('ALTER TABLE materia_medica_chunks ADD COLUMN embedding vector(384)');
    }

    public function down(): void
    {
        Schema::dropIfExists('materia_medica_chunks');
    }
};
