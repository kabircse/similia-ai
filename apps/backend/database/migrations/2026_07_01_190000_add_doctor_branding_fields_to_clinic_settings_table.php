<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->text('prescription_header')->nullable()->after('prescription_footer');
            $table->text('prescription_disclaimer')->nullable()->after('prescription_header');
            $table->unsignedInteger('appointment_default_duration_minutes')->nullable()->after('prescription_disclaimer');
            $table->string('appointment_default_timezone', 80)->nullable()->after('appointment_default_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn([
                'prescription_header',
                'prescription_disclaimer',
                'appointment_default_duration_minutes',
                'appointment_default_timezone',
            ]);
        });
    }
};
