<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedy_aliases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('remedy_id')
                ->constrained('remedies')
                ->cascadeOnDelete();

            $table->string('alias');
            $table->string('normalized_alias')->index();
            $table->string('alias_type')->default('imported');
            $table->string('source')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['remedy_id', 'normalized_alias']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedy_aliases');
    }
};
