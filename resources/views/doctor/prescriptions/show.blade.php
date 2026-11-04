@extends('layouts.doctor')
@section('title', $prescription->prescription_number)
@section('page-title')
    <a href="{{ route('doctor.prescriptions.index') }}" style="color:var(--txt-lt);text-decoration:none;font-size:.85rem;font-weight:400">Prescriptions</a>
    <span style="color:var(--txt-lt);margin:0 6px">/</span>
    {{ $prescription->prescription_number }}
@endsection

@section('content')
@php
    $p       = $prescription->patient;
    $profile = $p->profile;
    $doctor  = $prescription->doctor;
    $member  = $prescription->family_member_id ? $p->familyMembers->find($prescription->family_member_id) : null;
    $displayName = $member?->full_name ?? $profile?->full_name ?? 'Patient';
    $colors = ['#3d7a6e','#7a6e3d','#6e3d7a','#3d607a','#7a3d4a'];
    $color  = $colors[$p->id % count($colors)];
@endphp

{{-- Action bar --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:.78rem;font-weight:600;padding:4px 11px;border-radius:20px;{{ $prescription->status==='active' ? 'background:#e8f5f3;color:#1a7a6a' : 'background:var(--parch);color:var(--txt-lt)' }}">{{ ucfirst($prescription->status) }}</span>
        @if($prescription->is_sent_whatsapp)
        <span style="font-size:.78rem;font-weight:600;padding:4px 11px;border-radius:20px;background:#f0fdf4;color:#16a34a">✓ Sent via WhatsApp</span>
        @else
        <span style="font-size:.78rem;font-weight:500;padding:4px 11px;border-radius:20px;background:var(--parch);color:var(--txt-lt)">Not sent yet</span>
        @endif
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="POST" action="{{ route('doctor.prescriptions.regenerate-pdf', $prescription) }}">
            @csrf
            <button type="submit" style="display:flex;align-items:center;gap:6px;padding:8px 13px;border:1.5px solid var(--warm-bd);border-radius:9px;background:transparent;color:var(--txt-md);font-size:.8rem;font-weight:500;cursor:pointer;font-family:'Outfit',sans-serif" onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Regen PDF
            </button>
        </form>
        <a href="{{ route('doctor.prescriptions.pdf', $prescription) }}" target="_blank" style="display:flex;align-items:center;gap:6px;padding:8px 13px;border:1.5px solid var(--warm-bd);border-radius:9px;background:transparent;color:var(--txt-md);font-size:.8rem;font-weight:500;text-decoration:none" onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            View PDF
        </a>
        <a href="{{ route('doctor.prescriptions.pdf', $prescription) }}?download=1" style="display:flex;align-items:center;gap:6px;padding:8px 13px;border:1.5px solid var(--warm-bd);border-radius:9px;background:transparent;color:var(--txt-md);font-size:.8rem;font-weight:500;text-decoration:none" onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Download
        </a>
        <a href="{{ route('doctor.prescriptions.send-whatsapp', $prescription) }}" style="display:flex;align-items:center;gap:7px;padding:8px 15px;background:#25D366;color:#fff;border-radius:9px;font-size:.8rem;font-weight:600;text-decoration:none" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            {{ $prescription->is_sent_whatsapp ? 'Resend' : 'Send via WhatsApp' }}
        </a>
    </div>
</div>

<div class="dr-grid-2col" style="display:grid;grid-template-columns:1fr 280px;gap:18px;align-items:start">
<div>
<div class="panel fade-in">
    {{-- Letterhead --}}
    <div style="background:var(--ink);padding:16px 22px;display:flex;justify-content:space-between;align-items:flex-start">
        <div>
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.3rem;color:#fff;font-weight:500">{{ $doctor->doctorProfile?->clinic_name ?? 'Medical Clinic' }}</div>
            <div style="font-size:.72rem;color:rgba(255,255,255,.5);margin-top:3px">{{ $doctor->doctorProfile?->clinic_address }}{{ $doctor->doctorProfile?->clinic_city ? ', ' . $doctor->doctorProfile->clinic_city : '' }}</div>
        </div>
        <div style="text-align:right">
            <div style="font-size:.875rem;font-weight:600;color:#fff">Dr. {{ $doctor->profile?->full_name }}</div>
            <div style="font-size:.72rem;color:rgba(255,255,255,.5);margin-top:2px">{{ $doctor->doctorProfile?->qualification }}{{ $doctor->doctorProfile?->specialization ? ' · ' . $doctor->doctorProfile->specialization : '' }}</div>
        </div>
    </div>
    <div style="height:3px;background:var(--leaf)"></div>

    {{-- Patient strip --}}
    <div style="background:var(--parch);padding:12px 22px;border-bottom:1px solid var(--warm-bd);display:flex;gap:20px;flex-wrap:wrap;align-items:center">
        <div style="display:flex;align-items:center;gap:10px">
            <div style="width:34px;height:34px;border-radius:9px;background:{{ $color }};display:flex;align-items:center;justify-content:center;font-size:.875rem;font-weight:700;color:#fff">{{ strtoupper(substr($displayName,0,1)) }}</div>
            <div>
                <div style="font-size:.9rem;font-weight:600;color:var(--txt)">{{ $displayName }}</div>
                <div style="font-size:.75rem;color:var(--txt-lt)">{{ ($member?->age ?? $profile?->age) ? 'Age ' . ($member?->age ?? $profile?->age) : '' }}{{ $profile?->blood_group ? ' · ' . $profile->blood_group : '' }}{{ $member ? ' · ' . ucfirst($member->relation) : '' }}</div>
            </div>
        </div>
        <div style="margin-left:auto;text-align:right">
            <div style="font-size:.7rem;color:var(--txt-lt)">Rx Number</div>
            <div style="font-family:monospace;font-size:.9rem;font-weight:600;color:var(--txt)">{{ $prescription->prescription_number }}</div>
        </div>
        <div style="text-align:right">
            <div style="font-size:.7rem;color:var(--txt-lt)">Date</div>
            <div style="font-size:.875rem;font-weight:600;color:var(--txt)">{{ $prescription->prescribed_date->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Diagnosis --}}
    @if($prescription->chief_complaint||$prescription->diagnosis)
    <div style="padding:12px 22px;border-bottom:1px solid var(--warm-bd);display:flex;gap:20px;flex-wrap:wrap">
        @if($prescription->chief_complaint)
        <div><div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);margin-bottom:3px">Chief Complaint</div><div style="font-size:.875rem;color:var(--txt)">{{ $prescription->chief_complaint }}</div></div>
        @endif
        @if($prescription->diagnosis)
        <div><div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);margin-bottom:3px">Diagnosis</div><div style="font-size:.875rem;font-weight:500;color:var(--txt)">{{ $prescription->diagnosis }}</div></div>
        @endif
    </div>
    @endif

    {{-- Medicines --}}
    <div style="padding:16px 22px">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--leaf);margin-bottom:12px;padding-bottom:6px;border-bottom:1.5px solid var(--leaf)">Prescribed Medicines</div>
        @foreach($prescription->medicines->sortBy('sort_order') as $i => $med)
        <div style="display:flex;gap:14px;padding:11px 0;border-bottom:1px solid var(--parch);align-items:flex-start">
            <div style="min-width:22px;height:22px;border-radius:50%;background:var(--parch);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:var(--txt-lt);flex-shrink:0;margin-top:1px">{{ $i+1 }}</div>
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap">
                    <span style="font-size:.925rem;font-weight:600;color:var(--txt)">{{ $med->medicine_name }}</span>
                    @if($med->form)<span style="font-size:.72rem;background:var(--parch);padding:1px 7px;border-radius:20px;color:var(--txt-lt)">{{ $med->form }}</span>@endif
                    @if($med->generic_name)<span style="font-size:.75rem;color:var(--txt-lt)">({{ $med->generic_name }})</span>@endif
                </div>
                <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:5px">
                    @if($med->dosage)<span style="font-size:.8rem;color:var(--txt-md)">💊 {{ $med->dosage }}</span>@endif
                    @if($med->frequency)<span style="font-size:.8rem;color:var(--txt-md)">🔄 {{ $med->frequency }}</span>@endif
                    @if($med->duration_days)<span style="font-size:.8rem;color:var(--txt-md)">⏱ {{ $med->duration_days }} days</span>@endif
                    @if($med->timing)<span style="font-size:.75rem;font-weight:600;padding:2px 8px;border-radius:20px;background:#e8f5f3;color:#1a7a6a">{{ $med->timing_label }}</span>@endif
                </div>
                @if($med->special_instructions)<div style="margin-top:4px;font-size:.75rem;color:var(--coral);font-style:italic">⚠ {{ $med->special_instructions }}</div>@endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Notes & follow-up --}}
    @if($prescription->notes||$prescription->follow_up_instructions||$prescription->follow_up_date)
    <div style="padding:14px 22px;background:var(--parch);border-top:1px solid var(--warm-bd);display:flex;gap:18px;flex-wrap:wrap">
        @if($prescription->notes)
        <div style="flex:1;min-width:180px">
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);margin-bottom:5px">Patient Advice</div>
            <div style="font-size:.8125rem;color:var(--txt-md);line-height:1.6">{{ $prescription->notes }}</div>
        </div>
        @endif
        @if($prescription->follow_up_date||$prescription->follow_up_instructions)
        <div style="min-width:160px">
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);margin-bottom:5px">Follow-up</div>
            @if($prescription->follow_up_date)<div style="font-size:1rem;font-family:'Cormorant Garamond',serif;font-weight:500;color:var(--txt)">{{ $prescription->follow_up_date->format('d M Y') }}</div>@endif
            @if($prescription->follow_up_instructions)<div style="font-size:.78rem;color:var(--txt-md);margin-top:3px">{{ $prescription->follow_up_instructions }}</div>@endif
        </div>
        @endif
    </div>
    @endif

    <div style="padding:14px 22px;text-align:right;border-top:1px solid var(--warm-bd)">
        <div style="display:inline-block;text-align:center">
            <div style="border-top:1.5px solid var(--txt);width:120px;margin-bottom:5px"></div>
            <div style="font-size:.8rem;font-weight:600;color:var(--txt)">Dr. {{ $doctor->profile?->full_name }}</div>
            <div style="font-size:.7rem;color:var(--txt-lt)">{{ $doctor->doctorProfile?->qualification }}</div>
        </div>
    </div>
