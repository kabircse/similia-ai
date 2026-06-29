<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repertory_rubric_remedies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('repertory_rubric_id')
                ->constrained('repertory_rubrics')
                ->cascadeOnDelete();

            $table->string('remedy_code', 40);
            $table->string('remedy_name');
            $table->unsignedTinyInteger('grade')->default(1);
            $table->string('source')->default('sample');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['repertory_rubric_id', 'remedy_code']);
            $table->index('remedy_code');
            $table->index('remedy_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repertory_rubric_remedies');
    }
};