@extends('layouts.doctor')
@section('title', 'Appointment #' . $appointment->id)
@section('page-title')
    <a href="{{ route('doctor.appointments.index') }}" style="color:var(--txt-lt);text-decoration:none;font-size:.85rem;font-weight:400">Appointments</a>
    <span style="color:var(--txt-lt);margin:0 6px">/</span>
    Appointment #{{ $appointment->id }}
@endsection

@section('content')
@php
    $patient     = $appointment->patient;
    $profile     = $patient?->profile;
    $member      = $appointment->familyMember;
    $name        = $member?->full_name        ?? $profile?->full_name    ?? '—';
    $age         = $member?->age              ?? $profile?->age;
    $bloodGroup  = $member?->blood_group      ?? $profile?->blood_group;
    $gender      = $member?->gender           ?? $profile?->gender;
    $statusColors = [
        'pending'   => ['bg'=>'#fef9c3','color'=>'#854d0e'],
        'confirmed' => ['bg'=>'#dbeafe','color'=>'#1e40af'],
        'completed' => ['bg'=>'#dcfce7','color'=>'#166534'],
        'cancelled' => ['bg'=>'#fee2e2','color'=>'#991b1b'],
        'no_show'   => ['bg'=>'#f3f4f6','color'=>'#374151'],
    ];
    $sc = $statusColors[$appointment->status] ?? ['bg'=>'#f3f4f6','color'=>'#374151'];
@endphp

{{-- Payment status banner --}}
@if($appointment->payment_status === 'pending')
    <div style="display:flex;align-items:center;gap:8px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:10px 14px;font-size:.8rem;color:#9a3412;margin-bottom:16px">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
        Payment pending
        @if($appointment->payment_preference === 'cash')
            &mdash; cash expected
        @endif
    </div>
@elseif($appointment->payment_status === 'paid')
    <div style="display:flex;align-items:center;gap:8px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:10px 14px;font-size:.8rem;color:#065f46;margin-bottom:16px">
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        Payment received
    </div>
@endif
@if($appointment->completion_note)
    <div style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:10px;padding:10px 14px;font-size:.8rem;color:#334155;margin-bottom:16px">
        <strong>Completed without payment:</strong> {{ $appointment->completion_note }}
    </div>
@endif

<div class="dr-grid-2col" style="display:grid;grid-template-columns:1fr 300px;gap:18px;align-items:start">

{{-- Left --}}
<div>

{{-- Patient card --}}
<div style="background:var(--cream);border:1px solid var(--warm-bd);border-radius:14px;margin-bottom:16px;overflow:hidden">
    <div style="padding:13px 20px;border-bottom:1px solid var(--warm-bd);font-family:'Cormorant Garamond',serif;font-size:1.05rem;font-weight:500;color:var(--txt)">Patient</div>
    <div style="padding:18px 20px;display:flex;align-items:center;gap:12px">
        <div style="width:42px;height:42px;border-radius:11px;background:var(--ink);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:1rem;flex-shrink:0">{{ strtoupper(substr($name,0,1)) }}</div>
        <div>
            <div style="font-weight:600;color:var(--txt)">{{ $name }}</div>
            <div style="font-size:.75rem;color:var(--txt-lt);margin-top:2px">
                {{ $patient?->country_code }} {{ $patient?->mobile_number }}
                {{ $age        ? ' · Age ' . $age        : '' }}
                {{ $gender     ? ' · ' . ucfirst($gender)  : '' }}
                {{ $bloodGroup ? ' · ' . $bloodGroup        : '' }}
                {{ $member     ? ' · ' . ucfirst($member->relation) . ' of ' . ($profile?->full_name ?? 'account holder') : '' }}
            </div>
        </div>
        @if($member)
        <a href="{{ route('doctor.patients.show', [$patient->id, 'member' => $member->id, 'tab' => 'prescriptions']) }}"
           style="margin-left:auto;font-size:.8rem;padding:6px 14px;border:1.5px solid var(--warm-bd);border-radius:8px;color:var(--txt-md);text-decoration:none;white-space:nowrap">
            View {{ $member->full_name }}
        </a>
        @else
        <a href="{{ route('doctor.patients.show', [$patient->id, 'tab' => 'prescriptions']) }}"
           style="margin-left:auto;font-size:.8rem;padding:6px 14px;border:1.5px solid var(--warm-bd);border-radius:8px;color:var(--txt-md);text-decoration:none;white-space:nowrap">
            View Patient
        </a>
        @endif
    </div>
</div>

{{-- Appointment details --}}
<div style="background:var(--cream);border:1px solid var(--warm-bd);border-radius:14px;margin-bottom:16px">
    <div style="padding:13px 20px;border-bottom:1px solid var(--warm-bd);font-family:'Cormorant Garamond',serif;font-size:1.05rem;font-weight:500;color:var(--txt)">Details</div>
    <div class="dr-grid-2col" style="padding:18px 20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <div style="font-size:.72rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Date & Time</div>
            <div style="font-size:.95rem;color:var(--txt)">{{ $appointment->slot_datetime?->format('d M Y, h:i A') }}</div>
        </div>
        <div>
            <div style="font-size:.72rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Status</div>
            <span style="font-size:.8rem;padding:4px 10px;border-radius:7px;font-weight:600;background:{{ $sc['bg'] }};color:{{ $sc['color'] }}">{{ ucfirst($appointment->status) }}</span>
        </div>
        <div>
            <div style="font-size:.72rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Type</div>
            <div style="font-size:.9rem;color:var(--txt)">{{ ucfirst($appointment->type ?? 'in_person') }}</div>
        </div>
        <div>
            <div style="font-size:.72rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Booked</div>
            <div style="font-size:.9rem;color:var(--txt)">{{ $appointment->created_at?->format('d M Y') }}</div>
        </div>
        @if($appointment->chief_complaint)
        <div style="grid-column:1/-1">
            <div style="font-size:.72rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Chief Complaint</div>
            <div style="font-size:.9rem;color:var(--txt)">{{ $appointment->chief_complaint }}</div>
        </div>
        @endif
        @if($appointment->notes)
        <div style="grid-column:1/-1">
            <div style="font-size:.72rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Notes</div>
            <div style="font-size:.9rem;color:var(--txt)">{{ $appointment->notes }}</div>
        </div>
        @endif
    </div>
</div>

{{-- Actions --}}
@if(!in_array($appointment->status, ['completed','cancelled']))
<div style="background:var(--cream);border:1px solid var(--warm-bd);border-radius:14px;padding:18px 20px;display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
    @if($appointment->status === 'booked')
    <form method="POST" action="{{ route('doctor.appointments.confirm', $appointment) }}">
        @csrf
        <button type="submit" style="padding:9px 18px;background:var(--ink);color:#fff;border:none;border-radius:10px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif">Confirm</button>
    </form>
    @endif
    @if(in_array($appointment->status, ['booked','confirmed']))
    @if($appointment->slot_datetime?->isPast())
    <a href="#billing" style="padding:9px 18px;background:#16a34a;color:#fff;border-radius:10px;font-size:.875rem;font-weight:600;text-decoration:none">Mark Complete &amp; Payment</a>
    @endif
    <form method="POST" action="{{ route('doctor.appointments.cancel', $appointment) }}" onsubmit="return confirm('Cancel this appointment?')">
        @csrf
        <button type="submit" style="padding:9px 18px;background:transparent;color:#ef4444;border:1.5px solid #ef4444;border-radius:10px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif">Cancel</button>
    </form>
    @endif
    <a href="{{ route('doctor.prescriptions.create', array_filter(['patient' => $patient?->id, 'appointment' => $appointment->id, 'member' => $member?->id])) }}"
       style="padding:9px 18px;background:var(--leaf);color:#fff;border-radius:10px;font-size:.875rem;font-weight:600;text-decoration:none">Write Prescription</a>
</div>
@endif

@if($appointment->status !== 'cancelled')
@include('doctor.partials.billing-panel', ['appointment' => $appointment, 'bill' => $appointment->bill])
@endif

@include('doctor.appointments._previous-prescriptions')

</div>

{{-- Right sidebar --}}
<div>
    <div style="background:var(--cream);border:1px solid var(--warm-bd);border-radius:14px;overflow:hidden">
        <div style="background:var(--ink);padding:16px 18px">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:#fff;font-weight:500">{{ $appointment->doctor?->doctorProfile?->clinic_name ?? 'Clinic' }}</div>
            <div style="font-size:.72rem;color:rgba(255,255,255,.6);margin-top:3px">Dr. {{ $appointment->doctor?->profile?->full_name }}</div>
        </div>
        <div style="padding:14px 18px;font-size:.82rem;color:var(--txt-md)">
            <div style="margin-bottom:8px"><span style="color:var(--txt-lt)">Appointment ID</span><br><span style="font-family:monospace;font-weight:600">#{{ $appointment->id }}</span></div>
            <div><span style="color:var(--txt-lt)">Slot</span><br><span style="font-weight:500">{{ $appointment->slot_datetime?->format('D, d M Y') }}<br>{{ $appointment->slot_datetime?->format('h:i A') }}</span></div>
        </div>
    </div>
    @include('doctor.appointments._assign-timeline')
</div>

</div>

@endsection
