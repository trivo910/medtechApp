<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The production database already had `prescriptions.appointment_id` and
 * `prescriptions.clinic_id`, but no migration ever created them and
 * `Prescription::$fillable` did not include them — so every prescription was
 * saved without a visit link. This migration makes the columns official and
 * safe to run on both existing and fresh databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('prescriptions', 'appointment_id')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->foreignId('appointment_id')
                      ->nullable()
                      ->after('family_member_id')
                      ->constrained('appointments')
                      ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('prescriptions', 'clinic_id')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->unsignedBigInteger('clinic_id')->nullable()->after('appointment_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prescriptions', 'appointment_id')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('appointment_id');
            });
        }

        if (Schema::hasColumn('prescriptions', 'clinic_id')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->dropColumn('clinic_id');
            });
        }
    }
};
