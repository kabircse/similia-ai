<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedies', function (Blueprint $table) {
            $table->id();

            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('abbreviation', 120)->nullable();

            $table->string('normalized_name')->index();
            $table->string('normalized_abbreviation')->nullable()->index();

            $table->string('source')->nullable();
            $table->unsignedBigInteger('external_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['name', 'abbreviation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedies');
    }
};
