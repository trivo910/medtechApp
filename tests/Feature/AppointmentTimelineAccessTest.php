<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentTimelineAccessTest extends TestCase
{
    public function test_only_an_owned_appointment_for_the_patient_can_bypass_separate_access(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_user_id');
            $table->unsignedBigInteger('patient_user_id');
            $table->unsignedBigInteger('family_member_id')->nullable();
            $table->softDeletes();
        });
        Schema::create('doctor_access_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_user_id');
            $table->unsignedBigInteger('patient_user_id');
            $table->string('status');
            $table->timestamp('access_expires_at')->nullable();
        });
        DB::table('appointments')->insert(['id' => 31, 'doctor_user_id' => 7, 'patient_user_id' => 10]);
        $doctor = new User;
        $doctor->id = 7;
        $this->actingAs($doctor)->withoutMiddleware();

        // Reaching field validation proves an access request is not required.
        $this->post(route('doctor.timelines.assign.save', 10), ['appointment_id' => 31])
            ->assertSessionHasErrors(['template_id', 'start_date']);
        $this->post(route('doctor.timelines.assign.save', 11), ['appointment_id' => 31])->assertNotFound();
        DB::table('appointments')->where('id', 31)->update(['doctor_user_id' => 8]);
        $this->post(route('doctor.timelines.assign.save', 10), ['appointment_id' => 31])->assertNotFound();
        $this->post(route('doctor.timelines.assign.save', 10), [])->assertForbidden();
        $this->postJson(route('doctor.timelines.assign.save', 10), [])
            ->assertForbidden()->assertJsonPath('message', 'Patient access is missing or has expired. Request patient access and ask the patient to approve it before assigning a timeline.');
        DB::table('appointments')->where('id', 31)->update(['doctor_user_id' => 7]);
        $this->postJson(route('doctor.timelines.assign.save', 10), ['appointment_id' => 31])
            ->assertUnprocessable()->assertJsonPath('errors.template_id.0', 'Select a timeline before assigning.')
            ->assertJsonPath('errors.start_date.0', 'Choose a start date for this timeline.');
    }
}
