@extends('layouts.doctor')
@section('title', ($patient->profile?->full_name ?? 'Patient') . ' — History')
@section('page-title')
    <a href="{{ route('doctor.patients.index') }}" style="color:var(--txt-lt);text-decoration:none;font-size:.85rem;font-weight:400">Patients</a>
    <span style="color:var(--txt-lt);margin:0 6px">/</span>
    {{ $patient->profile?->full_name ?? 'Patient' }}
@endsection

@section('content')
<div style="margin-bottom:16px">
    <a href="{{ route('doctor.patients.index') }}"
       onclick="if (window.history.length > 1) { window.history.back(); return false; }"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid var(--warm-bd);border-radius:8px;color:var(--txt-md);text-decoration:none;font-size:.85rem">
        <span aria-hidden="true">&larr;</span> Back
    </a>
</div>
@php
    $name     = $patient->profile?->full_name ?? 'Unknown';
    $colors   = ['#3d7a6e','#7a6e3d','#6e3d7a','#3d607a','#7a3d4a'];
    $color    = $colors[$patient->id % count($colors)];

    $tabs = ['records', 'prescriptions', 'vitals', 'timelines'];
    $activeTab = request()->get('tab', 'records');
@endphp

{{-- ── Patient Header Card ─────────────────────────────────────────────────── -- --}}
<div class="panel fade-in" style="margin-bottom:20px">
    <div style="padding:20px 24px;display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap">

        {{-- Avatar --}}
        <x-avatar name="{{ $name }}" :photo="$patient->profile?->profile_photo"
                   :size="56" :radius="14" :bg="$color" font-size="1.25rem" />

        {{-- Identity --}}
        <div style="flex:1;min-width:200px">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
                <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:500;color:var(--txt)">
                    {{ $name }}
                </h2>
                @if($hasAccess)
                    <span style="font-size:.68rem;font-weight:700;padding:3px 9px;border-radius:20px;background:#e8f5f3;color:#1a7a6a;letter-spacing:.04em">
                        ● ACCESS ACTIVE
                    </span>
                @else
                    <span style="font-size:.68rem;font-weight:700;padding:3px 9px;border-radius:20px;background:var(--parch);color:var(--txt-lt);letter-spacing:.04em">
                        NO ACCESS
                    </span>
                @endif
            </div>
            <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:.8rem;color:var(--txt-md)">
                @if($patient->profile?->age)         <span>Age {{ $patient->profile->age }}</span> @endif
                @if($patient->profile?->gender)       <span>· {{ ucfirst($patient->profile->gender) }}</span> @endif
                @if($patient->profile?->blood_group)  <span>· {{ $patient->profile->blood_group }}</span> @endif
                @if($patient->profile?->city)         <span>· {{ $patient->profile->city }}, {{ $patient->profile?->state }}</span> @endif
                <span>· {{ $patient->country_code }} {{ $patient->mobile_number }}</span>
            </div>
            @if($patient->familyMembers->count() > 0)
            <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap">
                <a href="{{ route('doctor.patients.history', $patient->id) }}"
                   style="font-size:.75rem;font-weight:500;padding:4px 10px;border-radius:20px;border:1px solid;text-decoration:none;transition:all .12s;
                          {{ !$familyMemberId ? 'background:var(--ink);color:#fff;border-color:var(--ink)' : 'background:transparent;color:var(--txt-md);border-color:var(--warm-bd)' }}">
                    Self
                </a>
                @foreach($patient->familyMembers as $member)
                <a href="{{ route('doctor.patients.history', $patient->id) }}?member={{ $member->id }}&tab={{ $activeTab }}"
                   style="font-size:.75rem;font-weight:500;padding:4px 10px;border-radius:20px;border:1px solid;text-decoration:none;transition:all .12s;
                          {{ $familyMemberId == $member->id ? 'background:var(--ink);color:#fff;border-color:var(--ink)' : 'background:transparent;color:var(--txt-md);border-color:var(--warm-bd)' }}">
                    {{ $member->full_name }}
                    <span style="font-size:.65rem;opacity:.7">{{ $member->sub_id }}</span>
                </a>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Right: Stats + Actions --}}
        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
            @if($hasAccess)
            <div style="display:flex;gap:10px">
                @foreach(['total_visits' => 'Visits', 'total_rx' => 'Rx', 'my_visits' => 'My Visits'] as $k => $lbl)
                <div style="text-align:center;min-width:52px">
                    <div style="font-family:'Cormorant Garamond',serif;font-size:1.6rem;font-weight:500;color:var(--txt);line-height:1">
                        {{ $stats[$k] ?? 0 }}
                    </div>
                    <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);font-weight:600;margin-top:2px">{{ $lbl }}</div>
                </div>
                @if(!$loop->last) <div style="width:1px;background:var(--warm-bd);height:36px;align-self:center"></div> @endif
                @endforeach
            </div>
            <div style="width:1px;background:var(--warm-bd);height:36px"></div>
            @endif

            <div style="display:flex;gap:7px;flex-wrap:wrap">
                @if($hasAccess)
                @if($activeVisit)
                <a href="{{ route('doctor.appointments.show', $activeVisit) }}"
                   style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:#0f766e;color:#fff;border-radius:9px;font-size:.8rem;font-weight:600;text-decoration:none">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    Visit in Progress
                </a>
                @else
                <form method="POST" action="{{ route('doctor.quick-register.start-visit', $patient->id) }}"
                      style="display:flex"
                      onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.style.opacity='.6';b.textContent='Starting…';}">
                    @csrf
                    @if($viewingMember)
                    <input type="hidden" name="family_member_id" value="{{ $viewingMember->id }}">
                    @endif
                    <button type="submit"
                            style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:#16a34a;color:#fff;border:none;border-radius:9px;font-size:.8rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:opacity .15s"
                            onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Start Visit
                    </button>
                </form>
                @endif
                <a href="{{ route('doctor.prescriptions.create', ['patient' => $patient->id]) }}"
                   style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:var(--ink);color:#fff;border-radius:9px;font-size:.8rem;font-weight:600;text-decoration:none">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New Rx
                </a>
                <a href="{{ route('doctor.records.create', ['patient' => $patient->id]) }}"
                   style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:var(--leaf);color:#fff;border-radius:9px;font-size:.8rem;font-weight:600;text-decoration:none">
                    Add Record
                </a>
                @endif
                @if($accessGrant)
                <div style="display:flex;flex-direction:column;justify-content:center">
                    <div style="font-size:.68rem;color:var(--txt-lt)">Access expires</div>
                    <div style="font-size:.75rem;font-weight:600;color:var(--txt-md)">{{ $accessGrant->access_expires_at->format('d M Y') }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── No Access Gate ───────────────────────────────────────────────────────── -- --}}
@if(!$hasAccess)
<div style="background:linear-gradient(135deg,#fdf8f5 0%,#f7f0ea 100%);border:1.5px solid var(--warm-bd);border-radius:16px;padding:40px 32px;text-align:center;max-width:520px;margin:0 auto">
    <div style="width:60px;height:60px;background:#fff;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;border:1px solid var(--warm-bd)">
        <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="var(--txt-lt)" stroke-width="1.5">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
    </div>
    <div style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;font-weight:500;color:var(--txt);margin-bottom:8px">
        Access Required
    </div>
    <p style="font-size:.875rem;color:var(--txt-md);margin-bottom:20px;line-height:1.6">
        You don't have active access to {{ $name }}'s medical records.
        @if($pendingReq)
        <br><strong style="color:var(--coral)">An access request is pending — ask the patient for their OTP.</strong>
        @endif
    </p>
    @if($pendingReq)
    <div x-data="{ digits: ['','','','','',''], verifying: false, err: '' }" style="margin-bottom:18px">
        <p style="font-size:.8rem;color:var(--txt-md);margin-bottom:10px">Enter the 6-digit OTP the patient received on WhatsApp:</p>
        <div style="display:flex;justify-content:center;gap:7px;margin-bottom:10px">
            <template x-for="(d,i) in digits" :key="i">
                <input type="tel" inputmode="numeric" maxlength="1" :value="d"
                       style="width:42px;height:50px;text-align:center;font-size:1.3rem;font-weight:600;border:1.5px solid var(--warm-bd);border-radius:9px;outline:none;font-family:'Outfit',sans-serif"
                       @focus="$el.style.borderColor='var(--leaf)'" @blur="$el.style.borderColor='var(--warm-bd)'"
                       @input="v=$event.target.value.replace(/\D/g,'').slice(-1);digits[i]=v;if(v&&i<5)$nextTick(()=>$el.nextElementSibling?.focus())"
                       @keydown.backspace="if(digits[i]){digits[i]=''}else if(i>0){digits[i-1]='';$nextTick(()=>$el.previousElementSibling?.focus())}else{$event.preventDefault()}">
            </template>
        </div>
        <p x-show="err" x-text="err" style="font-size:.78rem;color:#dc2626;margin-bottom:8px"></p>
        <button @click="
            verifying=true; err='';
            fetch('{{ route('doctor.patients.verify-otp', $pendingReq) }}', {
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                body:JSON.stringify({otp:digits.join('')})
            }).then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else { err=d.message||'Wrong OTP.'; digits=['','','','','','']; } }).finally(()=>verifying=false)"
                :disabled="verifying || digits.join('').length < 6"
                style="padding:9px 24px;background:var(--leaf);color:#fff;border:none;border-radius:9px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif"
                :style="(verifying||digits.join('').length<6)?'opacity:.5;cursor:not-allowed':''">
            <span x-text="verifying?'Verifying…':'Verify OTP & Access Records'"></span>
        </button>
    </div>
    @else
    <a href="{{ route('doctor.patients.index') }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:10px 22px;background:var(--ink);color:#fff;border-radius:10px;font-size:.875rem;font-weight:600;text-decoration:none">
        Request Access
    </a>
    @endif
