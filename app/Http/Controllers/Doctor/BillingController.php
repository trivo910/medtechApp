<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Prescription;
use App\Services\BillService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private BillService $bills) {}

    // ── Collect payment + complete visit ─────────────────────────────────────

    public function collect(Request $request, Appointment $appointment)
    {
        $this->gate($appointment);

        $data = $request->validate([
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.head'          => ['required', 'string', 'max:30'],
            'items.*.label'         => ['nullable', 'string', 'max:200'],
            'items.*.quantity'      => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price'    => ['required', 'numeric', 'min:0', 'max:10000000'],
            'discount_type'         => ['nullable', 'in:amount,percent'],
            'discount_value'        => ['nullable', 'numeric', 'min:0'],
            'payment_method'        => ['required', 'in:cash,upi_qr,razorpay,other'],
            'amount_received'       => ['required', 'numeric', 'min:0'],
            'reference'             => ['nullable', 'string', 'max:200'],
            'note'                  => ['nullable', 'string', 'max:1000'],
        ]);

        $prescription = null;
        if ($request->filled('prescription_id')) {
            $prescription = Prescription::where('id', $request->input('prescription_id'))
                ->where('doctor_user_id', auth()->id())
                ->first();
        }

        $bill = $this->bills->collect($appointment, $data, $prescription);

        if ($request->expectsJson()) {
            return response()->json([
                'success'     => true,
                'bill_id'     => $bill->id,
                'bill_number' => $bill->bill_number,
                'status'      => $bill->status,
                'total'       => (float) $bill->total,
                'amount_paid' => (float) $bill->amount_paid,
                'balance'     => (float) $bill->balance,
                'receipt_url' => route('doctor.bills.receipt', $bill),
            ]);
        }

        return back()->with('success', 'Payment recorded — visit completed.');
    }

    // ── Complete without payment ─────────────────────────────────────────────

    public function waive(Request $request, Appointment $appointment)
    {
        $this->gate($appointment);

        $data = $request->validate([
            'note'           => ['required', 'string', 'min:1', 'max:1000'],
            'items'          => ['nullable', 'array'],
            'items.*.head'   => ['required_with:items', 'string', 'max:30'],
            'items.*.label'  => ['nullable', 'string', 'max:200'],
            'items.*.quantity'   => ['nullable', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        $bill = $this->bills->waive($appointment, $data);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status'  => $bill->status,
                'total'   => (float) $bill->total,
            ]);
        }

        return back()->with('success', 'Visit completed without payment.');
    }

    // ── Printable receipt ────────────────────────────────────────────────────

    public function receipt(Request $request, Bill $bill)
    {
        if ((int) $bill->doctor_user_id !== (int) auth()->id()) {
            abort(403);
        }

        $bill->load(['items', 'payments', 'doctor.profile', 'doctor.doctorProfile', 'patient.profile', 'appointment']);

        return view('doctor.bills.receipt', compact('bill'));
    }

    // ── Link a standalone prescription to a visit ────────────────────────────

    public function startVisitForPrescription(Request $request, Prescription $prescription)
    {
        if ((int) $prescription->doctor_user_id !== (int) auth()->id()) {
            abort(403);
        }

        if ($prescription->appointment_id) {
            return redirect()->to(route('doctor.prescriptions.show', $prescription) . '#billing');
        }

        $appointment = \Illuminate\Support\Facades\DB::transaction(function () use ($prescription) {
            \App\Models\User::whereKey($prescription->patient_user_id)->lockForUpdate()->first();

            $open = Appointment::openVisitToday(
                auth()->id(),
                $prescription->patient_user_id,
                $prescription->family_member_id
            )->first();

            if ($open) {
                return $open;
            }

            $doctor  = auth()->user();
            $profile = $doctor->doctorProfile;

            return Appointment::create([
                'doctor_user_id'     => $doctor->id,
                'patient_user_id'    => $prescription->patient_user_id,
                'family_member_id'   => $prescription->family_member_id,
                'slot_datetime'      => now(),
                'duration_minutes'   => config('medtech.appointment.default_duration', 15),
                'type'               => 'in_person',
                'status'             => 'confirmed',
                'reason'             => 'Billing for ' . $prescription->prescription_number,
                'fee'                => $profile->consultation_fee ?? 0,
                'payment_status'     => 'pending',
                'payment_preference' => 'cash',
            ]);
        });

        $prescription->update(['appointment_id' => $appointment->id]);

        return redirect()
            ->to(route('doctor.prescriptions.show', $prescription) . '#billing')
            ->with('success', 'Visit opened — add the payment heads below.');
    }

    // ── Guard ────────────────────────────────────────────────────────────────

    private function gate(Appointment $appointment): void
    {
        if ((int) $appointment->doctor_user_id !== (int) auth()->id()) {
            abort(403);
        }
    }
}
