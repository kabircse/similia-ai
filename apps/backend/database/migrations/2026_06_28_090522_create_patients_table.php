<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name');
            $table->unsignedTinyInteger('age_years')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('occupation')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('emergency_contact', 80)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['doctor_id', 'name']);
            $table->index(['doctor_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};