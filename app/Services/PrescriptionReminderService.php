<?php

namespace App\Services;

use App\Models\MedicationReminder;
use App\Models\Notification;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;

class PrescriptionReminderService
{
    // Suggested notification times, not a change to the doctor's dose instructions.
    public function schedule(?string $frequency): array
    {
        return match (strtolower(trim($frequency ?? ''))) {
            'once daily', 'od' => [['08:00'], 1],
            'twice daily', 'bd', '1-0-1' => [['08:00', '20:00'], 1],
            'three times daily', 'tds', '1-1-1' => [['08:00', '14:00', '20:00'], 1],
            'four times daily' => [['08:00', '12:00', '16:00', '20:00'], 1],
            'every 6 hours' => [['00:00', '06:00', '12:00', '18:00'], 1],
            'every 8 hours' => [['00:00', '08:00', '16:00'], 1],
            'every 12 hours' => [['08:00', '20:00'], 1],
            'once weekly' => [['08:00'], 7],
            'at bedtime' => [['22:00'], 1],
            default => [[], 1],
        };
    }

    public function createForPrescription(Prescription $prescription): void
    {
        if ($prescription->status !== 'issued') return;

        foreach ($prescription->medicines()->get() as $medicine) {
            [$times, $interval] = $this->schedule($medicine->frequency);
            if (strtolower(trim($medicine->frequency ?? '')) === 'once daily' && $medicine->timing === 'bed_time') {
                $times = ['22:00'];
            }
            $note = !$medicine->duration_days ? 'Needs doctor clarification: duration is missing.' : null;
            if (!$times) {
                $note = $medicine->frequency === 'As needed (SOS)'
                    ? 'As needed: no repeating alerts.'
                    : 'Needs doctor clarification: frequency is missing or unsupported.';
            }

            MedicationReminder::firstOrCreate(['prescription_medicine_id' => $medicine->id], [
                'patient_user_id' => $prescription->patient_user_id,
                'family_member_id' => $prescription->family_member_id,
                'prescription_id' => $prescription->id,
                'medicine_name' => $medicine->medicine_name,
                'dosage' => $medicine->dosage ?? '',
                'reminder_times' => $times,
                'interval_days' => $interval,
                'schedule_note' => $note,
                'start_date' => $prescription->prescribed_date,
                'end_date' => $medicine->duration_days
                    ? $prescription->prescribed_date->copy()->addDays($medicine->duration_days - 1) : null,
                'is_active' => $note === null,
                'channel' => 'in_app',
            ]);
        }
    }

    public function sendDue(): int
    {
        $count = 0;
        $at = now()->startOfMinute();
        MedicationReminder::active()->whereNotNull('prescription_medicine_id')->select('id')
            ->chunkById(100, function ($reminders) use ($at, &$count) {
                foreach ($reminders as $candidate) {
                    $count += DB::transaction(function () use ($candidate, $at) {
                        $reminder = MedicationReminder::with(['prescription', 'familyMember'])
                            ->lockForUpdate()->find($candidate->id);
                        if (!$reminder || !$reminder->isActive() || $reminder->schedule_note
                            || !in_array($at->format('H:i'), $reminder->reminder_times ?? [], true)
                            || ($reminder->last_notified_at && $reminder->last_notified_at->gte($at))
                            || (int) $reminder->start_date->diffInDays($at->copy()->startOfDay()) % $reminder->interval_days !== 0) {
                            return 0;
                        }
                        Notification::create([
                            'user_id' => $reminder->patient_user_id,
                            'type' => 'medication',
                            'title' => 'Medication reminder',
                            'body' => trim($reminder->medicine_name . ' ' . $reminder->dosage)
                                . ($reminder->familyMember ? ' for ' . $reminder->familyMember->full_name : '')
                                . ' — ' . $at->format('H:i'),
                            'data' => ['reminder_id' => $reminder->id, 'prescription_id' => $reminder->prescription_id,
                                'url' => route('patient.reminders.index')],
                            'channel' => 'in_app', 'is_read' => false,
                        ]);
                        $reminder->update(['last_notified_at' => $at]);
                        return 1;
                    });
                }
            });
        return $count;
    }
}
