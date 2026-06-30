<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('source')->default('legacy_sql');
            $table->unsignedBigInteger('external_id')->nullable();

            $table->string('code')->index();
            $table->string('title');
            $table->string('author')->nullable();

            $table->string('source_type')->default('general');
            $table->string('language', 20)->nullable();
            $table->string('edition')->nullable();
            $table->string('source_ref')->nullable();

            $table->string('visibility')->default('global_demo');
            $table->boolean('is_active')->default(true);

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->unique(['source', 'code']);

            $table->index(['source_type', 'language']);
            $table->index(['visibility', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_sources');
    }
};
