<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_fees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_visit_id')
                ->unique()
                ->constrained('patient_visits')
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('currency', 10)->default('BDT');

            $table->decimal('consultation_fee', 12, 2)->default(0);
            $table->decimal('medicine_fee', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);

            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);

            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->date('payment_date')->nullable();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'payment_status']);
            $table->index(['patient_id', 'created_at']);
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_fees');
    }
};
