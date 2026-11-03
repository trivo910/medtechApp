@extends('layouts.doctor')
@section('title', 'My Patients')
@section('page-title', 'Patients')

@push('styles')
<style>
@media (max-width: 700px) {
    .patients-toolbar { display:grid !important; grid-template-columns:1fr; gap:10px !important; }
    .patients-toolbar form { min-width:0 !important; width:100%; }
    .patients-toolbar > div { overflow-x:auto; padding-bottom:2px; }
    .patients-toolbar > button { width:100%; justify-content:center; }
}
</style>
@endpush

@section('content')
<div x-data="patientList()" x-init="init()">

@if($assignTemplate)
{{-- ── Assign-mode banner ────────────────────────────────────────────────── --}}
<div style="display:flex;align-items:center;gap:14px;padding:14px 20px;background:#eef5f3;border:1.5px solid #b5ddd5;border-radius:13px;margin-bottom:20px">
    <div style="width:38px;height:38px;border-radius:10px;background:#3d7a6e;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
        </svg>
    </div>
    <div style="flex:1">
        <div style="font-weight:600;font-size:.9rem;color:#1a5a4a">Assign Timeline to a Patient</div>
        <div style="font-size:.78rem;color:#3a8a7a;margin-top:2px">Click <strong>Assign Timeline</strong> next to a patient below. Only patients with active access can receive timelines.</div>
    </div>
    <a href="{{ route('doctor.timelines.index') }}" style="font-size:.78rem;color:#3a8a7a;text-decoration:none;white-space:nowrap;border:1px solid #b5ddd5;padding:5px 12px;border-radius:8px;transition:background .12s"
       onmouseover="this.style.background='#d0ece8'" onmouseout="this.style.background='transparent'">
        ✕ Cancel
    </a>
</div>
@endif

{{-- ── Top bar: search + filters + add-patient button ─────────────────────── -- --}}
<div class="patients-toolbar" style="display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap">

    {{-- Search form --}}
    <form method="GET" action="{{ route('doctor.patients.index') }}"
          style="display:flex;flex:1;min-width:220px;gap:0;position:relative">
        @if($assignTemplate)<input type="hidden" name="assign_template" value="{{ $assignTemplate }}">@endif
        <div style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--txt-lt);pointer-events:none">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/>
            </svg>
        </div>
        <input type="text" name="q" value="{{ $search }}" placeholder="Search by name or mobile…"
               style="flex:1;padding:.6rem .85rem .6rem 2.2rem;border:1.5px solid var(--warm-bd);border-right:none;border-radius:10px 0 0 10px;font-size:.875rem;color:var(--txt);background:var(--cream);outline:none;font-family:'Outfit',sans-serif"
               onfocus="this.style.borderColor='var(--leaf)'" onblur="this.style.borderColor='var(--warm-bd)'">
        <button type="submit" style="padding:.6rem 14px;background:var(--ink);color:#fff;border:none;border-radius:0 10px 10px 0;font-size:.875rem;font-weight:500;cursor:pointer;font-family:'Outfit',sans-serif">
            Search
        </button>
        @if($search)
        <a href="{{ route('doctor.patients.index') }}"
           style="display:flex;align-items:center;margin-left:6px;font-size:.8rem;color:var(--txt-lt);text-decoration:none;white-space:nowrap"
           onmouseover="this.style.color='var(--txt)'" onmouseout="this.style.color='var(--txt-lt)'">
            ✕ Clear
        </a>
        @endif
    </form>

    {{-- Filters --}}
    <div style="display:flex;gap:6px">
        @foreach(['all' => 'All Patients', 'active' => 'Active Access', 'recent' => 'Recent (30d)'] as $val => $lbl)
        <a href="{{ route('doctor.patients.index', array_merge(request()->only('q'), ['filter' => $val === 'all' ? null : $val])) }}"
           style="padding:6px 13px;border-radius:8px;font-size:.8rem;font-weight:500;text-decoration:none;border:1.5px solid;transition:all .15s;
                  {{ ($filter ?? 'all') === $val
                     ? 'background:var(--ink);color:#fff;border-color:var(--ink)'
                     : 'background:var(--cream);color:var(--txt-md);border-color:var(--warm-bd)' }}">
            {{ $lbl }}
        </a>
        @endforeach
    </div>

    {{-- Raise access request button --}}
    <button @click="modal=true"
            style="display:flex;align-items:center;gap:7px;padding:.6rem 14px;background:var(--leaf);color:#fff;border:none;border-radius:10px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;white-space:nowrap;box-shadow:0 2px 8px rgba(61,122,110,.25)">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
        </svg>
        Find / Access Patient
    </button>
</div>

{{-- ── Patient table ────────────────────────────────────────────────────────── -- --}}
<div class="panel">
    @if($patients->isEmpty())
    <div style="padding:52px 24px;text-align:center;color:var(--txt-lt)">
        <div style="width:52px;height:52px;border-radius:14px;background:var(--parch);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="var(--txt-lt)" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:500;color:var(--txt-md);margin-bottom:5px">
            {{ $search ? 'No patients matching "'.$search.'"' : 'No patients yet' }}
        </div>
        <p style="font-size:.8125rem;margin-bottom:16px">Use "Find / Access Patient" to add a patient by mobile, Sub-ID or Aadhaar.</p>
        <button @click="modal=true"
                style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:var(--leaf);color:#fff;border:none;border-radius:9px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif">
            Find a Patient
        </button>
    </div>
    @else

    <div class="dr-table-wrap patients-table-wrap">
    {{-- Table header --}}
    <div class="dr-table-min" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr;gap:12px;padding:10px 20px;border-bottom:1px solid var(--warm-bd);font-size:.68rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--txt-lt)">
        <span>Patient</span>
        <span>Mobile</span>
        <span>Last Visit</span>
        <span>Visits</span>
        <span>Access</span>
        <span>Action</span>
    </div>

    @foreach($patients as $p)
    @php
        $name     = $p->profile?->full_name ?? 'Unknown';
        $colors   = ['#3d7a6e','#7a6e3d','#6e3d7a','#3d607a','#7a3d4a'];
        $color    = $colors[$p->id % count($colors)];
    @endphp
    <div class="dr-table-min" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:12px;padding:13px 20px;border-bottom:1px solid var(--warm-bd);align-items:center;transition:background .12s"
         onmouseover="this.style.background='#faf8f5'" onmouseout="this.style.background='transparent'">

        {{-- Name + family count --}}
        <div style="display:flex;align-items:center;gap:11px;min-width:0">
            <x-avatar name="{{ $name }}" :photo="$p->profile?->profile_photo"
                       :size="36" :radius="10" :bg="$color" font-size=".875rem" />
            <div style="min-width:0">
                <div style="font-size:.9rem;font-weight:500;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    {{ $name }}
                </div>
                <div style="font-size:.72rem;color:var(--txt-lt);margin-top:1px;display:flex;gap:6px">
                    @if($p->profile?->age) <span>Age {{ $p->profile->age }}</span> @endif
                    @if($p->profile?->gender) <span>· {{ ucfirst($p->profile->gender) }}</span> @endif
                    @if($p->profile?->city)   <span>· {{ $p->profile->city }}</span>            @endif
                    @if($p->familyMembers->count() > 0)
                        <span>· {{ $p->familyMembers->count() }} member{{ $p->familyMembers->count() > 1 ? 's' : '' }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Mobile --}}
        <div style="font-size:.8125rem;color:var(--txt-md);font-family:monospace">
            {{ $p->country_code }} {{ $p->mobile_number }}
        </div>

        {{-- Last visit --}}
        <div style="font-size:.8125rem;color:var(--txt-md)">
            @if($p->last_visit)
                {{ \Carbon\Carbon::parse($p->last_visit)->format('d M Y') }}
            @else
                <span style="color:var(--txt-lt)">—</span>
            @endif
        </div>

        {{-- Total visits --}}
        <div>
            <span style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:500;color:var(--txt)">
                {{ $p->total_visits }}
            </span>
            <span style="font-size:.7rem;color:var(--txt-lt)"> visits</span>
        </div>

        {{-- Access badge --}}
        <div>
            @if($p->active_access)
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:.7rem;font-weight:600;padding:3px 9px;border-radius:20px;background:#e8f5f3;color:#1a7a6a">
                    <span style="width:6px;height:6px;border-radius:50%;background:#1a7a6a;display:inline-block"></span>
                    Active
                </span>
            @else
                <span style="font-size:.7rem;font-weight:600;padding:3px 9px;border-radius:20px;background:var(--parch);color:var(--txt-lt)">
                    No Access
                </span>
            @endif
        </div>

        {{-- Actions --}}
        <div style="display:flex;gap:5px;flex-shrink:0">
            @if($assignTemplate)
                @if($p->active_access)
                <a href="{{ route('doctor.timelines.assign', $p->id) }}?template={{ $assignTemplate }}"
                   style="display:flex;align-items:center;gap:5px;padding:6px 13px;background:var(--leaf);color:#fff;border-radius:8px;font-size:.78rem;font-weight:600;text-decoration:none;white-space:nowrap;transition:opacity .15s"
                   onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    Assign Timeline →
                </a>
                @else
                <span style="font-size:.75rem;color:var(--txt-lt);padding:6px 8px;border:1.5px solid var(--warm-bd);border-radius:8px;white-space:nowrap">No Access</span>
                @endif
            @else
                @if($p->active_access)
                <a href="{{ route('doctor.patients.history', $p->id) }}"
                   style="display:flex;align-items:center;gap:5px;padding:6px 11px;background:var(--ink);color:#fff;border:none;border-radius:8px;font-size:.78rem;font-weight:500;text-decoration:none;transition:opacity .15s"
                   onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    View History
                </a>
                @else
                <button @click="openAccessModal({{ json_encode(['id' => $p->id, 'name' => $name, 'mobile' => $p->mobile_number, 'cc' => $p->country_code]) }})"
                        style="display:flex;align-items:center;gap:5px;padding:6px 11px;background:transparent;color:var(--leaf);border:1.5px solid var(--leaf);border-radius:8px;font-size:.78rem;font-weight:500;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .15s"
                        onmouseover="this.style.background='#edf6f4'" onmouseout="this.style.background='transparent'">
                    Request Access
                </button>
                @endif
                <a href="{{ route('doctor.patients.history', $p->id) }}"
                   style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1.5px solid var(--warm-bd);color:var(--txt-lt);text-decoration:none;transition:all .12s"
                   onmouseover="this.style.background='var(--parch)';this.style.color='var(--txt)'" onmouseout="this.style.background='transparent';this.style.color='var(--txt-lt)'">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @endif
        </div>
    </div>
    @endforeach
    </div>

    {{-- Pagination --}}
    @if($patients->hasPages())
    <div style="padding:14px 20px;display:flex;justify-content:space-between;align-items:center;font-size:.8rem;color:var(--txt-md)">
        <span>Showing {{ $patients->firstItem() }}–{{ $patients->lastItem() }} of {{ $patients->total() }}</span>
        <div style="display:flex;gap:4px">
            @if($patients->onFirstPage())
                <span style="padding:5px 10px;border-radius:7px;border:1px solid var(--warm-bd);color:var(--txt-lt)">← Prev</span>
            @else
                <a href="{{ $patients->previousPageUrl() }}" style="padding:5px 10px;border-radius:7px;border:1px solid var(--warm-bd);color:var(--txt);text-decoration:none">← Prev</a>
            @endif
            @if($patients->hasMorePages())
                <a href="{{ $patients->nextPageUrl() }}" style="padding:5px 10px;border-radius:7px;border:1px solid var(--warm-bd);color:var(--txt);text-decoration:none">Next →</a>
            @else
                <span style="padding:5px 10px;border-radius:7px;border:1px solid var(--warm-bd);color:var(--txt-lt)">Next →</span>
            @endif
        </div>
    </div>
    @endif
    @endif
</div>

{{-- ── Find / Access Patient Modal ─────────────────────────────────────────── -- --}}
<div x-show="modal" x-transition style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;display:flex;align-items:center;justify-content:center;padding:20px" @keydown.escape.window="modal=false">
    <div @click.stop style="background:#fff;border-radius:18px;width:100%;max-width:480px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.2)">

        {{-- Modal header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--warm-bd)">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:500;color:var(--txt)">Find Patient & Request Access</div>
            <button @click="modal=false;resetModal()" style="background:none;border:none;cursor:pointer;color:var(--txt-lt);padding:4px">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div style="padding:22px">
            {{-- Step 1: search --}}
            <div x-show="step === 1">
                {{-- Search type tabs --}}
                <div style="display:flex;background:#f0f4f6;border-radius:10px;padding:3px;gap:3px;margin-bottom:16px">
                    <button @click="searchType='mobile'" :class="searchType==='mobile' ? 'active' : ''"
                            style="flex:1;padding:7px;border-radius:8px;border:none;font-size:.8rem;font-weight:500;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .15s"
                            :style="searchType==='mobile' ? 'background:#fff;color:var(--ink);box-shadow:0 1px 4px rgba(0,0,0,.1)' : 'background:transparent;color:var(--txt-md)'">
                        📱 Mobile
                    </button>
                    <button @click="searchType='sub_id'" :class="searchType==='sub_id' ? 'active' : ''"
                            style="flex:1;padding:7px;border-radius:8px;border:none;font-size:.8rem;font-weight:500;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .15s"
                            :style="searchType==='sub_id' ? 'background:#fff;color:var(--ink);box-shadow:0 1px 4px rgba(0,0,0,.1)' : 'background:transparent;color:var(--txt-md)'">
                        🔖 Sub-ID
                    </button>
                    <button @click="searchType='aadhaar'"
                            style="flex:1;padding:7px;border-radius:8px;border:none;font-size:.8rem;font-weight:500;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .15s"
                            :style="searchType==='aadhaar' ? 'background:#fff;color:var(--ink);box-shadow:0 1px 4px rgba(0,0,0,.1)' : 'background:transparent;color:var(--txt-md)'">
                        🪪 Aadhaar
                    </button>
                </div>

                <div style="position:relative;margin-bottom:12px">
                    <input x-model="searchQuery" type="text"
                           @input="clearFound()"
                           :placeholder="searchType==='mobile' ? 'Enter 10-digit mobile number' : searchType==='sub_id' ? 'e.g. MED-00042-B' : 'Last 4 digits of Aadhaar'"
                           @keyup.enter="doSearch()"
                           style="width:100%;padding:.65rem 3rem .65rem .95rem;border:1.5px solid var(--warm-bd);border-radius:10px;font-size:.9375rem;color:var(--txt);outline:none;font-family:'Outfit',sans-serif;box-sizing:border-box"
                           :style="searchError ? 'border-color:#ef4444' : ''"
                           @focus="$el.style.borderColor='var(--leaf)'" @blur="if(!searchError)$el.style.borderColor='var(--warm-bd)'">
                    <button type="button" @click="doSearch()" :disabled="searching"
                            aria-label="Search patient"
                            style="position:absolute;right:6px;top:50%;transform:translateY(-50%);width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;border-radius:8px;color:var(--txt-lt);cursor:pointer;padding:0;transition:color .15s,background .15s"
                            onmouseover="this.style.color='var(--leaf)';this.style.background='#f0faf8'" onmouseout="this.style.color='var(--txt-lt)';this.style.background='transparent'">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/></svg>
                    </button>
                </div>

                <p x-show="searchError" x-text="searchError" style="font-size:.78rem;color:#dc2626;margin-bottom:10px"></p>

                {{-- Found patient preview --}}
                <div x-show="foundPatient" style="background:var(--parch);border:1px solid var(--warm-bd);border-radius:10px;padding:14px;margin-bottom:14px">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                        <div style="width:36px;height:36px;border-radius:9px;background:var(--leaf);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.875rem">
                            <span x-text="(foundPatient?.name||'?')[0].toUpperCase()"></span>
                        </div>
                        <div>
                            <div style="font-weight:600;color:var(--txt);font-size:.9rem" x-text="foundPatient?.name"></div>
                            <div style="font-size:.75rem;color:var(--txt-lt)" x-text="(foundPatient?.age ? 'Age '+foundPatient.age : '') + (foundPatient?.city ? ' · '+foundPatient.city : '')"></div>
                        </div>
                        <div style="margin-left:auto">
                            <span x-show="foundMember" style="font-size:.7rem;font-weight:600;padding:3px 8px;border-radius:20px;background:#f0e8ff;color:#6b3d9a" x-text="'Family: '+foundMember?.name"></span>
                        </div>
                    </div>
                </div>

                <button @click="doSearch()" :disabled="searching || searchQuery.length < 3"
                        :style="(searching || searchQuery.length < 3) ? 'width:100%;padding:.7rem;background:var(--ink);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:600;cursor:not-allowed;opacity:.5;font-family:Outfit,sans-serif;display:flex;align-items:center;justify-content:center;gap:8px' : 'width:100%;padding:.7rem;background:var(--ink);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:600;cursor:pointer;font-family:Outfit,sans-serif;display:flex;align-items:center;justify-content:center;gap:8px'">
                    <span x-show="searching" style="width:15px;height:15px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite"></span>
                    <span x-text="searching ? 'Searching…' : (foundPatient ? 'Request Access for This Patient' : 'Search Patient')"></span>
                </button>
            </div>

            {{-- Step 2: OTP entry (if OTP required) --}}
            <div x-show="step === 2">
                <div style="text-align:center;margin-bottom:18px">
                    <div style="width:48px;height:48px;background:#f0faf8;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="var(--leaf)" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--txt)">Patient OTP Required</div>
                    <p style="font-size:.8125rem;color:var(--txt-md);margin-top:5px;line-height:1.5">
                        An OTP has been sent to the patient's WhatsApp.<br>Ask them to share it with you.
                    </p>
                </div>

                <div style="display:flex;justify-content:center;gap:8px;margin-bottom:14px">
                    <template x-for="(d,i) in otpDigits" :key="i">
                        <input type="tel" inputmode="numeric" maxlength="1"
                               :value="d"
                               style="width:44px;height:52px;text-align:center;font-size:1.4rem;font-weight:600;border:1.5px solid var(--warm-bd);border-radius:10px;outline:none;font-family:'Outfit',sans-serif;transition:border-color .15s"
                               @focus="$el.style.borderColor='var(--leaf)'" @blur="$el.style.borderColor='var(--warm-bd)'"
                               @input="otpInput($event,i)" @keydown="otpKey($event,i)">
                    </template>
                </div>

                <p x-show="otpError" x-text="otpError" style="text-align:center;font-size:.78rem;color:#dc2626;margin-bottom:10px"></p>

                <button @click="verifyOtp()" :disabled="verifying || otpDigits.join('').length < 6"
                        style="width:100%;padding:.7rem;background:var(--leaf);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;display:flex;align-items:center;justify-content:center;gap:8px"
                        :style="(verifying || otpDigits.join('').length < 6) ? 'opacity:.5;cursor:not-allowed' : ''">
                    <span x-show="verifying" style="width:15px;height:15px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite"></span>
                    <span x-text="verifying ? 'Verifying…' : 'Verify & Gain Access'"></span>
                </button>

                <button @click="step=1" style="width:100%;margin-top:8px;padding:.6rem;background:transparent;color:var(--txt-md);border:1px solid var(--warm-bd);border-radius:10px;font-size:.875rem;cursor:pointer;font-family:'Outfit',sans-serif">
                    ← Back
                </button>
            </div>

            {{-- Step 3: Success --}}
            <div x-show="step === 3" style="text-align:center;padding:10px 0">
                <div style="width:56px;height:56px;background:#e8f5f3;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="var(--leaf)" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;color:var(--txt);margin-bottom:6px">Access Granted!</div>
                <p style="font-size:.8125rem;color:var(--txt-md);margin-bottom:18px" x-text="'You now have access to ' + (foundPatient?.name || 'patient') + '\'s medical records.'"></p>
                <a :href="successUrl"
                   style="display:inline-flex;align-items:center;gap:7px;padding:10px 22px;background:var(--ink);color:#fff;border-radius:10px;font-size:.9rem;font-weight:600;text-decoration:none">
                    View Patient History →
                </a>
            </div>
        </div>
    </div>
</div>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
function patientList() {
    return {
        modal:       false,
        step:        1,
        searchType:  'mobile',
        searchQuery: '',
        searching:   false,
        searchError: '',
        foundPatient:null,
        foundMember: null,
        resolvedIdentifier: null,
        resolvedType: null,
        pendingReqId:null,
        otpDigits:   ['','','','','',''],
        otpError:    '',
        verifying:   false,
        successUrl:  '#',

        init() {},

        openAccessModal(patient) {
            this.modal       = true;
            this.step        = 1;
            this.searchQuery = patient.mobile;
            this.searchType  = 'mobile';
        },

        resetModal() {
            this.step        = 1;
            this.searchQuery = '';
            this.foundPatient= null;
            this.foundMember = null;
            this.resolvedIdentifier = null;
            this.resolvedType = null;
            this.searchError = '';
            this.otpDigits   = ['','','','','',''];
            this.otpError    = '';
            this.pendingReqId= null;
        },

        // Typing after a successful search invalidates the found patient
        clearFound() {
            this.foundPatient = null;
            this.foundMember  = null;
            this.resolvedIdentifier = null;
            this.resolvedType = null;
            this.searchError  = '';
        },

        async doSearch() {
            if (this.foundPatient) {
                // Already found — proceed to request access
                await this.raiseRequest();
                return;
            }
            if (this.searchQuery.length < 3) {
                this.searchError = 'Enter at least 3 characters (mobile, Sub-ID, Aadhaar or name).';
                return;
            }
            this.searching   = true;
            this.searchError = '';
            try {
                const res  = await fetch(`{{ route('doctor.patients.search') }}?q=${encodeURIComponent(this.searchQuery)}&type=${this.searchType}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                const data = await res.json();
                if (data.found) {
                    this.foundPatient = data.patient;
                    this.foundMember  = data.family_member || null;
                    // Use the identifier the server resolved, not the raw query
                    this.resolvedIdentifier = data.identifier || data.patient?.mobile || this.searchQuery;
                    this.resolvedType       = data.identifier_type || this.searchType;
                    this.searchError        = '';
                } else {
                    this.foundPatient = null;
                    this.foundMember  = null;
                    this.resolvedIdentifier = null;
                    this.resolvedType = null;
                    this.searchError = data.message || 'No patient found with that identifier.';
                }
            } catch(e) {
                this.searchError = 'Network error. Please try again.';
            } finally {
                this.searching = false;
            }
        },

        async raiseRequest() {
            this.searching   = true;
            this.searchError = '';
            try {
                const res  = await fetch('{{ route('doctor.patients.request-access') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({
                        identifier:       this.resolvedIdentifier || this.searchQuery,
                        identifier_type:  this.resolvedType || this.searchType,
                        patient_id:       this.foundPatient?.id ?? null,
                        family_member_id: this.foundMember?.id ?? null,
                    })
                });
                const data = await res.json();

                if (data.status === 'approved' || data.status === 'already_active') {
                    this.successUrl = `/doctor/patients/${this.foundPatient.id}/history`;
                    this.step = 3;
                } else if (data.status === 'pending') {
                    this.pendingReqId = data.request?.id;
                    this.step = 2;
                } else {
                    this.searchError = data.message || 'Something went wrong.';
                }
            } catch(e) {
                this.searchError = 'Network error.';
            } finally {
                this.searching = false;
            }
        },

        async verifyOtp() {
            const otp = this.otpDigits.join('');
            if (otp.length < 6 || !this.pendingReqId) return;
            this.verifying = true;
            this.otpError  = '';
            try {
                const res  = await fetch(`{{ url('doctor/patients/access') }}/${this.pendingReqId}/verify-otp`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ otp })
                });
                const data = await res.json();
                if (data.success) {
                    this.successUrl = `/doctor/patients/${this.foundPatient.id}/history`;
                    this.step = 3;
                } else {
                    this.otpError  = data.message || 'Incorrect OTP.';
                    this.otpDigits = ['','','','','',''];
                    this.$nextTick(() => document.querySelectorAll('[x-show="step === 2"] input[type=tel]')[0]?.focus());
                }
            } catch(e) {
                this.otpError = 'Network error.';
            } finally {
                this.verifying = false;
            }
        },

        otpInput(e, i) {
            const val = e.target.value.replace(/\D/g,'').slice(-1);
            this.otpDigits[i] = val;
            if (val && i < 5) this.$nextTick(() => document.querySelectorAll('[x-show="step === 2"] input[type=tel]')[i+1]?.focus());
        },
        otpKey(e, i) {
            if (e.key === 'Backspace') {
                if (this.otpDigits[i]) { this.otpDigits[i] = ''; }
                else if (i > 0) { this.otpDigits[i-1] = ''; this.$nextTick(() => document.querySelectorAll('[x-show="step === 2"] input[type=tel]')[i-1]?.focus()); }
                e.preventDefault();
            }
        },
    }
}
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush
