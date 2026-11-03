<?php

namespace App\Services;

use App\Models\User;
use App\Models\Appointment;
use App\Models\Prescription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    private string $provider;

    public function __construct()
    {
        $this->provider = config('whatsapp.provider', 'mock');
    }

    // ─── Appointment Messages ────────────────────────────────────────────────

    public function sendAppointmentConfirmation(Appointment $appointment): bool
    {
        $patient    = $appointment->patient;
        $doctor     = $appointment->doctor;
        $doctorName = $doctor->profile->full_name;
        $dateTime   = $appointment->slot_datetime->format('D, d M Y \a\t h:i A');
        $clinic     = $doctor->doctorProfile->clinic_name ?? 'the clinic';

        $message = "✅ *Appointment Confirmed*\n\n"
            . "Dear {$patient->profile->full_name},\n"
            . "Your appointment with *{$doctorName}* is confirmed.\n\n"
            . "📅 *Date & Time:* {$dateTime}\n"
            . "🏥 *Clinic:* {$clinic}\n"
            . "🔖 *Ref No:* {$appointment->appointment_number}\n\n"
            . "_Reply CANCEL to cancel this appointment._\n"
            . "_Naumah Clinic_";

        return $this->send($patient->full_mobile, $message);
    }

    public function sendAppointmentReminder(Appointment $appointment, string $when = '24h'): bool
    {
        $patient    = $appointment->patient;
        $doctor     = $appointment->doctor;
        $doctorName = $doctor->profile->full_name;
        $dateTime   = $appointment->slot_datetime->format('D, d M Y \a\t h:i A');
        $label      = $when === '1h' ? 'in *1 hour*' : 'tomorrow';

        $message = "⏰ *Appointment Reminder*\n\n"
            . "Dear {$patient->profile->full_name},\n"
            . "This is a reminder that you have an appointment {$label}.\n\n"
            . "👨‍⚕️ *Doctor:* {$doctorName}\n"
            . "📅 *When:* {$dateTime}\n"
            . "🏥 *Clinic:* {$doctor->doctorProfile->clinic_name}\n\n"
            . "_Please arrive 10 minutes early._\n"
            . "_Naumah Clinic_";

        return $this->send($patient->full_mobile, $message);
    }

    public function sendAppointmentCancellation(Appointment $appointment): bool
    {
        $patient    = $appointment->patient;
        $doctor     = $appointment->doctor;
        $doctorName = $doctor?->profile?->full_name ?? 'your doctor';
        $dateTime   = $appointment->slot_datetime?->format('D, d M Y \a\t h:i A') ?? '';
        $clinic     = $doctor?->doctorProfile?->clinic_name ?? 'the clinic';
        $reason     = $appointment->cancellation_reason;

        $message = "❌ *Appointment Cancelled*\n\n"
            . "Dear {$patient->profile->full_name},\n"
            . "Your appointment has been cancelled.\n\n"
            . "👨‍⚕️ *Doctor:* {$doctorName}\n"
            . "📅 *Was scheduled:* {$dateTime}\n"
            . "🏥 *Clinic:* {$clinic}\n"
            . "🔖 *Ref No:* {$appointment->appointment_number}\n\n"
            . ($reason ? "📝 *Reason:* {$reason}\n\n" : '')
            . "Please book a new slot at your convenience.\n"
            . "_Naumah Clinic_";

        return $this->send($patient->full_mobile, $message);
    }

    public function sendPaymentRequest(Appointment $appointment): bool
    {
        $patient    = $appointment->patient;
        $doctor     = $appointment->doctor;
        $doctorName = $doctor->profile->full_name ?? 'your doctor';
        $dateTime   = $appointment->slot_datetime?->format('D, d M Y \a\t h:i A') ?? '';
        $amount     = '₹' . number_format((float) $appointment->fee, 0);
        $payLink    = route('patient.payments.index');

        $message = "💰 *Payment Request*\n\n"
            . "Dear {$patient->profile->full_name},\n"
            . "Dr. {$doctorName} has requested payment of *{$amount}* for your appointment on {$dateTime}.\n\n"
            . "Please pay here: {$payLink}\n\n"
            . "_Naumah Clinic_";

        return $this->send($patient->full_mobile, $message);
    }

    public function sendFollowUpReminder(User $patient, string $doctorName, string $followUpDate): bool
    {
        $message = "📋 *Follow-up Reminder*\n\n"
            . "Dear {$patient->profile->full_name},\n"
            . "Dr. {$doctorName} has recommended a follow-up visit.\n\n"
            . "📅 *Recommended Date:* {$followUpDate}\n\n"
            . "Please book your appointment at your earliest.\n"
            . "_Naumah Clinic_";

        return $this->send($patient->full_mobile, $message);
    }

    // ─── Prescription Messages ───────────────────────────────────────────────

    public function sendPrescription(Prescription $prescription): bool
    {
        $patient    = $prescription->patient;
        $doctor     = $prescription->doctor;
        $medicines  = $prescription->medicines;

        $medicineList = $medicines->map(function ($med, $i) {
            return ($i + 1) . ". *{$med->medicine_name}* {$med->dosage} — {$med->frequency} — {$med->timingLabel}";
        })->join("\n");

        $message = "💊 *New Prescription*\n\n"
            . "Dear {$patient->profile->full_name},\n"
            . "Your prescription from *{$doctor->profile->full_name}* is ready.\n\n"
            . "📋 *Rx No:* {$prescription->prescription_number}\n"
            . "📅 *Date:* {$prescription->prescribed_date->format('d M Y')}\n\n"
            . "*Medicines:*\n{$medicineList}\n\n"
            . ($prescription->notes
                ? "📝 *Instructions:* {$prescription->notes}\n\n"
                : '')
            . ($prescription->follow_up_date
                ? "🗓️ *Follow-up:* {$prescription->follow_up_date->format('d M Y')}\n\n"
                : '')
            . "_Show this message at the pharmacy or download PDF from the app._\n"
            . "_Naumah Clinic_";

        $sent = $this->send($patient->full_mobile, $message);

        if ($sent) {
            $prescription->update([
                'is_sent_whatsapp' => true,
                'whatsapp_sent_at' => now(),
            ]);
        }

        return $sent;
    }

    // ─── Access Control Messages ─────────────────────────────────────────────

    public function sendAccessRequest(User $patient, string $doctorName, string $otp, int $expiresMinutes): bool
    {
        $message = "🔐 *Medical Records Access Request*\n\n"
            . "Dear {$patient->profile->full_name},\n"
            . "*Dr. {$doctorName}* is requesting access to your medical records.\n\n"
            . "Your OTP: *{$otp}*\n"
            . "_(Valid for {$expiresMinutes} minutes)_\n\n"
            . "Share this OTP with the doctor *only if you approve* this request.\n"
            . "Do NOT share if you did not visit this doctor.\n\n"
            . "_Naumah Clinic_";

        return $this->send($patient->full_mobile, $message);
    }

    public function sendAccessApproved(User $doctor, string $patientName): bool
    {
        $message = "✅ *Access Approved*\n\n"
            . "Dear Dr. {$doctor->profile->full_name},\n"
            . "*{$patientName}* has approved your request to access their medical records.\n\n"
            . "_Access is valid for " . config('medtech.access.session_hours', 8) . " hours._\n"
            . "_Naumah Clinic_";

        return $this->send($doctor->full_mobile, $message);
    }

    public function sendAccessDenied(User $doctor, string $patientName): bool
    {
        $message = "❌ *Access Denied*\n\n"
            . "Dear Dr. {$doctor->profile->full_name},\n"
            . "*{$patientName}* has declined your request to access their medical records.\n\n"
            . "_If you believe this is an error, please contact the patient directly._\n"
            . "_Naumah Clinic_";

        return $this->send($doctor->full_mobile, $message);
    }

    // ─── Health & Reminder Messages ──────────────────────────────────────────

    public function sendMedicationReminder(User $patient, string $medicineName, string $dosage, string $time): bool
    {
        $message = "💊 *Medication Reminder*\n\n"
            . "Time to take your medicine!\n\n"
            . "🔹 *{$medicineName}* — {$dosage}\n"
            . "⏰ *Time:* {$time}\n\n"
            . "_Stay healthy! — Naumah Clinic_";

        return $this->send($patient->full_mobile, $message);
    }

    public function sendVaccinationReminder(User $patient, string $childName, string $vaccineName, string $dueDate, int $daysAway): bool
    {
        $urgency = $daysAway <= 1 ? '🚨 *Due Today!*' : "📅 Due in *{$daysAway} days*";

        $message = "💉 *Vaccination Reminder*\n\n"
            . "Dear {$patient->profile->full_name},\n"
            . "{$urgency}\n\n"
            . "👶 *Child:* {$childName}\n"
            . "💉 *Vaccine:* {$vaccineName}\n"
            . "📅 *Due Date:* {$dueDate}\n\n"
            . "_Please visit your pediatrician to get this vaccine on time._\n"
            . "_Naumah Clinic_";

        return $this->send($patient->full_mobile, $message);
    }

    public function sendPregnancyMilestone(User $patient, string $milestoneName, string $week, string $description): bool
    {
        $message = "🌸 *Pregnancy Milestone — Week {$week}*\n\n"
            . "Dear {$patient->profile->full_name},\n\n"
            . "🗓️ *This Week:* {$milestoneName}\n\n"
            . "{$description}\n\n"
            . "_Take care of yourself and your little one! — Naumah Clinic_";

        return $this->send($patient->full_mobile, $message);
    }

    // ─── Provider Dispatch ───────────────────────────────────────────────────

    private function send(string $toMobile, string $message): bool
    {
        return match ($this->provider) {
            'mock'     => $this->sendMock($toMobile, $message),
            '360dialog'=> $this->send360Dialog($toMobile, $message),
            'twilio'   => $this->sendTwilio($toMobile, $message),
            'wati'     => $this->sendWati($toMobile, $message),
            default    => $this->sendMock($toMobile, $message),
        };
    }

    private function sendMock(string $toMobile, string $message): bool
    {
        Log::info('[WhatsApp MOCK]', [
            'to'      => $toMobile,
            'message' => $message,
        ]);
        return true;
    }

    private function send360Dialog(string $toMobile, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'D360-API-KEY' => config('whatsapp.360dialog.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('whatsapp.360dialog.base_url') . '/messages', [
                'to'   => $toMobile,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('[WhatsApp 360Dialog] Failed', ['body' => $response->body()]);
            return false;

        } catch (\Throwable $e) {
            Log::error('[WhatsApp 360Dialog] Exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendTwilio(string $toMobile, string $message): bool
    {
        $response = Http::withBasicAuth(
            config('whatsapp.twilio.sid'),
            config('whatsapp.twilio.token')
        )->timeout(15)->asForm()->post(
            "https://api.twilio.com/2010-04-01/Accounts/" . config('whatsapp.twilio.sid') . "/Messages.json",
            [
                'To'   => 'whatsapp:' . $toMobile,
                'From' => config('whatsapp.twilio.from'),
                'Body' => $message,
            ]
        );

        if ($response->successful()) {
            return true;
        }

        $body    = $response->json();
        $reason  = $body['message'] ?? $response->body();
        $code    = $body['code'] ?? $response->status();

        Log::error('[WhatsApp Twilio] Failed', ['to' => $toMobile, 'code' => $code, 'reason' => $reason]);
        throw new \RuntimeException("Twilio error {$code}: {$reason}");
    }

    private function sendWati(string $toMobile, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('whatsapp.wati.access_token'),
            ])->timeout(8)->post(config('whatsapp.wati.api_endpoint') . '/sendSessionMessage/' . $toMobile, [
                'messageText' => $message,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('[WhatsApp WATI] Failed', ['to' => $toMobile, 'response' => $response->body()]);
            return false;

        } catch (\Throwable $e) {
            Log::error('[WhatsApp WATI] Exception', ['to' => $toMobile, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function sendVerificationApproved($doctor): bool
    {
        $name   = $doctor->profile?->full_name ?? 'Doctor';
        $mobile = ($doctor->country_code ?? '+91') . $doctor->mobile_number;

        $message = "✅ *Verification Approved*\n\n"
            . "Dear Dr. {$name},\n"
            . "Your account has been verified successfully. You can now start accepting appointments.\n\n"
            . "_Naumah Clinic_";

        return $this->send($mobile, $message);
    }
}
