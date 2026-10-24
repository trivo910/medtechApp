@extends('layouts.patient')
@section('title', 'My Appointments')
@section('page-title', 'My Appointments')

@section('content')
<div class="fade-in">

{{-- Tabs --}}
<div class="apt-tabs-row" style="display:flex;gap:2px;border-bottom:2px solid var(--warm-bd);margin-bottom:20px">
    <div class="apt-tabs-scroll" style="display:flex;gap:2px;flex-wrap:wrap;min-width:0">
    @foreach(['upcoming' => "Upcoming ({$upcomingCount})", 'unpaid' => "Unpaid ({$unpaidCount})", 'past' => "Past ({$pastCount})", 'cancelled' => "Cancelled ({$cancelledCount})"] as $t => $lbl)
    <a href="{{ route('patient.appointments.index', ['tab' => $t]) }}"
       style="padding:10px 18px;font-size:.875rem;font-weight:500;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;white-space:nowrap;
              {{ $tab === $t ? 'color:var(--plum);border-bottom-color:var(--plum);font-weight:600' : 'color:var(--txt-lt)' }}"
       onmouseover="if('{{$tab}}' !== '{{$t}}') this.style.color='var(--txt)'" onmouseout="if('{{$tab}}' !== '{{$t}}') this.style.color='var(--txt-lt)'">
        {{ $lbl }}
    </a>
    @endforeach
    </div>
    <a href="{{ route('patient.appointments.book') }}"
       style="margin-left:auto;display:flex;align-items:center;gap:6px;padding:8px 16px;background:var(--plum);color:#fff;border-radius:9px;font-size:.8rem;font-weight:600;text-decoration:none;align-self:center;margin-bottom:4px;flex-shrink:0">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Book New
    </a>
