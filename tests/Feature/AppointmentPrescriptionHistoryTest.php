<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Prescription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentPrescriptionHistoryTest extends TestCase
{
    public function test_history_separates_people_doctors_and_unissued_or_future_prescriptions(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_user_id');
            $table->unsignedBigInteger('patient_user_id');
            $table->unsignedBigInteger('family_member_id')->nullable();
            $table->date('prescribed_date');
            $table->string('status');
            $table->softDeletes();
        });
        $this->travelTo(now()->setDate(2026, 10, 28));
        $base = ['doctor_user_id' => 7, 'patient_user_id' => 20, 'family_member_id' => null,
            'prescribed_date' => '2026-10-27', 'status' => 'issued', 'deleted_at' => null];
        foreach ([[], ['family_member_id' => 3], ['family_member_id' => 4],
            ['doctor_user_id' => 8], ['patient_user_id' => 21], ['status' => 'draft'],
            ['status' => 'cancelled'], ['deleted_at' => '2026-10-28 00:00:00'],
            ['prescribed_date' => '2026-10-29'], ['prescribed_date' => '2026-10-28']] as $changes) {
            DB::table('prescriptions')->insert(array_replace($base, $changes));
        }
        $appointment = new Appointment(['doctor_user_id' => 7, 'patient_user_id' => 20,
            'slot_datetime' => '2026-10-28 10:00:00']);
        $this->assertSame([1, 10], Prescription::forAppointmentPatient($appointment)->orderBy('id')->pluck('id')->all());
        $appointment->family_member_id = 3;
        $this->assertSame([2], Prescription::forAppointmentPatient($appointment)->pluck('id')->all());
        $appointment->family_member_id = null;
        $appointment->slot_datetime = '2026-10-27 10:00:00';
        $this->assertSame([1], Prescription::forAppointmentPatient($appointment)->pluck('id')->all());
        $appointment->slot_datetime = '2026-11-01 10:00:00';
        $this->assertSame([1, 10], Prescription::forAppointmentPatient($appointment)->orderBy('id')->pluck('id')->all());
        $appointment->patient_user_id = 99;
        $this->assertTrue(Prescription::forAppointmentPatient($appointment)->get()->isEmpty());
        $this->travelBack();
        Schema::drop('prescriptions');
    }
}
