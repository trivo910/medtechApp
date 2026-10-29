<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Prescription;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\NotificationService;


class AppointmentController extends Controller
{
    public function __construct(private WhatsAppService $whatsApp) {}

    // ── List ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $doctor = auth()->user();
        $tab    = $request->get('tab', 'upcoming');
        $date   = $request->get('date');

        $query = Appointment::where('doctor_user_id', $doctor->id)
            ->with(['patient.profile', 'familyMember']);

        if ($date) {
            $query->whereDate('slot_datetime', $date);
        } else {
            match ($tab) {
                'upcoming'  => $query->where('slot_datetime','>', now())->whereNotIn('status',['cancelled'])->orderBy('slot_datetime'),
                'today'     => $query->whereDate('slot_datetime', today())->orderBy('slot_datetime'),
                'past'      => $query->where('slot_datetime','<', now())->whereNotIn('status',['cancelled'])->orderByDesc('slot_datetime'),
                'cancelled' => $query->where('status','cancelled')->orderByDesc('slot_datetime'),
                default     => $query->orderBy('slot_datetime'),
            };
        }

        $appointments = $query->paginate(15)->withQueryString();

        $counts = [
            'today'    => Appointment::where('doctor_user_id',$doctor->id)->whereDate('slot_datetime',today())->whereNotIn('status',['cancelled'])->count(),
            'upcoming' => Appointment::where('doctor_user_id',$doctor->id)->where('slot_datetime','>',now())->whereNotIn('status',['cancelled'])->count(),
            'past'     => Appointment::where('doctor_user_id',$doctor->id)->where('slot_datetime','<',now())->whereNotIn('status',['cancelled'])->count(),
        ];