</div>

@if($prescription->appointment)
    @if($prescription->appointment->status !== 'cancelled')
        @include('doctor.partials.billing-panel', [
            'appointment'  => $prescription->appointment,
            'bill'         => $prescription->appointment->bill,
            'prescription' => $prescription,
        ])
    @endif
@else
    <div class="panel" style="margin-top:16px">
        <div style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div>
                <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt);margin-bottom:3px">Complete Visit &amp; Payment</div>
                <div style="font-size:.8rem;color:var(--txt-lt)">This prescription is not linked to a visit yet. Open a visit to add payment heads and complete it.</div>
            </div>
            <form method="POST" action="{{ route('doctor.prescriptions.start-visit', $prescription) }}">
                @csrf
                <button type="submit" style="padding:9px 16px;background:var(--leaf);color:#fff;border:0;border-radius:9px;font-size:.82rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif">Start visit &amp; bill</button>
            </form>
        </div>
    </div>
@endif
</div>

{{-- Right sidebar --}}
<div>
    <div class="panel" style="margin-bottom:14px">
        <div style="padding:14px 16px">
            <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:10px">Info</div>
            @foreach(['Rx Number' => $prescription->prescription_number,'Date' => $prescription->prescribed_date->format('d M Y'),'Medicines' => $prescription->medicines->count().' prescribed','Status' => ucfirst($prescription->status),'PDF' => ($prescription->pdf_path ? 'Ready' : 'Pending'),'WhatsApp' => ($prescription->is_sent_whatsapp ? 'Sent '.$prescription->whatsapp_sent_at?->diffForHumans() : 'Not sent')] as $lbl => $val)
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--parch);font-size:.8rem">
                <span style="color:var(--txt-lt)">{{ $lbl }}</span>
                <span style="font-weight:500;color:var(--txt)">{{ $val }}</span>
            </div>
            @endforeach
        </div>
    </div>
    <a href="{{ route('doctor.patients.history', $prescription->patient_user_id) }}?tab=prescriptions" style="display:flex;align-items:center;gap:8px;padding:12px 16px;background:var(--white);border:1px solid var(--warm-bd);border-radius:12px;text-decoration:none;margin-bottom:10px;transition:all .15s" onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='var(--white)'">
        <div style="width:32px;height:32px;border-radius:8px;background:{{ $color }};display:flex;align-items:center;justify-content:center;font-size:.875rem;font-weight:700;color:#fff">{{ strtoupper(substr($displayName,0,1)) }}</div>
        <div style="flex:1"><div style="font-size:.875rem;font-weight:500;color:var(--txt)">View Patient History</div><div style="font-size:.72rem;color:var(--txt-lt)">{{ $displayName }}</div></div>
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="var(--txt-lt)" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </a>
    <a href="{{ route('doctor.prescriptions.create', ['patient' => $prescription->patient_user_id]) }}" style="display:flex;align-items:center;justify-content:center;gap:7px;padding:10px;background:var(--ink);color:#fff;border-radius:10px;font-size:.875rem;font-weight:600;text-decoration:none">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        New Prescription
    </a>
</div>
</div>
@endsection
