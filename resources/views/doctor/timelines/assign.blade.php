@extends('layouts.doctor')
@section('title', 'Assign Timeline')
@section('page-title')
    <a href="{{ route('doctor.timelines.index') }}" style="font-size:.85rem;font-weight:400;color:var(--txt-lt);text-decoration:none">Timelines</a>
    <span style="color:var(--txt-lt);margin:0 6px">/</span>
    Assign to {{ $patient->profile?->full_name ?? 'Patient' }}
@endsection

@push('styles')
<style>
.tpl-card {
    padding: 13px 16px;
    border: 1.5px solid var(--warm-bd);
    border-radius: 11px;
    background: #fff;
    cursor: pointer;
    transition: border-color .15s, background .15s, box-shadow .15s;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    user-select: none;
}
.tpl-card:hover {
    border-color: var(--leaf);
    background: #f7fbfa;
}
.tpl-card.tpl-selected {
    border-color: var(--leaf);
    background: #eef5f3;
    box-shadow: 0 0 0 3px rgba(61,122,110,.12);
}
.tpl-check {
    width: 20px; height: 20px;
    border-radius: 50%;
    border: 2px solid var(--warm-bd);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 2px;
    transition: all .15s;
}
.tpl-card.tpl-selected .tpl-check {
    background: var(--leaf);
    border-color: var(--leaf);
}
</style>
@endpush

@section('content')
@php
use App\Models\TimelineTemplate;
$specMeta = TimelineTemplate::SPECIALTIES;
@endphp

