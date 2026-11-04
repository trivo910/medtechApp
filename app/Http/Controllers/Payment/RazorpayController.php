<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Appointment;
use App\Services\RazorpayService;
use Illuminate\Http\Request;
use App\Services\NotificationService;

class RazorpayController extends Controller
{
    public function __construct(private RazorpayService $razorpay) {}

    // ── Patient: payment history ──────────────────────────────────────────────

    public function patientIndex()
    {
        $patient = auth()->user();

        $payments = Payment::where('user_id', $patient->id)
            ->with([
                'appointment.doctor.profile',
                'appointment.doctor.doctorProfile',
                'appointment.bill.items',
                'bill.items',
            ])
            ->latest()
            ->paginate(12);

        // Stats
        $totalPaid    = Payment::where('user_id', $patient->id)->paid()->sum('amount');
        $pendingCount = Payment::where('user_id', $patient->id)->pending()->count();

        // Unpaid appointments (fee set, payment_status != paid). Bills already
        // waived need no payment from the patient, so those visits are excluded.
        $unpaidApts = Appointment::where('patient_user_id', $patient->id)
            ->whereNotNull('fee')
            ->where('fee', '>', 0)
            ->where('payment_status', '!=', 'paid')
            ->whereIn('status', ['booked', 'confirmed'])
            ->with(['doctor.profile', 'doctor.doctorProfile', 'bill.items'])
            ->latest('slot_datetime')
            ->get()
            ->filter(fn (Appointment $apt) => !in_array($apt->bill?->status, ['waived', 'paid'], true))
            ->values();

        return view('patient.payments.index', compact(
            'payments', 'totalPaid', 'pendingCount', 'unpaidApts'
        ));
    }

    // ── Create Razorpay order (AJAX) ──────────────────────────────────────────

    public function createOrder(Request $request)
    {
        $request->validate([
            'appointment_id' => ['required', 'exists:appointments,id'],
        ]);

        $appointment = Appointment::where('id', $request->appointment_id)
            ->where('patient_user_id', auth()->id())
            ->firstOrFail();

        if ($appointment->payment_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Already paid.'], 422);
        }

        if (!$appointment->fee || $appointment->fee <= 0) {
            return response()->json(['success' => false, 'message' => 'No fee set for this appointment.'], 422);
        }

        $result = $this->razorpay->createAppointmentOrder($appointment);

        if (!($result['success'] ?? false)) {
            return response()->json(['success' => false, 'message' => $result['message'] ?? 'Could not create order.'], 500);
        }

        return response()->json([
            'success'         => true,
            'order_id'        => $result['order_id'],
            'amount'          => $appointment->fee * 100, // paise
            'currency'        => 'INR',
            'name'            => 'Naumah Clinic',
            'description'     => 'Appointment with Dr. ' . $appointment->doctor?->profile?->full_name,
            'prefill_name'    => auth()->user()->profile?->full_name,
            'prefill_mobile'  => auth()->user()->country_code . auth()->user()->mobile_number,
            'key_id'          => config('services.razorpay.key_id'),
            'appointment_id'  => $appointment->id,
        ]);
    }

    // ── Verify payment after Razorpay callback ────────────────────────────────

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id'   => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

        $result = $this->razorpay->verifyPayment(
            razorpayOrderId:   $request->razorpay_order_id,
            razorpayPaymentId: $request->razorpay_payment_id,
            razorpaySignature: $request->razorpay_signature,
        );

        if ($result['success'])
        NotificationService::paymentReceived($result['payment']->load('appointment.doctor.profile'));

        if (!$result['success']) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $result['message']], 422);
            }
            return back()->withErrors(['payment' => $result['message']]);
        }

        $payment = $result['payment'];

        if ($request->expectsJson()) {
            return response()->json([
                'success'    => true,
                'payment_id' => $payment->id,
                'receipt_url'=> route('patient.payments.receipt', $payment),
            ]);
        }

        return redirect()
            ->route('patient.payments.receipt', $payment)
            ->with('success', 'Payment successful! ₹' . number_format($payment->amount, 2) . ' paid.');
    }

    // ── Payment receipt ───────────────────────────────────────────────────────

    public function receipt(Payment $payment)
    {
        if ($payment->user_id !== auth()->id()) abort(403);

        $payment->load([
            'user.profile',
            'appointment.doctor.profile',
            'appointment.doctor.doctorProfile',
            'appointment.patient.profile',
            'bill',
        ]);

        return view('patient.payments.receipt', compact('payment'));
    }
}