        return view('doctor.appointments.index', compact('appointments', 'tab', 'counts', 'date'));
    }

    // ── Calendar ─────────────────────────────────────────────────────────────

    public function calendar(Request $request)
    {
        $doctor = auth()->user();
        $month  = $request->get('month', today()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        $start = Carbon::create($year, $mon, 1)->startOfMonth();
        $end   = Carbon::create($year, $mon, 1)->endOfMonth();

        // All appointments in the month, grouped by date
        $appointments = Appointment::where('doctor_user_id', $doctor->id)
            ->whereBetween('slot_datetime', [$start, $end])
            ->whereNotIn('status', ['cancelled'])
            ->with(['patient.profile', 'familyMember'])
            ->orderBy('slot_datetime')
            ->get()
            ->groupBy(fn($a) => $a->slot_datetime->format('Y-m-d'));

        return view('doctor.appointments.calendar', compact('appointments', 'month', 'start', 'end'));
    }

    // ── Today ────────────────────────────────────────────────────────────────

    public function today()
    {
        $doctor = auth()->user();
        $appointments = Appointment::where('doctor_user_id', $doctor->id)
            ->whereDate('slot_datetime', today())
            ->whereNotIn('status', ['cancelled'])
            ->with(['patient.profile', 'familyMember'])
            ->orderBy('slot_datetime')
            ->get();

        return view('doctor.appointments.today', compact('appointments'));
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function show(Appointment $appointment)
    {
        $this->gate($appointment);
        $appointment->load(['patient.profile', 'doctor.profile', 'doctor.doctorProfile', 'familyMember']);
        $previousPrescriptions = Prescription::forAppointmentPatient($appointment)
            ->with('medicines')
            ->orderByDesc('prescribed_date')
            ->orderByDesc('id')
            ->paginate(5, ['*'], 'prescriptions_page')
            ->withQueryString()
            ->fragment('previous-prescriptions');

        return view('doctor.appointments.show', compact('appointment', 'previousPrescriptions'));
    }

    // ── Confirm ──────────────────────────────────────────────────────────────

    public function confirm(Request $request, Appointment $appointment)
    {
        $this->gate($appointment);
        $appointment->update(['status' => 'confirmed']);
        NotificationService::appointmentConfirmed($appointment->load('doctor.profile'));

        try {
            $this->whatsApp->sendAppointmentConfirmation(
                $appointment->load(['doctor.profile', 'patient.profile'])
            );
        } catch (\Exception) {}

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'status' => 'confirmed']);
        }

        return back()->with('success', 'Appointment confirmed. Patient notified via WhatsApp.');
    }

    // ── Complete ─────────────────────────────────────────────────────────────
    //
    // Behaviour:
    //  - If appointment is already paid, complete immediately (existing flow).
    //  - Otherwise return a "needs_payment" flag so the UI can show the popup.
    //  - The popup's two actions (collect cash / complete anyway) hit their own
    //    dedicated endpoints below.

    public function complete(Request $request, Appointment $appointment)
    {
        $this->gate($appointment);

        // Already paid → complete immediately, no popup.
        if ($appointment->payment_status === 'paid') {
            $appointment->update(['status' => 'completed']);
            NotificationService::appointmentCompleted($appointment->load('doctor.profile'));

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'status' => 'completed']);
            }
            return back()->with('success', 'Appointment marked as completed.');
        }

        // Not paid → tell the frontend to show the popup.
        if ($request->expectsJson()) {
            return response()->json([
                'success'           => false,
                'needs_payment'     => true,
                'appointment_id'    => $appointment->id,
                'fee'               => (float) $appointment->fee,
                'payment_status'    => $appointment->payment_status,
                'payment_preference'=> $appointment->payment_preference,
            ], 409);
        }

        // Non-AJAX (e.g. direct form submit): behave like "Complete Anyway" with a
        // forced note so the audit trail is never empty.
        $request->validate(['completion_note' => ['required', 'string', 'min:1', 'max:1000']]);
        $appointment->update([
            'status'          => 'completed',
            'completion_note' => $request->completion_note,
        ]);
        NotificationService::appointmentCompleted($appointment->load('doctor.profile'));
        return back()->with('success', 'Appointment marked as completed (no payment recorded).');
    }

    // Step 2: doctor collected cash → create a real Payment row, mark paid + complete.
    public function collectCash(Request $request, Appointment $appointment)
    {
        $this->gate($appointment);

        $data = $request->validate([
            'note'           => ['required', 'string', 'min:1', 'max:1000'],
            'reference'      => ['nullable', 'string', 'max:200'],
            'mark_completed' => ['sometimes', 'boolean'],
        ]);

        // Refuse if already paid — keeps the audit trail clean.
        if ($appointment->payment_status === 'paid') {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'error' => 'This appointment is already marked paid.'], 422)
                : back()->with('error', 'This appointment is already marked paid.');
        }

        $notes = $data['note'];
        if (!empty($data['reference'])) {
            $notes .= ' | Ref: ' . $data['reference'];
        }

        Payment::create([
            'user_id'        => $appointment->patient_user_id,
            'appointment_id' => $appointment->id,
            'payment_method' => 'cash',
            'status'         => 'paid',
            'amount'         => $appointment->fee,
            'currency'       => 'INR',
            'purpose'        => 'appointment',
            'notes'          => $notes,
            'paid_at'        => now(),
        ]);

        $appointment->update([
            'payment_status' => 'paid',
            'status'         => 'completed',
        ]);

        NotificationService::appointmentCompleted($appointment->load('doctor.profile'));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'status' => 'completed', 'payment_status' => 'paid']);
        }
        return back()->with('success', 'Cash payment recorded and appointment completed.');
    }

    // Step 2: "Complete Anyway" → complete, stay unpaid, no Payment record.
    public function completeAnyway(Request $request, Appointment $appointment)
    {
        $this->gate($appointment);

        $data = $request->validate([
            'note' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $appointment->update([
            'status'          => 'completed',
            'completion_note' => $data['note'],
            // payment_status deliberately left alone.
        ]);

        NotificationService::appointmentCompleted($appointment->load('doctor.profile'));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'status' => 'completed', 'payment_status' => $appointment->payment_status]);
        }
        return back()->with('success', 'Appointment completed (no payment recorded).');
    }

    // ── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(Request $request, Appointment $appointment)
    {
        $this->gate($appointment);
        $appointment->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->reason ?? 'Cancelled by doctor',
        ]);
        NotificationService::appointmentCancelled($appointment->fresh(), 'doctor');
      
        try {
            $this->whatsApp->sendAppointmentCancellation(
                $appointment->load(['doctor.profile', 'patient.profile'])
            );
        } catch (\Exception) {}

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Appointment cancelled.');
    }

    // ── Send reminder ────────────────────────────────────────────────────────

    public function sendReminder(Appointment $appointment)
    {
        $this->gate($appointment);
        try {
            $this->whatsApp->sendAppointmentReminder(
                $appointment->load(['doctor.profile', 'patient.profile'])
            );
            return back()->with('success', 'Reminder sent to patient.');
        } catch (\Exception $e) {
            return back()->with('error', 'Could not send reminder: ' . $e->getMessage());
        }
    }

    // ── Request payment (nudge patient to pay online) ─────────────────────────

    public function requestPayment(Request $request, Appointment $appointment)
    {
        $this->gate($appointment);

        if ($appointment->payment_status === 'paid') {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'error' => 'This appointment is already paid.'], 422)
                : back()->with('error', 'This appointment is already paid.');
        }

        if ($appointment->status === 'cancelled') {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'error' => 'Cannot request payment for a cancelled appointment.'], 422)
                : back()->with('error', 'Cannot request payment for a cancelled appointment.');
        }

        $appointment->update([
            'payment_requested_at' => now(),
            'payment_requested_by' => auth()->id(),
        ]);

        $appointment = $appointment->fresh(['patient.profile', 'doctor.profile']);

        // In-app notification to patient
        NotificationService::paymentRequested($appointment);

        // WhatsApp nudge (best-effort, never fail the request)
        try {
            $this->whatsApp->sendPaymentRequest($appointment);
        } catch (\Exception) {}

        if ($request->expectsJson()) {
            return response()->json([
                'success'              => true,
                'payment_requested_at' => $appointment->payment_requested_at?->toIso8601String(),
            ]);
        }
        return back()->with('success', 'Payment request sent to patient.');
    }

    // ── Manage availability slots ─────────────────────────────────────────────

    public function manageSlots()
    {
        $doctor  = auth()->user();
        $profile = $doctor->doctorProfile;
        $slots   = $profile?->available_slots ?? $this->defaultSlots();

        $blocked = $profile?->blocked_dates ?? [];
        $today   = today()->format('Y-m-d');
        $blocked = array_filter($blocked, fn($_, $d) => $d >= $today, ARRAY_FILTER_USE_BOTH);
        ksort($blocked);

        return view('doctor.appointments.slots', compact('slots', 'blocked'));
    }

    public function saveBlockedDate(Request $request)
    {
        $request->validate([
            'date'    => ['required', 'date', 'after_or_equal:today'],
            'type'    => ['required', 'in:full_day,partial'],
            'slots'   => ['required_if:type,partial', 'array'],
            'slots.*' => ['date_format:H:i'],
            'reason'  => ['nullable', 'string', 'max:200'],
        ]);

        $doctor = auth()->user();
        $date   = Carbon::parse($request->date);

        // Guard: no confirmed appointments on this date (full day)
        if ($request->type === 'full_day') {
            $count = Appointment::where('doctor_user_id', $doctor->id)
                ->whereDate('slot_datetime', $date)
                ->whereNotIn('status', ['cancelled'])
                ->count();

            if ($count > 0) {
                return response()->json([
                    'success' => false,
                    'error'   => "This date has {$count} confirmed appointment(s) on " .
                                 $date->format('D, d M Y') .
                                 '. Cancel or reschedule them before blocking the full day.',
                ], 422);
            }
        }

        // Guard: none of the chosen slots already have a booking
        if ($request->type === 'partial' && !empty($request->slots)) {
            $bookedTimes = Appointment::where('doctor_user_id', $doctor->id)
                ->whereDate('slot_datetime', $date)
                ->whereNotIn('status', ['cancelled'])
                ->pluck('slot_datetime')
                ->map(fn($dt) => $dt->format('H:i'))
                ->toArray();

            $conflicts = array_values(array_intersect($request->slots, $bookedTimes));

            if (!empty($conflicts)) {
                return response()->json([
                    'success'           => false,
                    'error'             => 'The following slots already have confirmed appointments and cannot be blocked: ' .
                                          implode(', ', $conflicts) . '. Cancel them first.',
                    'conflicting_slots' => $conflicts,
                ], 422);
            }
        }

        $profile = $doctor->doctorProfile;
        $blocked = $profile->blocked_dates ?? [];

        $entry = ['type' => $request->type, 'reason' => $request->reason ?? ''];
        if ($request->type === 'partial') {
            $entry['slots'] = $request->slots;
        }

        $blocked[$request->date] = $entry;
        ksort($blocked);

        $profile->update(['blocked_dates' => $blocked]);

        return response()->json(['success' => true, 'blocked' => $blocked]);
    }

    public function removeBlockedDate(Request $request)
    {
        $request->validate(['date' => ['required', 'date']]);

        $doctor  = auth()->user();
        $profile = $doctor->doctorProfile;
        $blocked = $profile->blocked_dates ?? [];

        unset($blocked[$request->date]);

        $profile->update(['blocked_dates' => $blocked]);

        return response()->json(['success' => true]);
    }

    public function saveSlots(Request $request)
    {
        $request->validate([
            'slots'                     => ['required', 'array'],
            'slots.*.enabled'           => ['sometimes', 'boolean'],
            'slots.*.blocks'            => ['sometimes', 'array'],
            'slots.*.blocks.*.start'    => ['sometimes', 'date_format:H:i'],
            'slots.*.blocks.*.end'      => ['sometimes', 'date_format:H:i'],
        ]);

        $doctor  = auth()->user();
        $profile = $doctor->doctorProfile;

        $days  = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $saved = [];

        foreach ($days as $day) {
            $dayData = $request->input("slots.{$day}", []);
            $enabled = !empty($dayData['enabled']);
            $blocks  = [];

            if ($enabled && !empty($dayData['blocks'])) {
                foreach ($dayData['blocks'] as $block) {
                    if (!empty($block['start']) && !empty($block['end']) && $block['start'] < $block['end']) {
                        $blocks[] = ['start' => $block['start'], 'end' => $block['end']];
                    }
                }
            }

            $saved[$day] = $blocks;
        }

        $profile->update(['available_slots' => $saved]);

        return back()->with('success', 'Availability updated successfully.');
    }

    // ── AJAX: available slots (for doctor's own view) ─────────────────────────

    public function availableSlots(Request $request)
    {
        $request->validate(['date' => ['required', 'date']]);
        $doctor  = auth()->user();
        $profile = $doctor->doctorProfile;
        $date    = Carbon::parse($request->date);
        $dayName = strtolower($date->format('l'));

        $slots    = $profile->available_slots ?? [];
        $daySlots = $slots[$dayName] ?? [];
        $duration = config('medtech.appointment.default_duration', 15);

        $allTimes = [];
        foreach ($daySlots as $block) {
            $start  = Carbon::parse($date->format('Y-m-d') . ' ' . $block['start']);
            $end    = Carbon::parse($date->format('Y-m-d') . ' ' . $block['end']);
            $cursor = $start->copy();
            while ($cursor->copy()->addMinutes($duration)->lte($end)) {
                $allTimes[] = $cursor->format('H:i');
                $cursor->addMinutes($duration);
            }
        }

        $booked = Appointment::where('doctor_user_id', $doctor->id)
            ->whereDate('slot_datetime', $date)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('slot_datetime')
            ->map(fn($dt) => $dt->format('H:i'))
            ->toArray();

        return response()->json([
            'all'    => $allTimes,
            'booked' => $booked,
            'date'   => $request->date,
        ]);
    }

    private function gate(Appointment $a): void
    {
        if ($a->doctor_user_id !== auth()->id()) abort(403);
    }

    private function defaultSlots(): array
    {
        $working = ['start' => '09:00', 'end' => '13:00'];
        $slots   = [];
        foreach (['monday','tuesday','wednesday','thursday','friday'] as $d) {
            $slots[$d] = [$working];
        }
        foreach (['saturday','sunday'] as $d) {
            $slots[$d] = [];
        }
        return $slots;
    }
}