{{-- ── Error / success flash ────────────────────────────────────────────────── --}}
@if(session('success'))
<div style="padding:12px 16px;background:#eef5f3;border:1px solid #b5ddd5;border-radius:10px;margin-bottom:18px;font-size:.875rem;color:#2a7a6a">
    ✓ {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin-bottom:18px">
    @foreach($errors->all() as $e)<div style="font-size:.8rem;color:#dc2626">• {{ $e }}</div>@endforeach
</div>
@endif

{{-- ── Main layout ──────────────────────────────────────────────────────────── --}}
<div class="fade-in dr-grid-2col"
     style="display:grid;grid-template-columns:1fr 300px;gap:22px;align-items:start"
     x-data="{
         selected:  '{{ $preselectedId ?? '' }}',
         submitted: false,
         pick(id) { this.selected = id; },
     }">

    {{-- ── LEFT: Template picker ───────────────────────────────────────────── --}}
    <div>
        {{-- FIX 1: hidden input bound to Alpine state — not a radio --}}
        {{-- FIX 2: @click="pick(id)" on each card directly --}}
        <form method="POST"
              action="{{ route('doctor.timelines.assign.save', $patient->id) }}"
              @submit.prevent="if(selected){ submitted=true; $el.submit() }">
            @csrf

            {{-- Hidden input carries the selected template ID --}}
            <input type="hidden" name="template_id" :value="selected">

            {{-- Already-assigned notice --}}
            @if($existing->isNotEmpty())
            <div style="padding:12px 15px;background:#fef9ec;border:1px solid #fde68a;border-radius:10px;margin-bottom:18px;font-size:.8rem;color:#92400e">
                ⚠ This patient already has
                <strong>{{ $existing->count() }}</strong>
                active timeline{{ $existing->count() > 1 ? 's' : '' }}:
                @foreach($existing as $e)
                    <strong>{{ $e->template?->title }}</strong>{{ !$loop->last ? ', ' : '' }}
                @endforeach.
                You can assign an additional one.
            </div>
            @endif

            {{-- Template groups --}}
            @if($templates->isEmpty())
            <div style="padding:40px 24px;text-align:center;color:var(--txt-lt);background:#fff;border:1.5px solid var(--warm-bd);border-radius:14px">
                <div style="font-size:2rem;margin-bottom:10px">📋</div>
                <div style="font-size:.9rem;color:var(--txt-md)">No active templates found.</div>
                <a href="{{ route('doctor.timelines.create') }}"
                   style="display:inline-block;margin-top:12px;padding:7px 18px;background:var(--leaf);color:#fff;border-radius:9px;text-decoration:none;font-size:.8rem;font-weight:600">
                    Create Template
                </a>
            </div>
            @else

            <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt);margin-bottom:16px">
                Select a Template
            </div>

            @foreach($templates as $spec => $group)
            @php $m = $specMeta[$spec] ?? ['icon'=>'📋','color'=>'#4a3760','label'=>ucwords(str_replace('_',' ',$spec))]; @endphp
            <div style="margin-bottom:22px">

                {{-- Specialty heading --}}
                <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--txt-lt);margin-bottom:8px;display:flex;align-items:center;gap:6px">
                    <span>{{ $m['icon'] }}</span>
                    <span>{{ $m['label'] }}</span>
                </div>

                <div style="display:flex;flex-direction:column;gap:7px">
                @foreach($group as $tpl)
                {{-- FIX: @click sets selected directly on the card div --}}
                <div class="tpl-card"
                     :class="selected == '{{ $tpl->id }}' ? 'tpl-selected' : ''"
                     @click="pick('{{ $tpl->id }}')">

                    {{-- Specialty icon --}}
                    <div style="width:38px;height:38px;border-radius:9px;background:{{ $m['color'] }}20;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0">
                        {{ $m['icon'] }}
                    </div>

                    {{-- Info --}}
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:600;font-size:.9rem;color:var(--txt);margin-bottom:2px">
                            {{ $tpl->title }}
                        </div>
                        @if($tpl->description)
                        <div style="font-size:.75rem;color:var(--txt-lt);line-height:1.45;margin-bottom:5px">
                            {{ Str::limit($tpl->description, 100) }}
                        </div>
                        @endif
                        <div style="display:flex;gap:12px;font-size:.7rem;color:var(--txt-lt)">
                            <span>📋 {{ $tpl->milestones_count }} milestone{{ $tpl->milestones_count != 1 ? 's' : '' }}</span>
                            @if($tpl->total_duration_days)
                            <span>⏱ {{ $tpl->total_duration_days }} days</span>
                            @endif
                            @if($tpl->is_system_template)
                            <span style="color:var(--leaf);font-weight:600">✓ System</span>
                            @endif
                        </div>
                    </div>

                    {{-- FIX: CSS-based checkmark, no JS dependency --}}
                    <div class="tpl-check">
                        <svg x-show="selected == '{{ $tpl->id }}'"
                             width="10" height="10" fill="none" viewBox="0 0 24 24"
                             stroke="#fff" stroke-width="3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                @endforeach
                </div>
            </div>
            @endforeach

            {{-- Assignment details --}}
            <div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:13px;padding:18px 20px;margin-top:8px">
                <div style="font-family:'Cormorant Garamond',serif;font-size:1rem;color:var(--txt);margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--warm-bd)">
                    Assignment Details
                </div>

                <div class="dr-grid-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                    <div>
                        <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);display:block;margin-bottom:5px">
                            Start Date *
                        </label>
                        <input type="date" name="start_date"
                               value="{{ today()->format('Y-m-d') }}"
                               max="{{ today()->addYears(1)->format('Y-m-d') }}"
                               style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.875rem;color:var(--txt);background:#fff;outline:none;font-family:inherit;transition:border-color .15s"
                               onfocus="this.style.borderColor='var(--leaf)'" onblur="this.style.borderColor='var(--warm-bd)'"
                               required>
                    </div>

                    @if($patient->familyMembers->isNotEmpty())
                    <div>
                        <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);display:block;margin-bottom:5px">
                            Assign For
                        </label>
                        <select name="family_member_id"
                                style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.875rem;color:var(--txt);background:#fff;outline:none;font-family:inherit">
                            <option value="">{{ $patient->profile?->full_name ?? 'Patient' }} (self)</option>
                            @foreach($patient->familyMembers as $fm)
                            <option value="{{ $fm->id }}" @selected((string) old('family_member_id', request('member')) === (string) $fm->id)>{{ $fm->full_name }} ({{ $fm->relation }})</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>

                <div>
                    <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);display:block;margin-bottom:5px">
                        Custom Notes <span style="font-weight:400;text-transform:none">(optional)</span>
                    </label>
                    <textarea name="custom_notes" rows="2"
                              placeholder="Any specific notes for this patient's timeline…"
                              style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.8rem;color:var(--txt);background:#fff;outline:none;font-family:inherit;resize:none;line-height:1.5"></textarea>
                </div>
            </div>

            {{-- Submit --}}
            <div style="margin-top:14px">
                {{-- FIX: button disabled state via Alpine, submit prevented by @submit.prevent --}}
                <button type="submit"
                        :disabled="!selected || submitted"
                        style="width:100%;padding:.82rem;background:var(--leaf);color:#fff;border:none;border-radius:11px;font-size:.9375rem;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s"
                        :style="(!selected || submitted) ? 'opacity:.45;cursor:not-allowed' : 'opacity:1'"
                        onmouseover="if(!this.disabled) this.style.opacity='.88'" onmouseout="if(!this.disabled) this.style.opacity='1'">
                    <span x-show="submitted" style="width:15px;height:15px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;flex-shrink:0"></span>
                    <span x-text="submitted ? 'Assigning…' : (selected ? 'Assign Timeline to Patient' : 'Select a template above')"></span>
                </button>

                <div x-show="!selected"
                     style="text-align:center;font-size:.75rem;color:var(--txt-lt);margin-top:7px">
                    ↑ Click any template card to select it
                </div>
            </div>

            @endif
        </form>
    </div>

    {{-- ── RIGHT: Patient info sidebar ─────────────────────────────────────── --}}
    <div class="dr-sidebar-sticky" style="position:sticky;top:78px;display:flex;flex-direction:column;gap:14px">

        {{-- Patient card --}}
        <div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:13px;padding:18px 20px">
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);margin-bottom:12px">Assigning to</div>

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--warm-bd)">
                <div style="width:44px;height:44px;border-radius:11px;background:var(--leaf);display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:700;color:#fff;flex-shrink:0">
                    {{ strtoupper(substr($patient->profile?->full_name ?? 'P', 0, 1)) }}
                </div>
                <div>
                    <div style="font-weight:600;font-size:.9375rem;color:var(--txt)">{{ $patient->profile?->full_name }}</div>
                    <div style="font-size:.75rem;color:var(--txt-lt)">
                        @if($patient->profile?->dob) Age {{ $patient->profile->dob->age }} · @endif
                        {{ ucfirst($patient->profile?->gender ?? '') }}
                        @if($patient->profile?->blood_group) · {{ $patient->profile->blood_group }} @endif
                    </div>
                </div>
            </div>

            @if($patient->familyMembers->isNotEmpty())
            <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--warm-bd)">
                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);margin-bottom:7px">Family Members</div>
                @foreach($patient->familyMembers as $fm)
                <div style="display:flex;justify-content:space-between;font-size:.78rem;padding:3px 0">
                    <span style="color:var(--txt)">{{ $fm->full_name }}</span>
                    <span style="color:var(--txt-lt)">{{ ucfirst($fm->relation) }}</span>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Active timelines --}}
            @if($existing->isNotEmpty())
            <div>
                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);margin-bottom:7px">Active Timelines</div>
                @foreach($existing as $e)
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:.78rem;padding:4px 0;border-bottom:1px solid var(--warm-bd)">
                    <span style="color:var(--txt)">{{ $e->template?->title }}</span>
                    <form method="POST" action="{{ route('doctor.timelines.unassign', $e->id) }}"
                          onsubmit="return confirm('Remove this timeline?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                style="font-size:.68rem;color:#dc2626;background:none;border:none;cursor:pointer;font-family:inherit;padding:0">
                            Remove
                        </button>
                    </form>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Selected template preview --}}
        <div x-show="selected" style="background:#eef5f3;border:1.5px solid #b5ddd5;border-radius:13px;padding:14px 16px">
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#2a7a6a;margin-bottom:6px">Selected</div>
            <div style="font-size:.85rem;font-weight:600;color:#1a5a4a">
                @foreach($templates->flatten() as $tpl)
                <span x-show="selected == '{{ $tpl->id }}'">{{ $tpl->title }}</span>
                @endforeach
            </div>
            <div style="font-size:.75rem;color:#3a8a7a;margin-top:3px">
                Milestones will be calculated from the start date
            </div>
        </div>

        {{-- Info box --}}
        <div style="padding:12px 14px;background:var(--parch);border-radius:12px;border:1px solid var(--warm-bd)">
            <div style="font-size:.72rem;color:var(--txt-md);line-height:1.6">
                💡 The patient will see all milestones with auto-calculated dates in their portal.
                Reminders are sent automatically before each milestone.
            </div>
        </div>

        <a href="{{ route('doctor.patients.history', $patient->id) }}"
           style="display:block;text-align:center;font-size:.8rem;color:var(--txt-lt);text-decoration:none;padding:6px;border:1.5px solid var(--warm-bd);border-radius:10px;transition:background .12s"
           onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
            ← Back to Patient History
        </a>
    </div>

</div>
@endsection

@push('styles')
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush
