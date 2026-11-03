@extends('layouts.doctor')
@section('title', 'New Prescription')
@section('page-title')
    <a href="{{ route('doctor.prescriptions.index') }}" style="color:var(--txt-lt);text-decoration:none;font-size:.85rem;font-weight:400">Prescriptions</a>
    <span style="color:var(--txt-lt);margin:0 6px">/</span>
    New Prescription
@endsection

@push('styles')
<style>
    .rx-card { background:var(--cream);border:1px solid var(--warm-bd);border-radius:14px;margin-bottom:16px;overflow:visible;position:relative; }
    .rx-card.has-dropdown { z-index:200;isolation:isolate; }
    .rx-card-head { padding:13px 20px;border-bottom:1px solid var(--warm-bd);display:flex;align-items:center;gap:9px;font-family:'Cormorant Garamond',serif;font-size:1.05rem;font-weight:500;color:var(--txt); }
    .rx-card-body { padding:18px 20px; }
    .fg { display:grid;gap:14px; }
    .fg-2 { grid-template-columns:1fr 1fr; }
    .fg-3 { grid-template-columns:1fr 1fr 1fr; }
    .fg-4 { grid-template-columns:1fr 1fr 1fr 1fr; }
    label { display:block;font-size:.72rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:5px; }
    .inp { width:100%;padding:.55rem .8rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.875rem;color:var(--txt);background:var(--cream);outline:none;font-family:'Outfit',sans-serif;transition:border-color .15s; }
    .inp:focus { border-color:var(--leaf); }
    .inp.err   { border-color:#ef4444; }
    select.inp { cursor:pointer; }
    textarea.inp { resize:vertical;min-height:72px; }
    .med-row { background:var(--cream);border:1.5px solid var(--warm-bd);border-radius:12px;padding:14px 16px;margin-bottom:10px;position:relative;transition:border-color .15s,box-shadow .15s; }
    .med-row:focus-within { border-color:var(--sage);box-shadow:0 0 0 3px rgba(106,158,142,.1);z-index:20;isolation:isolate; }
    .med-row.has-dropdown { z-index:200;isolation:isolate; }
    .med-row-num { position:absolute;top:-10px;left:14px;font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--txt-lt);background:var(--cream);padding:0 6px; }
    .ac-wrap { position:relative;z-index:1; }
    .ac-wrap:focus-within { z-index:50; }
    .ac-list { position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--cream);border:1.5px solid var(--warm-bd);border-radius:10px;z-index:60;max-height:220px;overflow-y:auto;box-shadow:0 8px 30px rgba(0,0,0,.12); }
    .ac-item { padding:9px 12px;cursor:pointer;transition:background .1s;font-size:.875rem;border-bottom:1px solid var(--warm-bd);background:var(--cream); }
    .ac-item:last-child { border-bottom:none; }
    .ac-item:hover,.ac-item.hl { background:var(--parch); }
    .ac-item-name { font-weight:500;color:var(--txt); }
    .ac-item-meta { font-size:.72rem;color:var(--txt-lt);margin-top:2px; }
    .patient-chip { display:flex;align-items:center;gap:10px;background:var(--parch);border:1.5px solid var(--warm-bd);border-radius:10px;padding:10px 14px; }
    .p-ava { width:36px;height:36px;border-radius:9px;background:var(--ink);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.875rem;flex-shrink:0; }
    .add-med-btn { display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;border:1.5px dashed var(--warm-bd);border-radius:10px;background:transparent;cursor:pointer;color:var(--txt-md);font-size:.875rem;font-family:'Outfit',sans-serif;transition:all .15s; }
    .add-med-btn:hover { border-color:var(--leaf);color:var(--leaf);background:#f0faf8; }
    .action-bar { position:sticky;bottom:0;background:var(--cream);border-top:1px solid var(--warm-bd);padding:14px 20px;margin:0 -28px -28px;display:flex;justify-content:flex-end;gap:10px;z-index:30; }
    .btn-primary { display:flex;align-items:center;gap:7px;padding:10px 20px;background:var(--ink);color:#fff;border:none;border-radius:10px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:opacity .15s; }
    .btn-primary:hover { opacity:.88; }
    .btn-secondary { display:flex;align-items:center;gap:7px;padding:10px 18px;background:var(--cream);color:var(--leaf);border:1.5px solid var(--leaf);border-radius:10px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .15s; }
    .btn-secondary:hover { background:#edf6f4; }
    .btn-ghost { padding:10px 16px;background:transparent;color:var(--txt-md);border:1.5px solid var(--warm-bd);border-radius:10px;font-size:.875rem;font-weight:500;cursor:pointer;font-family:'Outfit',sans-serif; }
    @media (max-width:768px) { .fg-3 { grid-template-columns:1fr 1fr; } .fg-4 { grid-template-columns:1fr 1fr; } .action-bar { flex-wrap:wrap;margin:0 -16px -16px;padding:12px 16px; } }
    @media (max-width:500px) { .fg-2,.fg-3,.fg-4 { grid-template-columns:1fr; } .action-bar { position:static; } .action-bar > * { flex:1 1 100%; justify-content:center; } }
</style>
@endpush

@section('content')
<div style="margin-bottom:16px">
    <a href="{{ ($patient ?? null) ? route('doctor.patients.history', $patient->id) : route('doctor.prescriptions.index') }}"
       onclick="if (window.history.length > 1) { window.history.back(); return false; }"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid var(--warm-bd);border-radius:8px;color:var(--txt-md);text-decoration:none;font-size:.85rem;background:transparent;transition:background .12s"
       onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
        <span aria-hidden="true">&larr;</span> Back
    </a>
</div>

<form method="POST" action="{{ route('doctor.prescriptions.store') }}" id="rx-form"
      x-data="rxForm()" x-init="init()">
@csrf
<input type="hidden" name="action" id="form-action" value="view">
@if(isset($appointment))<input type="hidden" name="appointment_id" value="{{ $appointment->id }}">@endif

<div class="dr-grid-2col" style="display:grid;grid-template-columns:1fr 300px;gap:18px;align-items:start">
<div>

{{-- ① Patient --}}
<div class="rx-card" :class="(showPList && results.length > 0) ? 'has-dropdown' : ''">
    <div class="rx-card-head">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="var(--leaf)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Patient
    </div>
    <div class="rx-card-body">
        @if($patient)
        <input type="hidden" name="patient_user_id" value="{{ $patient->id }}">
        <div class="patient-chip">
            <div class="p-ava">{{ strtoupper(substr($patient->profile?->full_name ?? 'P', 0, 1)) }}</div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;color:var(--txt)">{{ $patient->profile?->full_name }}</div>
                <div style="font-size:.75rem;color:var(--txt-lt)">{{ $patient->country_code }} {{ $patient->mobile_number }}{{ $patient->profile?->age ? ' · Age ' . $patient->profile->age : '' }}{{ $patient->profile?->blood_group ? ' · ' . $patient->profile->blood_group : '' }}</div>
            </div>
            <a href="{{ route('doctor.prescriptions.create') }}" style="font-size:.75rem;color:var(--txt-lt);text-decoration:none;padding:4px 8px;border-radius:7px;border:1px solid var(--warm-bd)">Change</a>
        </div>
        @if($patient->familyMembers->count() > 0)
        <div style="margin-top:12px">
            <label>Prescribing For</label>
            <select name="family_member_id" class="inp">
                <option value="" @selected(!$familyMember)>{{ $patient->profile?->full_name }} (Self)</option>
                @foreach($patient->familyMembers as $m)
                <option value="{{ $m->id }}" @selected($familyMember && $m->id == $familyMember->id)>{{ $m->full_name }} ({{ ucfirst($m->relation) }}) — {{ $m->sub_id }}</option>
                @endforeach
            </select>
        </div>
        @endif
        @else {{-- $patient is null --}}
        <div class="ac-wrap">
            <input type="hidden" name="patient_user_id" id="patient_id_input" x-model="patientId">
            <input type="text" class="inp" placeholder="Search patient by name or mobile…"
                   autocomplete="off" x-model="patientQuery"
                   @input.debounce.300ms="searchPatients()"
                   @focus="showPList = results.length > 0"
                   :class="!patientId && submitted ? 'inp err' : 'inp'">
            <div x-show="showPList && results.length > 0" class="ac-list">
                <template x-for="(p, i) in results" :key="p.id">
                    <div class="ac-item" :class="acPIdx===i?'hl':''" @click="selectPatient(p)">
                        <div class="ac-item-name" x-text="p.name"></div>
                        <div class="ac-item-meta" x-text="p.mobile+(p.age?' · Age '+p.age:'')+(p.city?' · '+p.city:'')"></div>
                    </div>
                </template>
            </div>
        </div>
        <p x-show="!patientId && submitted" style="font-size:.75rem;color:#ef4444;margin-top:4px">Please select a patient.</p>
        @endif
    </div>
</div>

{{-- ② Visit Details --}}
<div class="rx-card">
    <div class="rx-card-head">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="var(--leaf)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Visit Details
    </div>
    <div class="rx-card-body">
        <div class="fg fg-2" style="margin-bottom:14px">
            <div>
                <label>Date *</label>
                <input type="date" name="prescribed_date" class="inp" value="{{ old('prescribed_date', today()->format('Y-m-d')) }}" required>
            </div>
            <div>
                @if(isset($appointment))
                <label>Appointment</label>
                <input type="text" class="inp" value="{{ $appointment->appointment_number }} — {{ $appointment->slot_datetime->format('d M Y h:i A') }}" disabled>
                @endif
            </div>
        </div>
        <div class="fg" style="margin-bottom:14px">
            <div>
                <label>Chief Complaint</label>
                <input type="text" name="chief_complaint" class="inp" value="{{ old('chief_complaint') }}" placeholder="e.g. Fever with cough for 3 days">
            </div>
        </div>
        <div class="fg">
            <div>
                <label>Diagnosis / Impression</label>
                <input type="text" name="diagnosis" class="inp" value="{{ old('diagnosis') }}" placeholder="e.g. Acute viral upper respiratory tract infection">
            </div>
        </div>
    </div>
</div>

{{-- ③ Medicines --}}
<div class="rx-card">
    <div class="rx-card-head" style="justify-content:space-between">
        <div style="display:flex;align-items:center;gap:9px">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="var(--leaf)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            Medicines
        </div>
        <span style="font-size:.75rem;color:var(--txt-lt)" x-text="medicines.length + ' added'"></span>
    </div>
    <div class="rx-card-body" style="padding-bottom:10px">
        <template x-for="(med, idx) in medicines" :key="med.key">
            <div class="med-row" :class="med.acList.length > 0 ? 'has-dropdown' : ''">
                <div class="med-row-num" x-text="'Medicine ' + (idx+1)"></div>
                <div style="display:flex;align-items:flex-start;gap:10px">
                    <div style="flex:1;min-width:0">
                        <div class="fg fg-2" style="margin-bottom:10px">
                            <div class="ac-wrap">
                                <label>Medicine Name *</label>
                                <input type="text" :name="'medicines['+idx+'][medicine_name]'"
                                       class="inp" placeholder="e.g. Paracetamol…"
                                       x-model="med.medicine_name" autocomplete="off"
                                       @input.debounce.200ms="searchMed(idx, $event.target.value)"
                                       @keydown.arrow-down.prevent="med.acIdx = Math.min(med.acIdx+1, med.acList.length-1)"
                                       @keydown.arrow-up.prevent="med.acIdx = Math.max(med.acIdx-1, 0)"
                                       @keydown.enter.prevent="if(med.acList[med.acIdx]) fillMed(idx, med.acList[med.acIdx])"
                                       @keydown.escape="med.acList=[]"
                                       :class="!med.medicine_name && submitted ? 'inp err' : 'inp'">
                                <div x-show="med.acList.length > 0" class="ac-list">
                                    <template x-for="(s, si) in med.acList" :key="si">
                                        <div class="ac-item" :class="med.acIdx===si?'hl':''" @mousedown.prevent="fillMed(idx, s)">
                                            <div style="display:flex;align-items:center;gap:5px">
                                                <span class="ac-item-name" x-text="s.medicine_name"></span>
                                                <span x-show="s._mine" style="font-size:.62rem;font-weight:700;color:var(--leaf,#3d7a5c);background:rgba(61,122,92,.1);padding:1px 5px;border-radius:4px;letter-spacing:.04em">★ MY LIST</span>
                                            </div>
                                            <div class="ac-item-meta" x-text="[s.form,s.dosage,s.frequency].filter(Boolean).join(' · ')"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label>Form</label>
                                <select :name="'medicines['+idx+'][form]'" class="inp" x-model="med.form">
                                    <option value="">Select…</option>
                                    <option>Tablet</option><option>Capsule</option><option>Syrup</option>
                                    <option>Drops</option><option>Injection</option><option>Cream</option>
                                    <option>Ointment</option><option>Inhaler</option><option>Sachet</option>
                                    <option>Suppository</option><option>Patch</option><option>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="fg fg-4" style="margin-bottom:10px">
                            <div>
                                <label>Dosage</label>
                                <input type="text" :name="'medicines['+idx+'][dosage]'" class="inp" placeholder="500mg…" x-model="med.dosage">
                            </div>
                            <div>
                                <label>Frequency</label>
                                <select :name="'medicines['+idx+'][frequency]'" class="inp" x-model="med.frequency">
                                    <option value="">—</option>
                                    <option>Once daily</option><option>Twice daily</option>
                                    <option>Three times daily</option><option>Four times daily</option>
                                    <option>Every 6 hours</option><option>Every 8 hours</option>
                                    <option>Every 12 hours</option><option>Once weekly</option>
                                    <option>As needed (SOS)</option><option>At bedtime</option>
                                </select>
                            </div>
                            <div>
                                <label>Duration (days)</label>
                                <input type="number" :name="'medicines['+idx+'][duration_days]'" class="inp" placeholder="5" min="1" max="365" x-model="med.duration_days">
                            </div>
                            <div>
                                <label>Timing</label>
                                <select :name="'medicines['+idx+'][timing]'" class="inp" x-model="med.timing">
                                    <option value="">—</option>
                                    <option value="before_food">Before food</option>
                                    <option value="after_food">After food</option>
                                    <option value="with_food">With food</option>
                                    <option value="empty_stomach">Empty stomach</option>
                                    <option value="bed_time">Bedtime</option>
                                    <option value="any_time">Anytime</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label>Special Instructions</label>
                            <input type="text" :name="'medicines['+idx+'][special_instructions]'" class="inp" placeholder="e.g. Avoid in case of allergy…" x-model="med.special_instructions">
                        </div>
                        <div style="margin-top:8px;display:flex;align-items:center;gap:10px" x-show="med.medicine_name.trim() && !isMine(med.medicine_name) && !med._addedToList">
                            <button type="button" @click="addToMyList(idx)" :disabled="med._addingToList"
                                    style="font-size:.72rem;font-weight:600;padding:4px 12px;border-radius:7px;border:1.5px solid var(--leaf);background:transparent;color:var(--leaf);cursor:pointer;font-family:'Outfit',sans-serif;transition:all .15s"
                                    :style="med._addingToList ? 'opacity:.6;cursor:not-allowed' : 'opacity:1'"
                                    x-text="med._addingToList ? 'Adding…' : '＋ Add to My List'"></button>
                            <span x-show="med._addError" style="font-size:.72rem;color:#ef4444">Failed to add. Try again.</span>
                        </div>
                        <div style="margin-top:8px" x-show="med._addedToList">
                            <span style="font-size:.72rem;font-weight:600;color:var(--leaf)">✓ Added to My List</span>
                        </div>
                    </div>
                    <button type="button" @click="removeMed(idx)" x-show="medicines.length > 1"
                            style="flex-shrink:0;margin-top:20px;width:28px;height:28px;border-radius:7px;border:1.5px solid #fecaca;background:transparent;color:#ef4444;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s"
                            onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </template>
        <button type="button" @click="addMed()" class="add-med-btn">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Another Medicine
        </button>
    </div>
</div>

{{-- ④ Notes & Follow-up --}}
<div class="rx-card">
    <div class="rx-card-head">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="var(--leaf)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        Notes &amp; Follow-up
    </div>
    <div class="rx-card-body">
        <div class="fg" style="margin-bottom:14px">
            <div>
                <label>Advice to Patient</label>
                <textarea name="notes" class="inp" rows="3" placeholder="Rest well, drink plenty of fluids…">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="fg fg-2">
            <div>
                <label>Follow-up Instructions</label>
                <input type="text" name="follow_up_instructions" class="inp" value="{{ old('follow_up_instructions') }}" placeholder="Return if fever persists beyond 3 days">
            </div>
            <div>
                <label>Follow-up Date</label>
                <input type="date" name="follow_up_date" class="inp" value="{{ old('follow_up_date') }}" min="{{ today()->addDay()->format('Y-m-d') }}">
            </div>
        </div>
    </div>
</div>

</div>

{{-- RIGHT: Letterhead + summary + presets --}}
<div class="dr-sidebar-sticky" style="position:sticky;top:calc(var(--topbar-h) + 16px)">
    <div style="background:var(--cream);border:1px solid var(--warm-bd);border-radius:14px;overflow:hidden;margin-bottom:14px">
        <div style="background:var(--ink);padding:16px 18px">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;color:#fff;font-weight:500">{{ $profile?->clinic_name ?? 'My Clinic' }}</div>
            <div style="font-size:.75rem;color:rgba(255,255,255,.6);margin-top:3px">{{ $profile?->clinic_address ?? '' }}{{ $profile?->clinic_city ? ', ' . $profile->clinic_city : '' }}</div>
        </div>
        <div style="padding:14px 18px;border-bottom:1px solid var(--warm-bd)">
            <div style="font-size:.8rem;font-weight:600;color:var(--txt)">Dr. {{ auth()->user()->profile?->full_name ?? 'Doctor' }}</div>
            <div style="font-size:.72rem;color:var(--txt-lt);margin-top:2px">{{ $profile?->qualification ?? '' }}{{ $profile?->specialization ? ' · ' . $profile->specialization : '' }}</div>
            @if($profile?->registration_number)<div style="font-size:.7rem;color:var(--txt-lt);margin-top:1px">Reg: {{ $profile->registration_number }}</div>@endif
        </div>
        <div style="padding:12px 18px;font-size:.72rem;color:var(--txt-lt)">
            @if($profile?->clinic_city)📍 {{ $profile->clinic_city }}, {{ $profile->clinic_state }} &nbsp;@endif
            @if(auth()->user()->mobile_number)📱 {{ auth()->user()->country_code }}{{ auth()->user()->mobile_number }}@endif
        </div>
    </div>

    <div style="background:var(--cream);border:1px solid var(--warm-bd);border-radius:12px;padding:14px 16px;margin-bottom:14px">
        <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:8px">Medicines Added</div>
        <template x-for="(m, i) in medicines.slice(0, 6)" :key="i">
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--parch);font-size:.78rem">
                <span x-text="m.medicine_name || '—'" style="color:var(--txt)"></span>
                <span x-text="m.duration_days ? m.duration_days+'d' : '—'" style="color:var(--txt-lt)"></span>
            </div>
        </template>
        <div x-show="medicines.length > 6" style="text-align:center;padding-top:4px;font-size:.72rem;color:var(--txt-lt)" x-text="'+ '+(medicines.length-6)+' more'"></div>
    </div>

    <div style="background:var(--cream);border:1px solid var(--warm-bd);border-radius:12px;padding:14px 16px">
        <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:10px">Quick Combos</div>
        @foreach([
            ['name' => 'Fever / Viral', 'meds' => [['medicine_name' => 'Paracetamol','dosage' => '500mg','form' => 'Tablet','frequency' => 'Three times daily','duration_days' => 5,'timing' => 'after_food'],['medicine_name' => 'Cetirizine','dosage' => '10mg','form' => 'Tablet','frequency' => 'Once daily','duration_days' => 5,'timing' => 'bed_time']]],
            ['name' => 'Throat / Cough', 'meds' => [['medicine_name' => 'Amoxicillin','dosage' => '500mg','form' => 'Capsule','frequency' => 'Twice daily','duration_days' => 5,'timing' => 'after_food'],['medicine_name' => 'Dextromethorphan Syrup','dosage' => '10ml','form' => 'Syrup','frequency' => 'Three times daily','duration_days' => 5,'timing' => 'after_food']]],
            ['name' => 'Gastritis', 'meds' => [['medicine_name' => 'Pantoprazole','dosage' => '40mg','form' => 'Tablet','frequency' => 'Once daily','duration_days' => 7,'timing' => 'before_food'],['medicine_name' => 'Domperidone','dosage' => '10mg','form' => 'Tablet','frequency' => 'Three times daily','duration_days' => 5,'timing' => 'before_food']]],
            ['name' => 'BP / Hypertension', 'meds' => [['medicine_name' => 'Amlodipine','dosage' => '5mg','form' => 'Tablet','frequency' => 'Once daily','duration_days' => 30,'timing' => 'after_food'],['medicine_name' => 'Telmisartan','dosage' => '40mg','form' => 'Tablet','frequency' => 'Once daily','duration_days' => 30,'timing' => 'after_food']]],
        ] as $preset)
        <button type="button" @click="loadPreset({{ json_encode($preset['meds']) }})"
                style="display:block;width:100%;text-align:left;padding:7px 10px;border-radius:8px;border:1px solid var(--warm-bd);background:transparent;cursor:pointer;font-size:.8rem;color:var(--txt-md);font-family:'Outfit',sans-serif;margin-bottom:6px;transition:all .15s"
                onmouseover="this.style.background='var(--parch)';this.style.color='var(--txt)'" onmouseout="this.style.background='transparent';this.style.color='var(--txt-md)'">
            {{ $preset['name'] }} <span style="font-size:.68rem;color:var(--txt-lt)">· {{ count($preset['meds']) }} meds</span>
        </button>
        @endforeach
    </div>
</div>

</div>

<div class="action-bar">
    <a href="{{ route('doctor.prescriptions.index') }}" class="btn-ghost">Cancel</a>
    <button type="button" class="btn-secondary" @click="submitForm('view')">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Save Prescription
    </button>
    <button type="button" class="btn-primary" @click="submitForm('send_whatsapp')">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        Save &amp; Send WhatsApp
    </button>
</div>
</form>
@endsection

@push('scripts')
@php
    $selectedPatientData = $patient ? [
        'id'     => $patient->id,
        'name'   => $patient->profile?->full_name,
        'mobile' => $patient->country_code . $patient->mobile_number,
        'age'    => $patient->profile?->age,
    ] : null;
@endphp
<script>
const RECENT_MEDS = @json($recentMedicines);
const MY_MEDS     = @json($myMedicines);
function rxForm() {
    return {
        patientId: '{{ $patient?->id ?? "" }}',
        patientQuery: '{{ $patient?->profile?->full_name ?? "" }}',
        selectedPatient: @json($selectedPatientData),
        results: [], showPList: false, acPIdx: 0,
        medicines: [newMed()],
        submitted: false,
        init() {
            document.addEventListener('click', (e) => {
                if (e.target.closest('.ac-wrap')) return;
                this.showPList = false;
                this.medicines.forEach(m => m.acList = []);
            });
        },
        async searchPatients() {
            if (this.patientQuery.length < 2) { this.results = []; return; }
            const r = await fetch(`{{ route('doctor.patients.search') }}?q=${encodeURIComponent(this.patientQuery)}&type=any`);
            const d = await r.json();
            this.results = d.found ? (d.patients || [d.patient]) : [];
            this.showPList = this.results.length > 0;
        },
        selectPatient(p) { this.patientId = p.id; this.patientQuery = p.name; this.selectedPatient = p; this.showPList = false; document.getElementById('patient_id_input').value = p.id; },
        clearPatient() { this.patientId = ''; this.patientQuery = ''; this.selectedPatient = null; },
        addMed() { this.medicines.push(newMed()); },
        removeMed(i) { this.medicines.splice(i, 1); },
        searchMed(idx, val) {
            const med = this.medicines[idx];
            med._addedToList = false;
            med._addingToList = false;
            med._addError = false;
            if (!val || val.length < 2) { med.acList = []; return; }
            const q = val.toLowerCase();
            const mine = MY_MEDS.filter(m => m.medicine_name.toLowerCase().includes(q))
                                 .map(m => ({ ...m, _mine: true }));
            const mineNames = new Set(mine.map(m => m.medicine_name.toLowerCase()));
            const recent = RECENT_MEDS.filter(m => m.medicine_name.toLowerCase().includes(q) && !mineNames.has(m.medicine_name.toLowerCase()))
                                       .slice(0, 8 - mine.length);
            med.acList = [...mine.slice(0, 8), ...recent].slice(0, 10);
            med.acIdx = 0;
        },
        fillMed(idx, s) {
            const m = this.medicines[idx];
            Object.assign(m, { medicine_name: s.medicine_name, generic_name: s.generic_name||'', form: s.form||'', dosage: s.dosage||'', frequency: s.frequency||'', timing: s.timing||'', duration_days: s.duration_days||'', acList: [], _addedToList: false, _addingToList: false, _addError: false });
        },
        loadPreset(meds) { this.medicines = meds.map((m, i) => ({ ...newMed(), ...m, key: Date.now()+i })); },
        submitForm(action) {
            this.submitted = true;
            @if(!$patient) if (!this.patientId) { return; } @endif
            if (this.medicines.some(m => !m.medicine_name.trim())) { alert('Please fill in all medicine names.'); return; }
            document.getElementById('form-action').value = action;
            document.getElementById('rx-form').submit();
        },
        isMine(name) {
            if (!name) return false;
            const n = name.trim().toLowerCase();
            return MY_MEDS.some(m => m.medicine_name.toLowerCase() === n);
        },
        async addToMyList(idx) {
            const med = this.medicines[idx];
            med._addingToList = true;
            med._addError = false;
            try {
                const token = document.querySelector('input[name="_token"]').value;
                const body = new FormData();
                body.append('_token', token);
                body.append('medicine_name', med.medicine_name);
                if (med.generic_name)         body.append('generic_name', med.generic_name);
                if (med.form)                 body.append('form', med.form);
                if (med.dosage)               body.append('dosage', med.dosage);
                if (med.frequency)            body.append('frequency', med.frequency);
                if (med.timing)               body.append('timing', med.timing);
                if (med.duration_days)        body.append('duration_days', med.duration_days);
                if (med.special_instructions) body.append('special_instructions', med.special_instructions);
                const r = await fetch("{{ route('doctor.medicines.store') }}", {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                    body,
                });
                const data = await r.json();
                if (data.success) {
                    MY_MEDS.push(data.medicine);
                    med._addedToList = true;
                } else {
                    med._addError = true;
                }
            } catch(e) {
                med._addError = true;
            }
            med._addingToList = false;
        },
    };
}
function newMed() { return { key: Date.now()+Math.random(), medicine_name:'', generic_name:'', form:'', dosage:'', frequency:'', duration_days:'', timing:'', special_instructions:'', acList:[], acIdx:0, _addingToList:false, _addedToList:false, _addError:false }; }
</script>
@endpush
