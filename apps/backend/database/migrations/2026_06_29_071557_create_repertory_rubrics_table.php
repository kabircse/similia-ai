<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repertory_rubrics', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('sample');
            $table->string('chapter')->nullable();
            $table->string('rubric_path');
            $table->string('rubric_text');
            $table->foreignId('parent_id')->nullable()->constrained('repertory_rubrics')->nullOnDelete();
            $table->unsignedInteger('page')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('source');
            $table->index('chapter');
            $table->index('rubric_text');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repertory_rubrics');
    }
};