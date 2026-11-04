<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for money math on a visit.
 *
 * A bill is the invoice (bill_items = payment heads); a payment is money
 * actually received. See docs/BILLING.md.
 */
class BillService
{
    /** Find or create the bill for a visit, pre-filled with the OPD fee line. */
    public function forAppointment(Appointment $appointment): Bill
    {
        return DB::transaction(function () use ($appointment) {
            $bill = Bill::where('appointment_id', $appointment->id)
                ->lockForUpdate()
                ->first();

            if ($bill) {
                return $bill->load('items', 'payments');
            }

            $bill = Bill::create([
                'appointment_id'  => $appointment->id,
                'prescription_id' => null,
                'doctor_user_id'  => $appointment->doctor_user_id,
                'patient_user_id' => $appointment->patient_user_id,
                'clinic_id'       => $appointment->clinic_id,
                'subtotal'        => 0,
                'discount'        => 0,
                'total'           => 0,
                'amount_paid'     => 0,
                'balance'         => 0,
                'status'          => 'draft',
            ]);

            $fee = (float) $appointment->fee;

            $this->syncItems($bill, [[
                'head'       => 'opd_fee',
                'label'      => 'OPD / Consultation',
                'quantity'   => 1,
                'unit_price' => $fee,
            ]]);

            return $bill->fresh(['items', 'payments']);
        });
    }

    /** Replace all lines on a bill and recompute totals. */
    public function syncItems(Bill $bill, array $items): Bill
    {
        $clean = $this->normalizeItems($items);

        $bill->items()->delete();

        foreach ($clean as $item) {
            $bill->items()->create($item);
        }

        return $this->recalculate($bill);
    }

    /**
     * Record what is due, take a payment, and complete the visit.
     *
     * $data: items[], discount_type (amount|percent), discount_value,
     *        payment_method, amount_received, reference, note
     */
    public function collect(Appointment $appointment, array $data, ?Prescription $prescription = null): Bill
    {
        return DB::transaction(function () use ($appointment, $data, $prescription) {
            // Lock the visit so two clicks cannot both take money.
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            $bill = Bill::where('appointment_id', $appointment->id)->lockForUpdate()->first();

            if (!$bill) {
                $bill = Bill::create([
                    'appointment_id'  => $appointment->id,
                    'prescription_id' => $prescription?->id,
                    'doctor_user_id'  => $appointment->doctor_user_id,
                    'patient_user_id' => $appointment->patient_user_id,
                    'clinic_id'       => $appointment->clinic_id,
                    'status'          => 'draft',
                ]);
            }

            if ($prescription && !$bill->prescription_id) {
                $bill->prescription_id = $prescription->id;
            }

            $this->syncItems($bill, $data['items'] ?? []);

            $subtotal = (float) $bill->items()->sum('amount');
            $discount = $this->discountAmount(
                $subtotal,
                (string) ($data['discount_type'] ?? 'amount'),
                (float) ($data['discount_value'] ?? 0)
            );

            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Discount cannot be more than the subtotal.',
                ]);
            }

            $bill->discount = $discount;
            $bill->notes    = $this->buildNotes($data);
            $bill->save();

            $this->recalculate($bill);

            $received = round((float) ($data['amount_received'] ?? 0), 2);

            if ($received > (float) $bill->balance + 0.001) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Amount received is more than the outstanding balance of ₹'
                        . number_format((float) $bill->balance, 2) . '.',
                ]);
            }

            $payment = null;
            if ($received > 0) {
                $payment = Payment::create([
                    'user_id'        => $appointment->patient_user_id,
                    'appointment_id' => $appointment->id,
                    'bill_id'        => $bill->id,
                    'payment_method' => $this->paymentMethod($data['payment_method'] ?? 'cash'),
                    'amount'         => $received,
                    'currency'       => 'INR',
                    'status'         => 'paid',
                    'purpose'        => 'appointment',
                    'notes'          => 'Bill ' . $bill->bill_number . $this->referenceSuffix($data),
                    'paid_at'        => now(),
                ]);

                $this->recalculate($bill->fresh());
            }

            $bill->refresh();

            if ((float) $bill->balance > 0 && (float) $bill->amount_paid <= 0) {
                $bill->status = 'unpaid';
                $bill->save();
            }

            // A zero-value visit completed through "Collect" is settled.
            if ((float) $bill->total <= 0) {
                $bill->status  = 'paid';
                $bill->paid_at = $bill->paid_at ?? now();
                $bill->save();
            }

            // Complete the visit.
            $appointment->status = 'completed';
            if ((float) $bill->balance <= 0) {
                $appointment->payment_status = 'paid';
            }
            $appointment->save();

            NotificationService::appointmentCompleted($appointment->load('doctor.profile'));

            if ($payment) {
                NotificationService::paymentReceived(
                    $payment->load('appointment.doctor.profile')
                );
            }

            return $bill->fresh(['items', 'payments']);
        });
    }

    /** Complete the visit without charging; keeps the lines for the record. */
    public function waive(Appointment $appointment, array $data): Bill
    {
        return DB::transaction(function () use ($appointment, $data) {
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            $bill = Bill::where('appointment_id', $appointment->id)->lockForUpdate()->first()
                ?? Bill::create([
                    'appointment_id'  => $appointment->id,
                    'doctor_user_id'  => $appointment->doctor_user_id,
                    'patient_user_id' => $appointment->patient_user_id,
                    'clinic_id'       => $appointment->clinic_id,
                    'status'          => 'waived',
                ]);

            if (!empty($data['items'])) {
                $this->syncItems($bill, $data['items']);
            }

            $bill->discount = 0;
            $bill->notes    = $this->buildNotes($data);
            $bill->status   = 'waived';
            $bill->save();

            $this->recalculate($bill);
            $bill->status = 'waived';
            $bill->save();

            $appointment->status          = 'completed';
            $appointment->completion_note = $data['note'];
            $appointment->save();

            NotificationService::appointmentCompleted($appointment->load('doctor.profile'));

            return $bill->fresh(['items', 'payments']);
        });
    }

    /** Recompute subtotal / discount / total / paid / balance / status. */
    public function recalculate(Bill $bill): Bill
    {
        $subtotal = (float) $bill->items()->sum('amount');
        $paid     = (float) $bill->payments()->where('status', 'paid')->sum('amount');
        $total    = max(0, round($subtotal - (float) $bill->discount, 2));
        $balance  = max(0, round($total - $paid, 2));

        $bill->subtotal    = $subtotal;
        $bill->total       = $total;
        $bill->amount_paid = $paid;
        $bill->balance     = $balance;

        if ($bill->status !== 'waived') {
            if ($total <= 0) {
                // An empty/free bill stays editable until the doctor either
                // collects (marking it paid) or completes it without payment.
                $bill->status = $paid > 0 ? 'paid' : 'draft';
            } elseif ($balance <= 0) {
                $bill->status = 'paid';
            } elseif ($paid > 0) {
                $bill->status = 'partial';
            } else {
                $bill->status = 'draft';
            }
        }

        if ($bill->status === 'paid' && !$bill->paid_at) {
            $bill->paid_at = now();
        }

        $bill->save();

        return $bill;
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    private function normalizeItems(array $items): array
    {
        $allowed = array_keys(config('medtech.billing_heads', []));
        $clean   = [];

        foreach (array_values($items) as $i => $item) {
            $head = (string) ($item['head'] ?? 'other');
            if (!in_array($head, $allowed, true)) {
                $head = 'other';
            }

            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                $label = (string) config(
                    'medtech.billing_heads.' . $head . '.label',
                    ucfirst(str_replace('_', ' ', $head))
                );
            }

            $quantity = (int) ($item['quantity'] ?? 1);
            $quantity = max(1, min(999, $quantity));

            $unit = round((float) ($item['unit_price'] ?? 0), 2);
            $unit = max(0, min(10000000, $unit));

            $clean[] = [
                'head'       => $head,
                'label'      => mb_substr($label, 0, 200),
                'quantity'   => $quantity,
                'unit_price' => $unit,
                'amount'     => round($quantity * $unit, 2),
                'sort_order' => $i,
            ];
        }

        return $clean;
    }

    private function discountAmount(float $subtotal, string $type, float $value): float
    {
        $value = max(0, $value);

        $discount = $type === 'percent'
            ? $subtotal * min(100, $value) / 100
            : $value;

        return round(min($subtotal, $discount), 2);
    }

    private function paymentMethod(string $method): string
    {
        return in_array($method, ['cash', 'upi_qr', 'razorpay', 'other'], true)
            ? $method
            : 'cash';
    }

    private function buildNotes(array $data): ?string
    {
        $parts = array_filter([
            trim((string) ($data['note'] ?? '')),
            !empty($data['reference']) ? 'Ref: ' . trim((string) $data['reference']) : null,
        ]);

        return $parts ? implode(' | ', $parts) : null;
    }

    private function referenceSuffix(array $data): string
    {
        $suffix = '';
        if (!empty($data['reference'])) {
            $suffix .= ' | Ref: ' . trim((string) $data['reference']);
        }
        return $suffix;
    }
}
