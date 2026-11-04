<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $bill->bill_number }}</title>
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
        .badge-waived { background:#ede9fe; color:#5b21b6; }
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
        @media print {
            body { background:#fff; padding:0; }
            .sheet { box-shadow:none; border-radius:0; padding:10mm 12mm; max-width:none; }
            .actions { display:none; }
        }
    </style>
</head>
<body>
@php
    $doctor  = $bill->doctor;
    $profile = $doctor?->profile;
    $clinic  = $doctor?->doctorProfile;
    $patient = $bill->patient;
    $pProfile = $patient?->profile;
    $paidPayments = $bill->payments->where('status', 'paid');
    $badge = $bill->status === 'paid' ? 'badge-paid' : ($bill->status === 'waived' ? 'badge-waived' : 'badge-open');
@endphp

<div class="actions">
    <a href="{{ url()->previous() }}" class="btn">&larr; Back</a>
    <button type="button" onclick="window.print()" class="btn btn-primary">Print / Save PDF</button>
</div>

<div class="sheet">
    <div class="top">
        <div>
            <div class="clinic">{{ $clinic?->clinic_name ?? 'Medical Clinic' }}</div>
            <div class="muted">
                {{ $clinic?->clinic_address }}{{ $clinic?->clinic_city ? ', ' . $clinic->clinic_city : '' }}
            </div>
        </div>
        <div style="text-align:right">
            <div class="badge {{ $badge }}">{{ $bill->status }}</div>
            <div class="muted" style="margin-top:8px">
                <strong style="color:var(--ink);font-size:13px">{{ $bill->bill_number }}</strong><br>
                {{ $bill->created_at?->format('d M Y, h:i A') }}
            </div>
        </div>
    </div>

    <div class="grid">
        <div>
            <h1>Billed to</h1>
            <div style="font-weight:600;font-size:14px">{{ $pProfile?->full_name ?? 'Patient' }}</div>
            <div class="muted">
                {{ $patient?->country_code }} {{ $patient?->mobile_number }}<br>
                @if($bill->appointment) Visit #{{ $bill->appointment->appointment_number }} · {{ $bill->appointment->slot_datetime?->format('d M Y') }} @endif
            </div>
        </div>
        <div style="text-align:right">
            <h1 style="margin-top:0">Doctor</h1>
            <div style="font-weight:600;font-size:14px">Dr. {{ $profile?->full_name ?? '—' }}</div>
            <div class="muted">{{ $clinic?->qualification }}{{ $clinic?->specialization ? ' · ' . $clinic->specialization : '' }}</div>
        </div>
    </div>

    <h1>Charges</h1>
    <table>
        <thead>
            <tr>
                <th>Payment head</th>
                <th class="num">Qty</th>
                <th class="num">Rate</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
        @forelse($bill->items as $item)
            <tr>
                <td>
                    {{ $item->label }}
                    <div class="head">{{ $item->head_label }}</div>
                </td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">₹{{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="num">₹{{ number_format((float) $item->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No charges recorded.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span>₹{{ number_format((float) $bill->subtotal, 2) }}</span></div>
        @if((float) $bill->discount > 0)
        <div><span>Discount</span><span>−₹{{ number_format((float) $bill->discount, 2) }}</span></div>
        @endif
        <div class="grand"><span>Total</span><span>₹{{ number_format((float) $bill->total, 2) }}</span></div>
        <div><span>Paid</span><span>₹{{ number_format((float) $bill->amount_paid, 2) }}</span></div>
        @if((float) $bill->balance > 0)
        <div style="color:#9a3412"><span>Balance</span><span>₹{{ number_format((float) $bill->balance, 2) }}</span></div>
        @endif
    </div>

    @if($paidPayments->isNotEmpty())
    <h1>Payments</h1>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Method</th>
                <th>Reference</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
        @foreach($paidPayments as $pay)
            <tr>
                <td>{{ $pay->paid_at?->format('d M Y, h:i A') ?? $pay->created_at?->format('d M Y') }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $pay->payment_method)) }}</td>
                <td class="muted">{{ $pay->notes ?: '—' }}</td>
                <td class="num">₹{{ number_format((float) $pay->amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif

    @if($bill->status === 'waived')
        <div style="margin-top:20px;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:12px 14px;font-size:12px;color:#5b21b6">
            <strong>Completed without payment.</strong>
            {{ $bill->appointment?->completion_note ?? $bill->notes }}
        </div>
    @endif

    <div class="footer">
        <div class="muted">
            This is a computer generated receipt.<br>
            {{ $bill->bill_number }}
        </div>
        <div style="text-align:right" class="muted">
            For {{ $clinic?->clinic_name ?? 'the clinic' }}<br>
            <span style="color:var(--ink);font-weight:600">Dr. {{ $profile?->full_name }}</span>
        </div>
    </div>
</div>
</body>
</html>