</div>
@push('styles')
<style>
.apt-tabs-scroll { flex: 1 1 auto; }
@media (max-width: 560px) {
    .apt-tabs-row    { flex-wrap: wrap; }
    .apt-tabs-scroll { flex: 1 1 100%; display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .apt-tabs-scroll a { padding: 10px 8px !important; text-align: center; white-space: normal !important; }
    .apt-card-body { padding: 14px 16px !important; gap: 10px !important; }
    .apt-main-info { min-width: 0 !important; flex: 1 1 calc(100% - 58px) !important; }
    .apt-main-info > div:first-child { gap: 4px !important; }
    .apt-main-info > div:nth-child(2) { gap: 6px !important; }
    .apt-side { width: 100%; flex-direction: row !important; align-items: center !important; justify-content: space-between; gap: 8px !important; padding-left: 58px; }
    .apt-side > span { flex-shrink: 0; }
    .apt-side > div { margin-left: auto; }
}
</style>
@endpush

@if($appointments->isEmpty())
<div style="text-align:center;padding:52px 24px;color:var(--txt-lt)">
    <div style="width:52px;height:52px;border-radius:14px;background:var(--parch);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </div>
    <div style="font-family:'Lora',serif;font-size:1.1rem;color:var(--txt-md);margin-bottom:6px">
        {{ $tab === 'upcoming' ? 'No upcoming appointments' : ($tab === 'past' ? 'No past appointments' : ($tab === 'unpaid' ? 'No unpaid appointments 🎉' : 'No cancelled appointments')) }}
    </div>
    @if($tab === 'upcoming')
    <p style="font-size:.8125rem;margin-bottom:16px">Book an appointment with any of your doctors.</p>
    <a href="{{ route('patient.appointments.book') }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:9px 20px;background:var(--plum);color:#fff;border-radius:10px;font-size:.875rem;font-weight:600;text-decoration:none">
        Book Appointment
    </a>
    @endif
</div>
@else

<div style="display:flex;flex-direction:column;gap:12px">
    @foreach($appointments as $apt)
    @php
        $drName   = $apt->doctor?->profile?->full_name ?? 'Doctor';
        $drSpec   = $apt->doctor?->doctorProfile?->specialization;
        $patName  = $apt->familyMember?->full_name ?? auth()->user()->profile?->full_name;
        $colors   = ['#4a3760','#3d7a6e','#7a5c3d','#3d5e7a','#7a3d4a'];
        $color    = $colors[$apt->doctor_user_id % count($colors)];
        $status   = $apt->status;
        $statusCfg = match($status) {
            'confirmed' => ['bg'=>'#e8f5f3','color'=>'#1a7a6a','dot'=>'#1a7a6a','label'=>'Confirmed'],
            'booked'    => ['bg'=>'#fef9ec','color'=>'#b45309','dot'=>'#f59e0b','label'=>'Pending'],
            'completed' => ['bg'=>'#f0f9ff','color'=>'#0369a1','dot'=>'#0ea5e9','label'=>'Completed'],
            'cancelled' => ['bg'=>'#fef2f2','color'=>'#b91c1c','dot'=>'#ef4444','label'=>'Cancelled'],
            default     => ['bg'=>'var(--parch)','color'=>'var(--txt-lt)','dot'=>'var(--txt-lt)','label'=>ucfirst($status)],
        };
    @endphp
    <div class="panel" style="padding:0;overflow:hidden;transition:box-shadow .15s" onmouseover="this.style.boxShadow='0 4px 18px rgba(74,55,96,.08)'" onmouseout="this.style.boxShadow='none'">
        <div style="display:flex;gap:0;align-items:stretch">
            {{-- Colour side bar --}}
            <div style="width:4px;background:{{ $color }};flex-shrink:0;border-radius:0"></div>

            <div class="apt-card-body" style="flex:1;padding:16px 20px;display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
                {{-- Doctor avatar --}}
                <x-avatar name="{{ $drName }}" :photo="$apt->doctor?->profile?->profile_photo"
                           :size="44" :radius="11" :bg="$color" font-size="1rem" />

                {{-- Main info --}}
                <div class="apt-main-info" style="flex:1;min-width:160px">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px">
                        <span style="font-size:.9375rem;font-weight:600;color:var(--txt)">Dr. {{ $drName }}</span>
                        @if($drSpec)<span style="font-size:.75rem;color:var(--txt-lt)">· {{ $drSpec }}</span>@endif
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:.8rem;color:var(--txt-md);margin-bottom:6px">
                        <span style="display:flex;align-items:center;gap:4px">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ $apt->slot_datetime->format('D, d M Y') }}
                        </span>
                        <span style="display:flex;align-items:center;gap:4px">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            {{ $apt->slot_datetime->format('h:i A') }}
                        </span>
                        @if($apt->type)
                        <span style="font-size:.72rem;padding:2px 8px;border-radius:20px;background:var(--parch);color:var(--txt-lt)">{{ ucwords(str_replace('_',' ',$apt->type)) }}</span>
                        @endif
                        @if($apt->familyMember)
                        <span style="font-size:.72rem;color:var(--txt-lt)">· For {{ $apt->familyMember->full_name }}</span>
                        @endif
                    </div>
                    @if($apt->reason)
                    <div style="font-size:.78rem;color:var(--txt-lt);font-style:italic">{{ Str::limit($apt->reason, 80) }}</div>
                    @endif
                </div>

                {{-- Status + actions --}}
                <div class="apt-side" style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0">
                    <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                        <span style="font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;background:{{ $statusCfg['bg'] }};color:{{ $statusCfg['color'] }};display:flex;align-items:center;gap:4px">
                            <span style="width:5px;height:5px;border-radius:50%;background:{{ $statusCfg['dot'] }};display:inline-block"></span>
                            {{ $statusCfg['label'] }}
                        </span>
                        @if($apt->payment_status === 'pending' && !in_array($apt->status, ['cancelled']))
                            <span style="font-size:.68rem;font-weight:700;padding:3px 9px;border-radius:20px;background:#fef3c7;color:#92400e;display:flex;align-items:center;gap:3px" title="Payment pending">
                                <svg width="9" height="9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M12 8v4M12 16h.01"/></svg>
                                Due ₹{{ number_format($apt->fee ?? 0) }}
                            </span>
                        @endif
                    </div>

                    <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:flex-end">
                        <a href="{{ route('patient.appointments.show', $apt) }}"
                           style="font-size:.75rem;font-weight:500;padding:5px 10px;border:1.5px solid var(--warm-bd);border-radius:8px;color:var(--txt-md);text-decoration:none;transition:all .12s"
                           onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
                            View →
                        </a>
                        @if($apt->payment_status === 'pending' && !in_array($apt->status, ['cancelled']))
                            <button type="button"
                                onclick="payNow({{ $apt->id }}, {{ $apt->fee }}, 'Dr. {{ addslashes($apt->doctor?->profile?->full_name ?? '') }}', '{{ $apt->appointment_number }}')"
                                style="font-size:.75rem;font-weight:600;padding:5px 12px;background:var(--plum);color:#fff;border-radius:8px;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:4px;transition:opacity .12s;font-family:'Plus Jakarta Sans',sans-serif"
                                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><rect x="2" y="5" width="20" height="14" rx="2"/><path stroke-linecap="round" d="M2 10h20"/></svg>
                                Pay Now
                            </button>
                        @endif
                        @if($apt->isUpcoming())
                        <form method="POST" action="{{ route('patient.appointments.cancel', $apt) }}"
                              onsubmit="return confirm('Cancel this appointment?')">
                            @csrf
                            <button type="submit"
                                    style="font-size:.75rem;font-weight:500;padding:5px 10px;border:1.5px solid #fecaca;border-radius:8px;color:#dc2626;background:transparent;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .12s"
                                    onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                                Cancel
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Pagination --}}
@if($appointments->hasPages())
<div style="display:flex;justify-content:center;gap:6px;margin-top:16px">
    @if(!$appointments->onFirstPage())
        <a href="{{ $appointments->previousPageUrl() }}" style="padding:6px 14px;border-radius:8px;border:1px solid var(--warm-bd);color:var(--txt);text-decoration:none;font-size:.8rem">← Prev</a>
    @endif
    @if($appointments->hasMorePages())
        <a href="{{ $appointments->nextPageUrl() }}" style="padding:6px 14px;border-radius:8px;border:1px solid var(--warm-bd);color:var(--txt);text-decoration:none;font-size:.8rem">Next →</a>
    @endif
</div>
@endif
@endif
@push('scripts')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
function payNow(aptId, fee, doctorName, aptNo) {
    fetch('{{ route('patient.payments.order') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ appointment_id: aptId }),
    })
    .then(res => res.json())
    .then(order => {
        if (!order.success) {
            alert(order.message || 'Could not create order.');
            return;
        }
        const rzp = new Razorpay({
            key: order.key_id,
            amount: order.amount,
            currency: order.currency,
            name: order.name,
            description: order.description,
            order_id: order.order_id,
            prefill: {
                name: order.prefill_name,
                contact: order.prefill_mobile,
            },
            theme: { color: '#4a3760' },
            modal: {
                ondismiss() { /* dismissed */ }
            },
            handler: async function(response) {
                const verRes = await fetch('{{ route('patient.payments.verify') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature,
                    }),
                });
                const ver = await verRes.json();
                if (ver.success) {
                    window.location.href = ver.receipt_url;
                } else {
                    alert(ver.message || 'Payment verification failed.');
                }
            }
        });
        rzp.open();
    })
    .catch(() => {
        alert('Something went wrong. Please try again.');
    });
}
</script>
@endpush

</div>
@endsection
