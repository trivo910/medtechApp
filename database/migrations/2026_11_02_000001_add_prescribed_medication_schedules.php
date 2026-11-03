<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('medication_reminders', function (Blueprint $table) {
            $table->foreignId('prescription_medicine_id')->nullable()->unique()
                ->constrained('prescription_medicines')->cascadeOnDelete();
            $table->unsignedTinyInteger('interval_days')->default(1);
            $table->string('schedule_note')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->string('medicine_name', 200)->change();
            $table->string('dosage', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('medication_reminders', function (Blueprint $table) {
            $table->dropForeign(['prescription_medicine_id']);
            $table->dropUnique(['prescription_medicine_id']);
            $table->dropColumn(['prescription_medicine_id', 'interval_days', 'schedule_note', 'last_notified_at']);
        });
    }
};
