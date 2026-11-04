<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $payment->razorpay_payment_id ?? 'PAY-'.str_pad($payment->id, 8, '0', STR_PAD_LEFT) }}</title>
    <style>
        :root { --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb; --leaf:#0f766e; }
        * { box-sizing:border-box; }
        body { margin:0; padding:32px 16px; background:#f3f4f6; font-family:'Segoe UI', Arial, sans-serif; color:var(--ink); }
        .sheet { max-width:760px; margin:0 auto; background:#fff; border-radius:12px; padding:36px 40px; box-shadow:0 10px 40px rgba(0,0,0,.08); }
        .top { display:flex; justify-content:space-between; gap:24px; border-bottom:2px solid var(--line); padding-bottom:20px; }
        .clinic { font-size:22px; font-weight:700; letter-spacing:.01em; }
        .muted { color:var(--muted); font-size:12px; line-height:1.55; }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
        .badge-paid { background:#dcfce7; color:#166534; }
        .badge-open { background:#fff7ed; color:#9a3412; }
        h1 { font-size:15px; text-transform:uppercase; letter-spacing:.12em; color:var(--muted); margin:26px 0 14px; }
        table { width:100%; border-collapse:collapse; }
        th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); border-bottom:1px solid var(--line); padding:8px 6px; }
        td { padding:10px 6px; border-bottom:1px solid var(--line); font-size:13px; vertical-align:top; }
        td.num, th.num { text-align:right; }
        .head { font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin-top:2px; }
        .totals { margin-left:auto; width:280px; margin-top:16px; }
        .totals div { display:flex; justify-content:space-between; padding:5px 0; font-size:13px; }
        .totals .grand { border-top:2px solid var(--ink); margin-top:6px; padding-top:10px; font-size:16px; font-weight:700; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:6px; }
        .footer { margin-top:36px; border-top:1px dashed var(--line); padding-top:14px; display:flex; justify-content:space-between; gap:20px; }
        .actions { max-width:760px; margin:0 auto 16px; display:flex; justify-content:space-between; gap:10px; }
        .btn { padding:9px 16px; border-radius:8px; border:1px solid var(--line); background:#fff; color:var(--ink); font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; }
        .btn-primary { background:var(--leaf); color:#fff; border-color:var(--leaf); }
        @media (max-width:560px) {
            .grid { grid-template-columns:1fr; }
            .sheet { padding:24px 18px; }
            .top, .footer { flex-direction:column; gap:10px; }
        }
        @media print {
            body { background:#fff; padding:0; }
            .sheet { box-shadow:none; border-radius:0; padding:10mm 12mm; max-width:none; }
            .actions { display:none; }
        }
    </style>
</head>
<body>
@php
    $apt        = $payment->appointment;
    $doctor     = $apt?->doctor;
    $profile    = $doctor?->profile;
    $clinic     = $doctor?->doctorProfile;
    $payer      = $payment->user;
    $pProfile   = $payer?->profile;
    $receiptNo  = $payment->razorpay_payment_id ?? 'PAY-'.str_pad($payment->id, 8, '0', STR_PAD_LEFT);
    $paidAt     = $payment->paid_at ?? $payment->created_at;
    $bill       = $payment->bill;
    $badge      = $payment->isPaid() ? 'badge-paid' : 'badge-open';

    /* Charges: bill lines when the payment is attached to a bill, otherwise a
       single consultation line — patient-side payments prepay the visit fee. */
    if ($bill && $bill->items->isNotEmpty()) {
        $charges = $bill->items->map(fn ($i) => [
            'label' => $i->label,
            'head'  => $i->head_label,
            'qty'   => $i->quantity,
            'rate'  => (float) $i->unit_price,
            'amt'   => (float) $i->amount,
        ])->values()->all();
    } else {
        $purpose = $payment->purpose === 'appointment'
            ? ($apt?->appointment_number ? 'Visit ' . $apt->appointment_number : 'Consultation / visit charges')
            : ucfirst(str_replace('_', ' ', $payment->purpose ?? 'payment'));
        $charges = [[
            'label' => $purpose,
            'head'  => 'opd fee',
            'qty'   => 1,
            'rate'  => (float) $payment->amount,
            'amt'   => (float) $payment->amount,
        ]];
        $bill = null;
    }
@endphp

<div class="actions">
    <a href="{{ route('patient.payments.index') }}" class="btn">&larr; Back to Payments</a>
    <button type="button" onclick="window.print()" class="btn btn-primary">Print / Save PDF</button>
</div>

<div class="sheet">
    <div class="top">
        <div>
            <div class="clinic">{{ $clinic?->clinic_name ?? 'Naumah Clinic' }}</div>
            <div class="muted">
                {{ $clinic?->clinic_address }}{{ $clinic?->clinic_city ? ', ' . $clinic->clinic_city : '' }}
            </div>
        </div>
        <div style="text-align:right">
            <div class="badge {{ $badge }}">{{ $payment->isPaid() ? 'Paid' : ucfirst($payment->status) }}</div>
            <div class="muted" style="margin-top:8px">
                <strong style="color:var(--ink);font-size:13px">{{ $receiptNo }}</strong><br>
                {{ $paidAt?->format('d M Y, h:i A') }}
            </div>
        </div>
    </div>

    <div class="grid">
        <div>
            <h1>Received from</h1>
            <div style="font-weight:600;font-size:14px">{{ $pProfile?->full_name ?? ($apt?->patient?->profile?->full_name ?? 'Patient') }}</div>
            <div class="muted">
                {{ $payer?->country_code }} {{ $payer?->mobile_number }}<br>
                @if($apt) Visit #{{ $apt->appointment_number }} · {{ $apt->slot_datetime?->format('d M Y') }} @endif
            </div>
        </div>
        @if($doctor)
        <div style="text-align:right">
            <h1 style="margin-top:0">Doctor</h1>
            <div style="font-weight:600;font-size:14px">Dr. {{ $profile?->full_name ?? '—' }}</div>
            <div class="muted">{{ $clinic?->qualification }}{{ $clinic?->specialization ? ' · ' . $clinic->specialization : '' }}</div>
        </div>
        @endif
    </div>

    <h1>Charges</h1>
    <table>
        <thead>
            <tr>
                <th>Particulars</th>
                <th class="num">Qty</th>
                <th class="num">Rate</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
        @foreach($charges as $item)
            <tr>
                <td>
                    {{ $item['label'] }}
                    <div class="head">{{ $item['head'] }}</div>
                </td>
                <td class="num">{{ $item['qty'] }}</td>
                <td class="num">₹{{ number_format($item['rate'], 2) }}</td>
                <td class="num">₹{{ number_format($item['amt'], 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span>₹{{ number_format(array_sum(array_column($charges, 'amt')), 2) }}</span></div>
        @if($bill && (float) $bill->discount > 0)
        <div><span>Discount on bill</span><span>−₹{{ number_format((float) $bill->discount, 2) }}</span></div>
        @endif
        <div class="grand"><span>Total paid</span><span>₹{{ number_format((float) $payment->amount, 2) }}</span></div>
        @if($bill)
        <div><span>Bill total</span><span>₹{{ number_format((float) $bill->total, 2) }}</span></div>
        @if((float) $bill->balance > 0)
        <div style="color:#9a3412"><span>Balance on bill</span><span>₹{{ number_format((float) $bill->balance, 2) }}</span></div>
        @endif
        @endif
    </div>

    <h1>Payment</h1>
    <table>
        <tbody>
            <tr>
                <td>{{ $paidAt?->format('d M Y, h:i A') }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                <td class="muted">{{ $payment->razorpay_payment_id ?? $payment->notes ?? '—' }}</td>
                <td class="num">₹{{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>
    @if(!$payment->isPaid())
        <div style="margin-top:20px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px 14px;font-size:12px;color:#9a3412">
            <strong>Payment not completed.</strong> This receipt will confirm once the payment settles.
        </div>
    @endif

    <div class="footer">
        <div class="muted">
            This is a computer generated receipt.<br>
            {{ $receiptNo }}
        </div>
        @if($clinic?->clinic_name)
        <div style="text-align:right" class="muted">
            For {{ $clinic->clinic_name }}<br>
            <span style="color:var(--ink);font-weight:600">Dr. {{ $profile?->full_name }}</span>
        </div>
        @endif
    </div>
</div>
</body>
</html>
