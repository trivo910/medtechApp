@extends('layouts.doctor')
@section('title', 'Appointments')
@section('page-title', $date ? 'Appointments — '.Carbon\Carbon::parse($date)->format('d M Y') : 'Appointments')

@section('content')
@php
    $statusColors = [
        'booked'    => ['bg'=>'#fef9ec','color'=>'#b45309','dot'=>'#f59e0b','label'=>'Pending'],
        'confirmed' => ['bg'=>'#e8f5f3','color'=>'#1a7a6a','dot'=>'#10b981','label'=>'Confirmed'],
        'completed' => ['bg'=>'#f0f9ff','color'=>'#0369a1','dot'=>'#0ea5e9','label'=>'Completed'],
        'cancelled' => ['bg'=>'#fef2f2','color'=>'#b91c1c','dot'=>'#ef4444','label'=>'Cancelled'],
    ];
@endphp
<div class="fade-in" x-data="{ processing: null }">

{{-- Tabs + actions --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px">
    <div style="display:flex;gap:2px;border-bottom:2px solid var(--warm-bd);overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%">
        @foreach(['today'=>"Today ({$counts['today']})", 'upcoming'=>"Upcoming ({$counts['upcoming']})", 'past'=>"Past ({$counts['past']})", 'cancelled'=>'Cancelled'] as $t => $lbl)
        <a href="{{ route('doctor.appointments.index', ['tab' => $t]) }}"
           style="padding:9px 16px;font-size:.875rem;font-weight:500;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap;flex-shrink:0;
                  {{ $tab === $t ? 'color:var(--ink);border-bottom-color:var(--ink);font-weight:600' : 'color:var(--txt-lt)' }}">
            {{ $lbl }}
        </a>
        @endforeach
    </div>
    <div style="display:flex;gap:8px">
        <a href="{{ route('doctor.appointments.calendar') }}"
           style="font-size:.8rem;padding:7px 13px;border:1.5px solid var(--warm-bd);border-radius:9px;color:var(--txt-md);text-decoration:none;transition:background .12s"
           onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
            Calendar View
        </a>
    </div>
</div>

@if($appointments->isEmpty())
<div style="text-align:center;padding:52px;color:var(--txt-lt)">
    <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt-md)">No appointments</div>
</div>
@else
<div class="panel">
    @foreach($appointments as $apt)
    @php
        $cfg      = $statusColors[$apt->status] ?? $statusColors['booked'];
        $pName    = $apt->familyMember?->full_name ?? $apt->patient?->profile?->full_name ?? 'Unknown';
        $pPhoto   = $apt->familyMember?->profile_photo ?? $apt->patient?->profile?->profile_photo;
        $colors   = ['#3d7a6e','#7a6e3d','#6e3d7a','#3d607a','#7a3d4a'];
        $pColor   = $colors[$apt->patient_user_id % count($colors)];
    @endphp
    <div class="dr-row-wrap doctor-appointment-row" style="display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--warm-bd);transition:background .12s"
         onmouseover="this.style.background='#faf8f5'" onmouseout="this.style.background='transparent'">

        {{-- Time column --}}
        <div class="doctor-appointment-time" style="width:56px;text-align:center;flex-shrink:0">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;font-weight:500;color:var(--txt)">
                {{ $apt->slot_datetime->format('h:i') }}
            </div>
            <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);font-weight:600">
                {{ $apt->slot_datetime->format('A') }}
            </div>
            <div style="font-size:.65rem;color:var(--txt-lt);margin-top:1px">
                {{ $apt->slot_datetime->format('d M') }}
            </div>
        </div>

        {{-- Patient avatar --}}
        <x-avatar class="doctor-appointment-avatar" name="{{ $pName }}" :photo="$pPhoto"
                   :size="38" :radius="10" :bg="$pColor" font-size=".875rem" />

        {{-- Info --}}
        <div class="doctor-appointment-info" style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:2px">
                <span style="font-size:.9rem;font-weight:600;color:var(--txt)">{{ $pName }}</span>
                @if($apt->familyMember)
                <span style="font-size:.68rem;color:var(--txt-lt)">({{ $apt->patient?->profile?->full_name }}'s {{ $apt->familyMember->relation }})</span>
                @endif
                @if($apt->type)
                <span style="font-size:.68rem;padding:2px 7px;border-radius:20px;background:var(--parch);color:var(--txt-lt)">{{ ucwords(str_replace('_',' ',$apt->type)) }}</span>
                @endif
            </div>
            @if($apt->reason)
            <div style="font-size:.78rem;color:var(--txt-lt);font-style:italic">{{ Str::limit($apt->reason, 80) }}</div>
            @endif
            <div style="font-size:.72rem;color:var(--txt-lt);margin-top:2px">{{ $apt->appointment_number }}</div>
        </div>

        {{-- Status badge --}}
        <span class="doctor-appointment-status" style="font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }};display:flex;align-items:center;gap:4px;flex-shrink:0">
            <span style="width:5px;height:5px;border-radius:50%;background:{{ $cfg['dot'] }};display:inline-block"></span>
            {{ $cfg['label'] }}
        </span>

        {{-- Actions --}}
        <div class="doctor-appointment-actions" style="display:flex;gap:5px;flex-shrink:0" :id="'apt-actions-{{ $apt->id }}'">
            {{-- Confirm button (booked only) --}}
            @if($apt->status === 'booked')
            <button type="button"
                    class="apt-confirm-btn"
                    @click="
                        processing='{{ $apt->id }}';
                        fetch('{{ route('doctor.appointments.confirm', $apt) }}', {method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}})
                        .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); }).finally(()=>processing=null)"
                    :disabled="processing==='{{ $apt->id }}'">
                <span class="apt-confirm-spinner" x-show="processing==='{{ $apt->id }}'"></span>
                <svg class="apt-confirm-icon" x-show="processing!=='{{ $apt->id }}'" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span x-text="processing==='{{ $apt->id }}' ? 'Confirming…' : 'Confirm'"></span>
            </button>
            @endif

            {{-- Complete button (confirmed/booked + past) --}}
            @if(in_array($apt->status, ['booked','confirmed']) && $apt->slot_datetime->isPast())
            <button type="button"
                    class="apt-confirm-btn"
                    @click="
                        processing='{{ $apt->id }}c';
                        fetch('{{ route('doctor.appointments.complete', $apt) }}', {method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}})
                        .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); }).finally(()=>processing=null)"
                    :disabled="processing==='{{ $apt->id }}c'"
                    style="font-size:.75rem;font-weight:500;padding:6px 11px;background:var(--ink);color:#fff;border:none;border-radius:8px;cursor:pointer;font-family:'Outfit',sans-serif;display:flex;align-items:center;gap:4px"
                    :style="processing==='{{ $apt->id }}c' ? 'opacity:.5' : ''">
                Complete
            </button>
            @endif

            <a href="{{ route('doctor.appointments.show', $apt) }}"
               style="font-size:.75rem;font-weight:500;padding:6px 10px;border:1.5px solid var(--warm-bd);border-radius:8px;color:var(--txt-md);text-decoration:none;transition:background .12s;display:flex;align-items:center"
               onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
                →
            </a>
        </div>
    </div>
    @endforeach
</div>

@if($appointments->hasPages())
<div style="display:flex;justify-content:center;gap:6px;margin-top:14px">
    @if(!$appointments->onFirstPage())
    <a href="{{ $appointments->previousPageUrl() }}" style="padding:6px 14px;border-radius:8px;border:1px solid var(--warm-bd);color:var(--txt);text-decoration:none;font-size:.8rem">← Prev</a>
    @endif
    @if($appointments->hasMorePages())
    <a href="{{ $appointments->nextPageUrl() }}" style="padding:6px 14px;border-radius:8px;border:1px solid var(--warm-bd);color:var(--txt);text-decoration:none;font-size:.8rem">Next →</a>
    @endif
</div>
@endif
@endif

</div>
@endsection
@push('styles')
<style>
@keyframes spin{to{transform:rotate(360deg)}}

/* ── Light "Confirm" action button ─────────────────────────────────────── */
.apt-confirm-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 6px 13px;
    background: #fff;
    color: var(--txt-md, #4a5a58);
    border: 1.5px solid var(--warm-bd);
    border-radius: 8px;
    font-family: 'Outfit', sans-serif;
    font-size: .75rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    cursor: pointer;
    transition: background .13s, border-color .13s, color .13s, box-shadow .13s;
}
.apt-confirm-btn:hover:not(:disabled) {
    background: #edf6f4;
    border-color: #b8dbd7;
    color: var(--leaf, #3d7a6e);
    box-shadow: 0 1px 4px rgba(61, 122, 110, .12);
}
.apt-confirm-btn:active:not(:disabled) { transform: translateY(1px); }
.apt-confirm-btn:focus-visible {
    outline: 2px solid var(--leaf, #3d7a6e);
    outline-offset: 2px;
}
.apt-confirm-btn:disabled {
    opacity: .55;
    cursor: not-allowed;
    box-shadow: none;
}
.apt-confirm-icon,
.apt-confirm-spinner { flex-shrink: 0; }
.apt-confirm-spinner {
    width: 11px;
    height: 11px;
    border: 1.5px solid rgba(61, 122, 110, .3);
    border-top-color: var(--leaf, #3d7a6e);
    border-radius: 50%;
    animation: spin .6s linear infinite;
}
</style>
@endpush