</div>

@else
{{-- ── Tabs ─────────────────────────────────────────────────────────────────── -- --}}
<div style="display:flex;gap:2px;border-bottom:2px solid var(--warm-bd);margin-bottom:20px;overflow-x:auto;-webkit-overflow-scrolling:touch">
    @foreach(['records' => 'Medical Records', 'prescriptions' => 'Prescriptions', 'vitals' => 'Vitals & Charts', 'timelines' => 'Care Timelines', 'documents' => 'Documents'] as $t => $lbl)
    <a href="{{ route('doctor.patients.history', $patient->id) }}?tab={{ $t }}{{ $familyMemberId ? '&member='.$familyMemberId : '' }}"
       style="padding:10px 18px;font-size:.875rem;font-weight:500;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;white-space:nowrap;flex-shrink:0;
              {{ $activeTab === $t ? 'color:var(--ink);border-bottom-color:var(--ink);font-weight:600' : 'color:var(--txt-lt)' }}"
       onmouseover="if('{{ $activeTab }}' !== '{{ $t }}') this.style.color='var(--txt)'" onmouseout="if('{{ $activeTab }}' !== '{{ $t }}') this.style.color='var(--txt-lt)'">
        {{ $lbl }}
        @if($t === 'records' && $records->total() > 0)
            <span style="font-size:.65rem;background:var(--parch);color:var(--txt-lt);padding:1px 6px;border-radius:20px;margin-left:4px">{{ $records->total() }}</span>
        @endif
        @if($t === 'prescriptions' && $prescriptions->total() > 0)
            <span style="font-size:.65rem;background:var(--parch);color:var(--txt-lt);padding:1px 6px;border-radius:20px;margin-left:4px">{{ $prescriptions->total() }}</span>
        @endif
        @if($t === 'documents' && $patientDocuments->isNotEmpty())
            <span style="font-size:.65rem;background:#f4f0fa;color:#4a3760;padding:1px 6px;border-radius:20px;margin-left:4px">{{ $patientDocuments->count() }}</span>
        @endif
    </a>
    @endforeach
</div>

{{-- ═══════════════════════════ TAB: MEDICAL RECORDS ════════════════════════ -- --}}
@if($activeTab === 'records')
@if($records->isEmpty())
<div style="text-align:center;padding:52px 24px;color:var(--txt-lt)">
    <div style="width:48px;height:48px;border-radius:12px;background:var(--parch);display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    </div>
    <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt-md)">No medical records yet</div>
    <p style="font-size:.8rem;margin-top:4px">Add the first visit record for this patient.</p>
    <a href="{{ route('doctor.records.create', ['patient' => $patient->id]) }}"
       style="display:inline-flex;align-items:center;gap:6px;margin-top:14px;padding:9px 18px;background:var(--ink);color:#fff;border-radius:9px;font-size:.875rem;font-weight:600;text-decoration:none">
        + Add Record
    </a>
</div>
@else
@foreach($records as $record)
<div class="panel" style="margin-bottom:14px;transition:box-shadow .15s" onmouseover="this.style.boxShadow='0 4px 20px rgba(0,0,0,.06)'" onmouseout="this.style.boxShadow='none'">
    {{-- Record header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:16px 20px;gap:12px;flex-wrap:wrap">
        <div style="display:flex;gap:12px;align-items:flex-start;flex:1;min-width:0">
            {{-- Date column --}}
            <div style="text-align:center;min-width:44px;flex-shrink:0">
                <div style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;font-weight:500;color:var(--txt);line-height:1">
                    {{ $record->visit_date->format('d') }}
                </div>
                <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);font-weight:600">
                    {{ $record->visit_date->format('M Y') }}
                </div>
            </div>
            <div style="width:1px;background:var(--warm-bd);height:40px;flex-shrink:0;margin-top:3px"></div>

            {{-- Content --}}
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:5px">
                    <span style="font-size:.875rem;font-weight:600;color:var(--txt)">
                        {{ $record->visit_type ? ucwords(str_replace('_', ' ', $record->visit_type)) : 'Consultation' }}
                    </span>
                    @if($record->doctor_user_id !== auth()->id())
                    <span style="font-size:.7rem;padding:2px 8px;background:var(--parch);border-radius:20px;color:var(--txt-lt)">
                        By Dr. {{ $record->doctor?->profile?->full_name ?? 'Other' }}
                    </span>
                    @else
                    <span style="font-size:.7rem;padding:2px 8px;background:#e8f5f3;border-radius:20px;color:#1a7a6a">
                        By You
                    </span>
                    @endif
                </div>

                @if($record->chief_complaint)
                <div style="font-size:.8125rem;color:var(--txt-md);margin-bottom:6px">
                    <span style="color:var(--txt-lt);font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Chief Complaint: </span>
                    {{ $record->chief_complaint }}
                </div>
                @endif

                @if($record->diagnosis)
                <div style="font-size:.8125rem;color:var(--txt);font-weight:500;margin-bottom:5px">
                    <span style="color:var(--txt-lt);font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Diagnosis: </span>
                    {{ $record->diagnosis }}
                </div>
                @endif

                @if($record->notes)
                <div style="font-size:.8rem;color:var(--txt-md);margin-top:5px;padding:8px 12px;background:var(--parch);border-radius:8px;border-left:3px solid var(--warm-bd)">
                    {{ Str::limit($record->notes, 200) }}
                </div>
                @endif

                {{-- Vitals inline --}}
                @if($record->vitals && count($record->vitals) > 0)
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px">
                    @foreach($record->vitals as $key => $val)
                    @if($val)
                    <span style="font-size:.72rem;padding:3px 9px;background:var(--parch);border-radius:20px;color:var(--txt-md);border:1px solid var(--warm-bd)">
                        {{ ucwords(str_replace('_', ' ', $key)) }}: <strong style="color:var(--txt)">{{ $val }}</strong>
                    </span>
                    @endif
                    @endforeach
                </div>
                @endif

                {{-- Attachments --}}
                @if($record->attachments && count($record->attachments) > 0)
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
                    @foreach($record->attachments as $att)
                    @php
                        $ap      = explode('/', str_replace('medical-records/', '', $att['path'] ?? ''), 2);
                        $attUrl  = route('attachments.medical-record', ['patientId' => $ap[0] ?? '', 'filename' => $ap[1] ?? '']);
                        $attName = $att['name'] ?? 'Attachment';
                        $attType = str_contains($att['type'] ?? '', 'pdf') ? 'pdf' : (str_starts_with($att['type'] ?? '', 'image/') ? 'image' : 'other');
                    @endphp
                    <a href="javascript:void(0)" x-data
                       @click="$store.attachmentPreview.show(@js($attUrl), @js($attUrl), @js($attName), @js($attType))"
                       style="display:flex;align-items:center;gap:5px;font-size:.72rem;padding:3px 9px;border:1px solid var(--warm-bd);border-radius:7px;color:var(--txt-md);text-decoration:none;background:var(--cream)"
                       onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='var(--cream)'">
                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        {{ $attName }}
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div style="display:flex;gap:6px;flex-shrink:0">
            @if($record->prescriptions->count() > 0)
            <span style="display:flex;align-items:center;gap:4px;font-size:.72rem;padding:4px 9px;background:var(--parch);border-radius:7px;color:var(--txt-md)">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg>
                {{ $record->prescriptions->count() }} Rx
            </span>
            @endif
            <a href="{{ route('doctor.records.show', $record) }}"
               style="font-size:.78rem;font-weight:500;padding:5px 10px;border:1.5px solid var(--warm-bd);border-radius:8px;color:var(--txt-md);text-decoration:none;transition:all .12s"
               onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
                Full Record →
            </a>
        </div>
    </div>
</div>
@endforeach

{{-- Pagination --}}
@if($records->hasPages())
<div style="display:flex;justify-content:center;gap:6px;margin-top:8px">
    @if($records->onFirstPage())
        <span style="padding:6px 12px;border-radius:8px;border:1px solid var(--warm-bd);color:var(--txt-lt);font-size:.8rem">← Prev</span>
    @else
        <a href="{{ $records->previousPageUrl() }}" style="padding:6px 12px;border-radius:8px;border:1px solid var(--warm-bd);color:var(--txt);text-decoration:none;font-size:.8rem">← Prev</a>
    @endif
    <span style="padding:6px 12px;font-size:.8rem;color:var(--txt-md)">Page {{ $records->currentPage() }} of {{ $records->lastPage() }}</span>
    @if($records->hasMorePages())
        <a href="{{ $records->nextPageUrl() }}" style="padding:6px 12px;border-radius:8px;border:1px solid var(--warm-bd);color:var(--txt);text-decoration:none;font-size:.8rem">Next →</a>
    @else
        <span style="padding:6px 12px;border-radius:8px;border:1px solid var(--warm-bd);color:var(--txt-lt);font-size:.8rem">Next →</span>
    @endif
</div>
@endif
@endif
@endif

{{-- ═══════════════════════════ TAB: PRESCRIPTIONS ═══════════════════════════ -- --}}
@if($activeTab === 'prescriptions')
@if($prescriptions->isEmpty())
<div style="text-align:center;padding:52px 24px;color:var(--txt-lt)">
    <div style="width:48px;height:48px;border-radius:12px;background:var(--parch);display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
    </div>
    <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt-md)">No prescriptions yet</div>
    <a href="{{ route('doctor.prescriptions.create', ['patient' => $patient->id]) }}"
       style="display:inline-flex;align-items:center;gap:6px;margin-top:14px;padding:9px 18px;background:var(--ink);color:#fff;border-radius:9px;font-size:.875rem;font-weight:600;text-decoration:none">
        + New Prescription
    </a>
</div>
@else
<div class="panel">
    <div class="dr-table-wrap">
    {{-- Table header --}}
    <div class="dr-table-min" style="display:grid;grid-template-columns:1fr 1.5fr 2fr 1fr auto;gap:12px;padding:10px 20px;border-bottom:1px solid var(--warm-bd);font-size:.68rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--txt-lt)">
        <span>Rx Number</span>
        <span>Date</span>
        <span>Medicines</span>
        <span>Sent</span>
        <span>Actions</span>
    </div>
    @foreach($prescriptions as $rx)
    <div class="dr-table-min" style="display:grid;grid-template-columns:1fr 1.5fr 2fr 1fr auto;gap:12px;padding:13px 20px;border-bottom:1px solid var(--warm-bd);align-items:center;transition:background .12s"
         onmouseover="this.style.background='#faf8f5'" onmouseout="this.style.background='transparent'">

        <div>
            <div style="font-size:.85rem;font-weight:600;color:var(--txt);font-family:monospace">{{ $rx->prescription_number }}</div>
            @if($rx->doctor_user_id !== auth()->id())
            <div style="font-size:.7rem;color:var(--txt-lt)">Dr. {{ $rx->doctor?->profile?->full_name }}</div>
            @endif
        </div>

        <div style="font-size:.8125rem;color:var(--txt-md)">
            {{ $rx->prescribed_date->format('d M Y') }}
            <div style="font-size:.7rem;color:var(--txt-lt)">{{ $rx->prescribed_date->diffForHumans() }}</div>
        </div>

        <div>
            <div style="display:flex;gap:4px;flex-wrap:wrap">
                @foreach($rx->medicines->take(3) as $med)
                <span style="font-size:.7rem;padding:2px 8px;background:var(--parch);border-radius:20px;color:var(--txt-md);border:1px solid var(--warm-bd)">
                    {{ $med->medicine_name }}
                    @if($med->duration) · {{ $med->duration }} @endif
                </span>
                @endforeach
                @if($rx->medicines->count() > 3)
                <span style="font-size:.7rem;color:var(--txt-lt);align-self:center">+{{ $rx->medicines->count() - 3 }} more</span>
                @endif
            </div>
        </div>

        <div>
            @if($rx->is_sent_whatsapp)
            <span style="font-size:.7rem;font-weight:600;padding:2px 8px;border-radius:20px;background:#f0fdf4;color:#16a34a">✓ WhatsApp</span>
            @else
            <span style="font-size:.7rem;font-weight:500;padding:2px 8px;border-radius:20px;background:var(--parch);color:var(--txt-lt)">Not sent</span>
            @endif
        </div>

        <div style="display:flex;gap:5px">
            @if($rx->pdf_path)
            <a href="{{ route('doctor.prescriptions.pdf', $rx) }}" target="_blank"
               style="display:flex;align-items:center;gap:4px;font-size:.75rem;font-weight:500;padding:5px 10px;border:1.5px solid var(--warm-bd);border-radius:8px;color:var(--txt-md);text-decoration:none;transition:all .12s"
               onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
                PDF
            </a>
            @endif
            <a href="{{ route('doctor.prescriptions.show', $rx) }}"
               style="font-size:.75rem;font-weight:500;padding:5px 10px;border:1.5px solid var(--warm-bd);border-radius:8px;color:var(--txt-md);text-decoration:none;transition:all .12s"
               onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
                View →
            </a>
        </div>
    </div>
    @endforeach
    </div>
</div>
@if($prescriptions->hasPages())
<div style="display:flex;justify-content:center;gap:6px;margin-top:12px">
    @if(!$prescriptions->onFirstPage())
        <a href="{{ $prescriptions->previousPageUrl() }}" style="padding:6px 12px;border-radius:8px;border:1px solid var(--warm-bd);color:var(--txt);text-decoration:none;font-size:.8rem">← Prev</a>
    @endif
    @if($prescriptions->hasMorePages())
        <a href="{{ $prescriptions->nextPageUrl() }}" style="padding:6px 12px;border-radius:8px;border:1px solid var(--warm-bd);color:var(--txt);text-decoration:none;font-size:.8rem">Next →</a>
    @endif
</div>
@endif
@endif
@endif

{{-- ═══════════════════════════ TAB: VITALS ══════════════════════════════════ -- --}}
@if($activeTab === 'vitals')
@php
    $hasAnyVitals = collect($vitalsData)->some(fn($v) => $v->count() > 0);
@endphp
@if(!$hasAnyVitals)
<div style="text-align:center;padding:52px 24px;color:var(--txt-lt)">
    <div style="width:48px;height:48px;border-radius:12px;background:var(--parch);display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
    </div>
    <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt-md)">No vitals logged in the last 30 days</div>
    <p style="font-size:.8rem;margin-top:4px">Patient can log vitals from their dashboard.</p>
</div>
@else
<div class="dr-grid-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:18px" x-data="vitalsCharts()" x-init="init()">

    @foreach([
        'blood_pressure' => ['label' => 'Blood Pressure', 'unit' => 'mmHg', 'color' => '#c0737a', 'color2' => '#e8a89e'],
        'blood_sugar'    => ['label' => 'Blood Sugar',    'unit' => 'mg/dL', 'color' => '#d4a853', 'color2' => null],
        'weight'         => ['label' => 'Weight',         'unit' => 'kg',    'color' => '#4f87b0', 'color2' => null],
        'pulse'          => ['label' => 'Pulse',          'unit' => 'bpm',   'color' => '#6a9e8e', 'color2' => null],
    ] as $type => $cfg)
    @php $data = $vitalsData[$type] ?? collect(); @endphp
    @if($data->count() > 0)
    <div class="panel">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--warm-bd)">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1rem;font-weight:500;color:var(--txt)">{{ $cfg['label'] }}</div>
            <div style="display:flex;align-items:center;gap:6px">
                <span style="font-size:.75rem;font-weight:700;color:var(--txt)">
                    @if($type === 'blood_pressure')
                        {{ $data->last()['val1'] }}/{{ $data->last()['val2'] }}
                    @else
                        {{ $data->last()['val1'] }}
                    @endif
                </span>
                <span style="font-size:.7rem;color:var(--txt-lt)">{{ $cfg['unit'] }}</span>
            </div>
        </div>
        <div style="padding:14px 18px">
            {{-- SVG Sparkline --}}
            @php
                $vals1 = $data->pluck('val1')->map(fn($v) => (float)$v)->toArray();
                $vals2 = $type === 'blood_pressure' ? $data->pluck('val2')->map(fn($v) => (float)$v)->toArray() : [];
                $min1  = min($vals1) * .95;
                $max1  = max($vals1) * 1.05;
                $range1 = max($max1 - $min1, 1);
                $w = 260; $h = 70; $n = count($vals1);
                $pts1 = collect($vals1)->map(fn($v,$i) => [
                    'x' => $n > 1 ? round($i * ($w / ($n-1))) : $w/2,
                    'y' => round($h - (($v - $min1) / $range1) * $h)
                ])->toArray();
                $pathD1 = implode(' ', array_map(fn($p,$i) => ($i===0?'M':'L').$p['x'].','.$p['y'], $pts1, array_keys($pts1)));
                if ($type === 'blood_pressure' && count($vals2)) {
                    $min2 = min($vals2) * .95; $max2 = max($vals2) * 1.05; $range2 = max($max2-$min2,1);
                    $pts2 = collect($vals2)->map(fn($v,$i) => ['x'=>$pts1[$i]['x'],'y'=>round($h-(($v-$min2)/$range2)*$h)])->toArray();
                    $pathD2 = implode(' ',array_map(fn($p,$i)=>($i===0?'M':'L').$p['x'].','.$p['y'],$pts2,array_keys($pts2)));
                }
            @endphp
            <div style="overflow:hidden">
                <svg viewBox="-2 -4 264 80" style="width:100%;height:70px">
                    <polyline points="{{ implode(' ', array_map(fn($p) => $p['x'].','.$p['y'], $pts1)) }}"
                              fill="none" stroke="{{ $cfg['color'] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    @if($type === 'blood_pressure' && isset($pathD2))
                    <polyline points="{{ implode(' ', array_map(fn($p) => $p['x'].','.$p['y'], $pts2)) }}"
                              fill="none" stroke="{{ $cfg['color2'] }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="4,3"/>
                    @endif
                    @foreach($pts1 as $i => $pt)
                    <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="3" fill="{{ $cfg['color'] }}" opacity=".8">
                        <title>{{ $data[$i]['label'] }}: {{ $data[$i]['val1'] }}{{ $type === 'blood_pressure' ? '/'.$data[$i]['val2'] : '' }} {{ $cfg['unit'] }}</title>
                    </circle>
                    @endforeach
                </svg>
            </div>

            {{-- X-axis labels (first, mid, last) --}}
            <div style="display:flex;justify-content:space-between;font-size:.65rem;color:var(--txt-lt);margin-top:4px">
                <span>{{ $data->first()['date'] }}</span>
                @if($data->count() > 2) <span>{{ $data[intdiv($data->count(),2)]['date'] }}</span> @endif
                <span>{{ $data->last()['date'] }}</span>
            </div>

            {{-- Last 5 log entries --}}
            <div style="margin-top:12px;border-top:1px solid var(--warm-bd);padding-top:10px">
                @foreach($data->reverse()->take(5) as $entry)
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:.78rem;border-bottom:1px solid var(--parch)">
                    <span style="color:var(--txt-md)">{{ $entry['label'] }}</span>
                    <span style="font-weight:600;color:var(--txt)">
                        {{ $entry['val1'] }}{{ $type === 'blood_pressure' ? '/'.$entry['val2'] : '' }}
                        <span style="font-weight:400;color:var(--txt-lt)"> {{ $cfg['unit'] }}</span>
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
    @endforeach
</div>
@endif
@endif

{{-- ═══════════════════════════ TAB: TIMELINES ════════════════════════════════ -- --}}
@if($activeTab === 'timelines')
@if($timelines->isEmpty())
<div style="text-align:center;padding:52px 24px;color:var(--txt-lt)">
    <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt-md)">No active care timelines</div>
    <p style="font-size:.8rem;margin-top:4px">Timelines are created by doctors and shared with the patient.</p>
</div>
@else
@foreach($timelines as $tlData)
@php
    $tl         = $tlData['timeline'];
    $milestones = $tlData['milestones'];
    $done       = $milestones->filter(fn($m) => $m->is_past)->count();
    $total      = $milestones->count();
    $pct        = $total > 0 ? round($done / $total * 100) : 0;
@endphp
<div class="panel" style="margin-bottom:16px">
    <div style="padding:16px 20px;border-bottom:1px solid var(--warm-bd);display:flex;align-items:center;justify-content:space-between;gap:12px">
        <div>
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;color:var(--txt)">{{ $tl->template?->name }}</div>
            <div style="font-size:.78rem;color:var(--txt-lt);margin-top:2px">
                Started {{ $tl->start_date->format('d M Y') }}
                @if($tl->familyMember) · For {{ $tl->familyMember->full_name }} @endif
            </div>
        </div>
        <div style="text-align:right">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--txt)">{{ $pct }}%</div>
            <div style="font-size:.7rem;color:var(--txt-lt)">{{ $done }}/{{ $total }} milestones</div>
        </div>
    </div>

    {{-- Progress bar --}}
    <div style="height:4px;background:var(--parch)">
        <div style="height:100%;background:var(--leaf);width:{{ $pct }}%;transition:width .6s ease"></div>
    </div>

    {{-- Milestone list --}}
    <div style="padding:12px 20px">
        @foreach($milestones as $m)
        <div style="display:flex;gap:12px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--parch)">
            <div style="flex-shrink:0;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-top:1px;
                        {{ $m->is_past ? 'background:#e8f5f3;border:2px solid #6a9e8e' : 'background:var(--parch);border:2px solid var(--warm-bd)' }}">
                @if($m->is_past)
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="#3d7a6a" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                @else
                <div style="width:6px;height:6px;border-radius:50%;background:var(--warm-bd)"></div>
                @endif
            </div>
            <div style="flex:1">
                <div style="font-size:.875rem;font-weight:{{ $m->is_past ? '400' : '500' }};color:{{ $m->is_past ? 'var(--txt-lt)' : 'var(--txt)' }}">
                    {{ $m->title }}
                </div>
                @if($m->description)
                <div style="font-size:.75rem;color:var(--txt-lt);margin-top:2px">{{ $m->description }}</div>
                @endif
            </div>
            <div style="font-size:.75rem;font-weight:500;flex-shrink:0;
                        {{ $m->is_past ? 'color:var(--txt-lt)' : 'color:var(--leaf)' }}">
                {{ $m->target_date?->format('d M') ?? '' }}
                @if(!$m->is_past && $m->days_away !== null)
                    <span style="font-size:.68rem;color:var(--txt-lt)"> ({{ $m->days_away }}d)</span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endforeach
@endif
@endif

{{-- ══════════════════════════ TAB: DOCUMENTS ══════════════════════════════ --}}
@if($activeTab === 'documents')
@if($patientDocuments->isEmpty())
<div class="panel" style="padding:44px 24px;text-align:center;color:var(--txt-lt)">
    <svg width="38" height="38" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 12px;display:block;opacity:.3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt-md)">No documents uploaded</div>
    <p style="font-size:.78rem;margin-top:4px">{{ $patient->profile?->full_name ?? 'The patient' }} hasn't uploaded any documents yet.</p>
</div>
@else
<div style="margin-bottom:10px;font-size:.75rem;color:var(--txt-lt)">
    {{ $patientDocuments->count() }} document{{ $patientDocuments->count() > 1 ? 's' : '' }} uploaded by the patient · read-only
</div>
<div style="display:flex;flex-direction:column;gap:8px">
@foreach($patientDocuments as $doc)
@php
$typeLabels = ['prescription'=>'Prescription','lab_report'=>'Lab Report','discharge_summary'=>'Discharge Summary','scan_xray'=>'Scan / X-Ray','vaccination'=>'Vaccination','insurance'=>'Insurance','other'=>'Other'];
$typeColors = ['prescription'=>['bg'=>'#f4f0fa','color'=>'#4a3760'],'lab_report'=>['bg'=>'#eef5f3','color'=>'#2a7a6a'],'discharge_summary'=>['bg'=>'#fce7ef','color'=>'#9d174d'],'scan_xray'=>['bg'=>'#eff6ff','color'=>'#1d4ed8'],'vaccination'=>['bg'=>'#ecfdf5','color'=>'#065f46'],'insurance'=>['bg'=>'#fffbeb','color'=>'#92400e'],'other'=>['bg'=>'var(--parch)','color'=>'var(--txt-md)']];
$tc = $typeColors[$doc->document_type] ?? $typeColors['other'];
$tl = $typeLabels[$doc->document_type] ?? 'Other';
$isImage = str_starts_with($doc->mime_type ?? '', 'image/');
@endphp
<div class="panel" style="padding:14px 18px;display:flex;align-items:center;gap:14px">

    {{-- Icon --}}
    <div style="width:42px;height:42px;border-radius:11px;background:{{ $tc['bg'] }};display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0">
        {{ $isImage ? '🖼️' : '📄' }}
    </div>

    {{-- Info --}}
    <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:3px">
            <span style="font-weight:600;font-size:.9rem;color:var(--txt)">{{ $doc->title }}</span>
            <span style="font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:20px;background:{{ $tc['bg'] }};color:{{ $tc['color'] }}">{{ $tl }}</span>
        </div>
        <div style="font-size:.72rem;color:var(--txt-lt);display:flex;gap:10px;flex-wrap:wrap">
            <span>{{ $doc->file_name }}</span>
            <span>{{ $doc->file_size_human }}</span>
            @if($doc->document_date)<span>📅 {{ $doc->document_date->format('d M Y') }}</span>@endif
            @if($doc->notes)<span style="font-style:italic">{{ $doc->notes }}</span>@endif
            <span>Uploaded {{ $doc->created_at->format('d M Y') }}</span>
        </div>
    </div>

    {{-- Download --}}
    <a href="{{ route('doctor.patients.documents.download', $doc->id) }}"
       style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;background:var(--ink,#2d2d2d);color:#fff;border-radius:8px;font-size:.78rem;font-weight:600;text-decoration:none;flex-shrink:0;transition:opacity .12s"
       onmouseover="this.style.opacity='.82'" onmouseout="this.style.opacity='1'">
        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Download
    </a>
</div>
@endforeach
</div>
@endif
@endif {{-- end documents tab --}}

@endif {{-- end hasAccess --}}
@endsection

@push('scripts')
<script>
function vitalsCharts() {
    return { init() {} }
}
</script>
@endpush
