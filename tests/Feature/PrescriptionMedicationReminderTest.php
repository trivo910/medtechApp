<?php

namespace Tests\Feature;

use App\Models\MedicationReminder;
use App\Models\Notification;
use App\Models\Prescription;
use App\Services\PrescriptionReminderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrescriptionMedicationReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', fn(Blueprint $t) => $t->id());
        Schema::create('family_members', function (Blueprint $t) {
            $t->id(); $t->string('full_name'); $t->softDeletes();
        });
        Schema::create('prescriptions', function (Blueprint $t) {
            $t->id(); $t->string('prescription_number'); $t->unsignedBigInteger('doctor_user_id');
            $t->unsignedBigInteger('patient_user_id'); $t->unsignedBigInteger('family_member_id')->nullable();
            $t->date('prescribed_date'); $t->string('status'); $t->timestamps(); $t->softDeletes();
        });
        // Exercise the actual reminder schema and additive migration against SQLite.
        Schema::create('prescription_medicines', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('prescription_id'); $t->string('medicine_name');
            $t->string('dosage')->nullable(); $t->string('frequency')->nullable();
            $t->integer('duration_days')->nullable(); $t->string('timing')->nullable();
            $t->string('special_instructions')->nullable(); $t->integer('sort_order')->default(0); $t->timestamps();
        });
        (require database_path('migrations/0011_create_health_tracking_tables.php'))->up();
        (require database_path('migrations/2026_11_02_000001_add_prescribed_medication_schedules.php'))->up();
        (require database_path('migrations/0012_create_notifications_table.php'))->up();
        DB::table('users')->insert(['id' => 1]);
        DB::table('family_members')->insert(['id' => 2, 'full_name' => 'Family Patient']);
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        DB::purge('sqlite');
        parent::tearDown();
    }

    private function course(string $frequency = 'Twice daily', ?int $days = 5, ?int $member = null): Prescription
    {
        $rx = Prescription::create(['doctor_user_id' => 1, 'patient_user_id' => 1,
            'family_member_id' => $member, 'prescribed_date' => today(), 'status' => 'issued']);
        $rx->medicines()->create(['medicine_name' => 'Test medicine', 'dosage' => '10 mg',
            'frequency' => $frequency, 'duration_days' => $days, 'timing' => 'after_food']);
        app(PrescriptionReminderService::class)->createForPrescription($rx);
        return $rx;
    }

    public function test_courses_are_idempotent_separate_people_and_end_inclusively(): void
    {
        $rx = $this->course();
        app(PrescriptionReminderService::class)->createForPrescription($rx);
        $this->assertSame(1, MedicationReminder::count());
        $reminder = MedicationReminder::first();
        $this->assertSame('2026-11-06', $reminder->end_date->toDateString());
        $this->assertNull($reminder->family_member_id);
        $this->course('Once daily', 1, 2);
        $this->assertSame(2, MedicationReminder::count());
        $family = MedicationReminder::where('family_member_id', 2)->first();
        $this->assertTrue($family->isActive());
        $this->travelTo(now()->setTime(23, 59));
        $this->assertTrue($family->fresh()->isActive());
        $this->travelTo(now()->addDay()->startOfDay());
        $this->assertFalse($family->fresh()->isActive());
        $this->assertSame(1, MedicationReminder::active()->count());
    }

    public function test_dispatch_is_duplicate_safe_and_stops_on_pause_cancellation_deletion_and_expiry(): void
    {
        $rx = $this->course('Every 8 hours', 1, 2);
        $service = app(PrescriptionReminderService::class);
        $this->assertSame(1, $service->sendDue());
        $this->assertSame(0, $service->sendDue());
        $this->assertStringContainsString('Family Patient', Notification::first()->body);
        $reminder = MedicationReminder::first();
        $reminder->update(['is_active' => false]);
        $this->travelTo(now()->setTime(16, 0));
        $this->assertSame(0, $service->sendDue());
        $reminder->update(['is_active' => true]);
        $rx->update(['status' => 'cancelled']);
        $this->assertSame(0, $service->sendDue());
        $rx->update(['status' => 'issued']);
        $rx->delete();
        $this->assertSame(0, $service->sendDue());
        $rx->restore();
        $this->assertSame(1, $service->sendDue());
        $this->travelTo(now()->addDay()->setTime(8, 0));
        $this->assertSame(0, $service->sendDue());
        $this->assertSame(2, Notification::count());
    }

    public function test_weekly_future_and_unclear_schedules_do_not_send_daily_or_indefinitely(): void
    {
        $this->course('Once weekly', 15);
        $service = app(PrescriptionReminderService::class);
        $this->assertSame(1, $service->sendDue());
        $this->travelTo(now()->addDay());
        $this->assertSame(0, $service->sendDue());
        $this->travelTo(now()->addDays(6));
        $this->assertSame(1, $service->sendDue());
        $this->course('As needed (SOS)', 5);
        $this->course('Unclear frequency', 5);
        $this->course('Once daily', null);
        $this->assertSame(3, MedicationReminder::whereNotNull('schedule_note')->count());
        $rx = $this->course('Once daily', 1);
        $rx->update(['prescribed_date' => today()->addDays(2)]);
        MedicationReminder::where('prescription_id', $rx->id)->update([
            'start_date' => today()->addDays(2), 'end_date' => today()->addDays(2),
        ]);
        $this->assertSame(0, $service->sendDue());
    }

    public function test_patient_can_adjust_times_but_cannot_extend_course_or_change_dosage(): void
    {
        $this->course();
        $patient = new \App\Models\User;
        $patient->id = 1;
        $this->actingAs($patient);
        $reminder = MedicationReminder::first();
        $request = \Illuminate\Http\Request::create('/', 'PUT', [
            'reminder_times' => ['09:00', '21:00'], 'dosage' => 'changed', 'end_date' => '2027-01-01',
        ]);
        app(\App\Http\Controllers\Patient\MedicationReminderController::class)->update($request, $reminder);
        $this->assertSame(['09:00', '21:00'], $reminder->fresh()->reminder_times);
        $this->assertSame('10 mg', $reminder->fresh()->dosage);
        $this->assertSame('2026-11-06', $reminder->fresh()->end_date->toDateString());
        $patient->id = 9;
        $this->actingAs($patient);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Http\Controllers\Patient\MedicationReminderController::class)->update($request, $reminder);
    }

    public function test_hourly_schedule_preserves_spacing_and_review_entries_cannot_be_activated(): void
    {
        $this->course('Every 8 hours');
        $patient = new \App\Models\User;
        $patient->id = 1;
        $this->actingAs($patient);
        $controller = app(\App\Http\Controllers\Patient\MedicationReminderController::class);
        $reminder = MedicationReminder::first();
        $controller->update(\Illuminate\Http\Request::create('/', 'PUT', [
            'reminder_times' => ['08:00', '09:00', '10:00'],
        ]), $reminder);
        $this->assertSame(['00:00', '08:00', '16:00'], $reminder->fresh()->reminder_times);
        $controller->update(\Illuminate\Http\Request::create('/', 'PUT', [
            'reminder_times' => ['01:00', '09:00', '17:00'],
        ]), $reminder);
        $this->assertSame(['01:00', '09:00', '17:00'], $reminder->fresh()->reminder_times);
        $this->course('Once daily', null);
        $review = MedicationReminder::whereNotNull('schedule_note')->first();
        $controller->toggle(\Illuminate\Http\Request::create('/', 'POST'), $review);
        $this->assertFalse($review->fresh()->is_active);
    }
}
