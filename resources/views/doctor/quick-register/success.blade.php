@extends('layouts.doctor')
@section('title', 'Patient Registered')
@section('page-title', 'Patient Registered Successfully')

@section('content')
@php
    $name    = $patient->profile?->full_name ?? 'Patient';
    $age     = $patient->profile?->dob?->age;
    $gender  = ucfirst($patient->profile?->gender ?? '');
@endphp

<div class="fade-in" style="max-width:580px;margin:0 auto">

    {{-- ── Success banner ──────────────────────────────────────────────────── --}}
    <div style="background:linear-gradient(135deg,#3d7a6e 0%,#2a5e54 100%);border-radius:18px;padding:30px 32px;text-align:center;color:#fff;margin-bottom:22px;position:relative;overflow:hidden">
        <div style="position:absolute;right:-30px;top:-30px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.06)"></div>
        <div style="position:relative;z-index:1">
            <x-avatar name="{{ $name }}" :photo="$patient->profile?->profile_photo"
                       :size="60" :radius="30" bg="rgba(255,255,255,.2)" font-size="1.5rem"
                       style="margin:0 auto 14px" />
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.6rem;font-weight:500;margin-bottom:4px">
                {{ $name }}
            </div>
            @if($age || $gender)
            <div style="font-size:.85rem;color:rgba(255,255,255,.75)">
                {{ collect([$age ? $age.' years' : null, $gender])->filter()->implode(' · ') }}
            </div>
            @endif
            <div style="margin-top:14px;font-size:.8rem;color:rgba(255,255,255,.6)">
                ✓ Registered &amp; access granted
            </div>
        </div>
    </div>

    {{-- ── Sub-ID card ─────────────────────────────────────────────────────── --}}
    @if($selfSubId)
    <div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:14px;padding:20px 24px;margin-bottom:16px;text-align:center">
        <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--txt-lt);margin-bottom:8px">Patient Sub-ID</div>
        <div style="font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:500;color:var(--txt);letter-spacing:.08em;margin-bottom:10px">
            {{ $selfSubId }}
        </div>
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
            <button type="button"
                    onclick="navigator.clipboard.writeText('{{ $selfSubId }}').then(()=>{const b=this;b.textContent='✓ Copied!';setTimeout(()=>b.textContent='Copy ID',2000)})"
                    style="padding:7px 18px;border:1.5px solid var(--warm-bd);border-radius:9px;background:transparent;color:var(--txt-md);font-size:.8rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:background .12s"
                    onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
                Copy ID
            </button>
            <a href="https://wa.me/{{ ltrim($patient->country_code ?? '+91', '+') }}{{ $patient->mobile_number }}?text={{ urlencode($name."'s Naumah Clinic Sub-ID: ".$selfSubId." — share with your doctor for quick record lookup.") }}"
               target="_blank"
               style="padding:7px 18px;border:1.5px solid #bbf7d0;border-radius:9px;background:#f0fdf4;color:#15803d;font-size:.8rem;font-weight:600;text-decoration:none;transition:all .12s"
               onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'">
                📲 WhatsApp ID to Patient
            </a>
        </div>
        <div style="font-size:.72rem;color:var(--txt-lt);margin-top:10px">
            Share this ID with the patient — they can give it to any doctor for quick record lookup.
        </div>
    </div>
    @endif

    {{-- ── Patient details ─────────────────────────────────────────────────── --}}
    <div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:14px;padding:18px 22px;margin-bottom:16px">
        <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);margin-bottom:12px">Patient Details</div>
        @php
        $details = [
            'Mobile'       => $patient->country_code.' '.$patient->mobile_number,
            'Date of Birth'=> $patient->profile?->dob?->format('d M Y') ?? '—',
            'Blood Group'  => $patient->profile?->blood_group ?? '—',
            'City'         => $patient->profile?->city ?? '—',
            'Access Valid' => 'Until '.now()->addDays(config('medtech.access.duration_days',30))->format('d M Y'),
        ];
        @endphp
        @foreach($details as $k => $v)
        @if($v && $v !== '—')
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--warm-bd);font-size:.8125rem">
            <span style="color:var(--txt-lt)">{{ $k }}</span>
            <span style="color:var(--txt-md);font-weight:500">{{ $v }}</span>
        </div>
        @endif
        @endforeach
    </div>

    {{-- ── Next action buttons ──────────────────────────────────────────────── --}}
    {{-- ── Next action buttons ──────────────────────────────────────────────── --}}
    <div style="display:flex;flex-direction:column;gap:9px">

        {{-- Step 6: Start Visit Now — creates a real appointment for this patient --}}
        @if($activeVisit ?? null)
        <a href="{{ route('doctor.appointments.show', $activeVisit) }}"
           style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:#0f766e;color:#fff;border-radius:12px;text-decoration:none;transition:opacity .15s;box-shadow:0 2px 8px rgba(15,118,110,.25)"
           onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <div style="display:flex;align-items:center;gap:10px;text-align:left">
                <span style="font-size:1.3rem">🩺</span>
                <div>
                    <div style="font-weight:600;font-size:.9rem">Visit in progress</div>
                    <div style="font-size:.72rem;opacity:.8">Appointment #{{ $activeVisit->id }} · open to continue or mark complete</div>
                </div>
            </div>
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        @elseif($doctorFee > 0)
        <form method="POST" action="{{ route('doctor.quick-register.start-visit', $patient->id) }}"
              onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.style.opacity='.6';b.querySelector('[data-start-label]').textContent='Starting…';}">
            @csrf
            <button type="submit"
                    style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:#16a34a;color:#fff;border:none;border-radius:12px;text-decoration:none;cursor:pointer;font-family:'Outfit',sans-serif;transition:opacity .15s;box-shadow:0 2px 8px rgba(22,163,74,.25)"
                    onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                <div style="display:flex;align-items:center;gap:10px;text-align:left">
                    <span style="font-size:1.3rem">🚶</span>
                    <div>
                        <div style="font-weight:600;font-size:.9rem" data-start-label>Start Visit Now</div>
                        <div style="font-size:.72rem;opacity:.8">Create appointment · ₹{{ number_format($doctorFee, 0) }} fee · unpaid</div>
                    </div>
                </div>
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </form>
        @endif

        <a href="{{ route('doctor.records.create', $patient->id) }}"
           style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:var(--leaf);color:#fff;border-radius:12px;text-decoration:none;transition:opacity .15s"
           onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:1.3rem">📋</span>
                <div>
                    <div style="font-weight:600;font-size:.9rem">Create Medical Record</div>
                    <div style="font-size:.72rem;opacity:.8">Document today's visit</div>
                </div>
            </div>
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>

        <a href="{{ route('doctor.prescriptions.create', ['patient' => $patient->id]) }}"
           style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:#fff;border:1.5px solid var(--warm-bd);color:var(--txt);border-radius:12px;text-decoration:none;transition:background .12s"
           onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='#fff'">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:1.3rem">💊</span>
                <div>
                    <div style="font-weight:600;font-size:.9rem;color:var(--txt)">Write Prescription</div>
                    <div style="font-size:.72rem;color:var(--txt-lt)">Add medicines directly</div>
                </div>
            </div>
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--txt-lt)"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>

        <a href="{{ route('doctor.patients.history', $patient->id) }}"
           style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:#fff;border:1.5px solid var(--warm-bd);color:var(--txt);border-radius:12px;text-decoration:none;transition:background .12s"
           onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='#fff'">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:1.3rem">📁</span>
                <div>
                    <div style="font-weight:600;font-size:.9rem;color:var(--txt)">View Patient History</div>
                    <div style="font-size:.72rem;color:var(--txt-lt)">Records, prescriptions, vitals</div>
                </div>
            </div>
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--txt-lt)"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>

        <a href="{{ route('doctor.quick-register.create') }}"
           style="display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;border:1.5px dashed var(--warm-bd);border-radius:12px;color:var(--txt-lt);text-decoration:none;font-size:.8125rem;font-weight:500;transition:all .12s"
           onmouseover="this.style.borderColor='var(--leaf)';this.style.color='var(--leaf)'" onmouseout="this.style.borderColor='var(--warm-bd)';this.style.color='var(--txt-lt)'">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Register Another Patient
        </a>
    </div>

</div>
@endsection
