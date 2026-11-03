@extends('layouts.doctor')
@section('title', 'Manage Availability')
@section('page-title')
    <a href="{{ route('doctor.appointments.calendar') }}" style="color:var(--txt-lt);text-decoration:none;font-size:.85rem;font-weight:400">Appointments</a>
    <span style="color:var(--txt-lt);margin:0 6px">/</span>
    Manage Availability
@endsection

@section('content')
@php
    $days     = ['monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday'];
    $duration = config('medtech.appointment.default_duration', 15);
@endphp

@if(session('success'))
<div style="padding:12px 16px;background:#eef5f3;border:1px solid #b5ddd5;border-radius:10px;margin-bottom:16px;font-size:.875rem;color:#2a7a6a;display:flex;align-items:center;gap:8px">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    {{ session('success') }}
</div>
@endif

<div x-data="slotsManager()" class="fade-in">

{{-- Hidden form — inputs injected by submitForm() before submit --}}
<form id="slots-form" method="POST" action="{{ route('doctor.appointments.slots.save') }}">
    @csrf
    <div id="slots-hidden-inputs"></div>
</form>

<div class="dr-grid-2col" style="display:grid;grid-template-columns:1fr 260px;gap:20px;align-items:start">

{{-- ── LEFT: Day blocks ─────────────────────────────────────────────────────── --}}
<div class="panel">
    <div style="padding:16px 22px;border-bottom:1px solid var(--warm-bd);display:flex;align-items:center;justify-content:space-between">
        <div>
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--txt)">Weekly Schedule</div>
            <div style="font-size:.78rem;color:var(--txt-lt);margin-top:2px">Set which days and hours you are available</div>
        </div>
        <div style="font-size:.75rem;color:var(--txt-lt)">{{ $duration }}-min slots</div>
    </div>

    {{-- ── Day Picker Chips ─────────────────────────────────────────────────── --}}
    <div style="padding:14px 22px;border-bottom:1px solid var(--warm-bd)">
        <div style="font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:10px">Active Days</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach($days as $dayKey => $dayLabel)
            @php $isWeekend = in_array($dayKey, ['saturday','sunday']); @endphp
            @php $onBg    = $isWeekend ? '#c0737a' : '#3d7a5c'; @endphp
            @php $onBd    = $isWeekend ? '#c0737a' : '#3d7a5c'; @endphp
            <button type="button"
                    @click="schedule['{{ $dayKey }}'].enabled = !schedule['{{ $dayKey }}'].enabled"
                    :style="{
                        background:   schedule['{{ $dayKey }}'].enabled ? '{{ $onBg }}'  : '#fff',
                        color:        schedule['{{ $dayKey }}'].enabled ? '#fff'          : '#6b7280',
                        borderColor:  schedule['{{ $dayKey }}'].enabled ? '{{ $onBd }}'  : '#d1d5db',
                        boxShadow:    schedule['{{ $dayKey }}'].enabled ? '0 1px 4px rgba(0,0,0,.12)' : 'none'
                    }"
                    style="padding:7px 18px;border-radius:24px;border:2px solid;font-size:.8125rem;font-weight:600;cursor:pointer;font-family:Outfit,sans-serif;transition:all .18s;min-width:52px;line-height:1.2;outline:none;text-align:center">
                {{ substr($dayLabel, 0, 3) }}
            </button>
            @endforeach
        </div>
    </div>

    @foreach($days as $dayKey => $dayLabel)
    @php $isWeekend = in_array($dayKey, ['saturday','sunday']); @endphp

    <div style="border-bottom:1px solid var(--warm-bd);padding:14px 22px;transition:background .15s"
         :style="{ background: schedule['{{ $dayKey }}'].enabled ? '' : '#fdfcfb', opacity: schedule['{{ $dayKey }}'].enabled ? '1' : '.75' }">

        <div class="dr-day-row" style="display:flex;align-items:flex-start;gap:14px">

            {{-- Toggle --}}
            <div style="padding-top:2px;flex-shrink:0">
                <button type="button"
                        @click="schedule['{{ $dayKey }}'].enabled = !schedule['{{ $dayKey }}'].enabled"
                        style="position:relative;width:38px;height:22px;border:none;border-radius:11px;cursor:pointer;padding:0;transition:background .2s;outline:none;flex-shrink:0"
                        :style="{ background: schedule['{{ $dayKey }}'].enabled ? 'var(--leaf,#3d7a5c)' : '#d1d5db' }">
                    <span style="position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;top:2px;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2)"
                          :style="{ left: schedule['{{ $dayKey }}'].enabled ? '18px' : '2px' }"></span>
                </button>
            </div>

            {{-- Day name --}}
            <div style="min-width:100px;padding-top:1px">
                <div style="font-size:.9rem;font-weight:600;color:{{ $isWeekend ? 'var(--coral,#c0737a)' : 'var(--txt)' }}">{{ $dayLabel }}</div>
                <div style="font-size:.72rem;color:var(--txt-lt);margin-top:1px"
                     x-text="schedule['{{ $dayKey }}'].enabled ? slotCount('{{ $dayKey }}') + ' slots' : 'Off'"></div>
            </div>

            {{-- Time blocks --}}
            <div x-show="schedule['{{ $dayKey }}'].enabled" style="flex:1;display:flex;flex-direction:column;gap:7px">
                <template x-for="(block, bi) in schedule['{{ $dayKey }}'].blocks" :key="bi">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <div style="display:flex;align-items:center;gap:6px;background:var(--parch);padding:6px 12px;border-radius:9px;border:1.5px solid var(--warm-bd)">
                            <input type="time" x-model="block.start"
                                   @change="validateBlock('{{ $dayKey }}', bi)"
                                   style="border:none;background:transparent;font-size:.875rem;color:var(--txt);outline:none;font-family:'Outfit',sans-serif;cursor:pointer">
                            <span style="font-size:.75rem;color:var(--txt-lt)">to</span>
                            <input type="time" x-model="block.end"
                                   @change="validateBlock('{{ $dayKey }}', bi)"
                                   style="border:none;background:transparent;font-size:.875rem;color:var(--txt);outline:none;font-family:'Outfit',sans-serif;cursor:pointer">
                        </div>
                        <div x-show="blockSlotCount(block) > 0"
                             style="font-size:.72rem;color:var(--txt-lt)"
                             x-text="blockSlotCount(block) + ' slots'"></div>
                        <div x-show="block.error"
                             x-text="block.error"
                             style="font-size:.72rem;color:#dc2626;font-weight:500"></div>
                        <button type="button"
                                @click="removeBlock('{{ $dayKey }}', bi)"
                                x-show="schedule['{{ $dayKey }}'].blocks.length > 1"
                                title="Remove"
                                style="width:26px;height:26px;border:1px solid var(--warm-bd);border-radius:7px;background:transparent;cursor:pointer;color:var(--txt-lt);display:inline-flex;align-items:center;justify-content:center;transition:all .12s;flex-shrink:0"
                                onmouseover="this.style.color='#dc2626';this.style.background='#fef2f2';this.style.borderColor='#fecaca'"
                                onmouseout="this.style.color='var(--txt-lt)';this.style.background='transparent';this.style.borderColor='var(--warm-bd)'">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>

                <button type="button" @click="addBlock('{{ $dayKey }}')"
                        style="align-self:flex-start;display:flex;align-items:center;gap:5px;font-size:.75rem;color:var(--leaf);background:none;border:none;cursor:pointer;font-family:'Outfit',sans-serif;font-weight:500;padding:2px 0">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add break
                </button>
            </div>

            <div x-show="!schedule['{{ $dayKey }}'].enabled"
                 style="flex:1;font-size:.8rem;color:var(--txt-lt);padding-top:2px">
                Toggle on to enable this day
            </div>
        </div>
    </div>
    @endforeach

    {{-- Quick actions --}}
    <div style="padding:12px 22px;background:var(--parch);display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:.78rem;color:var(--txt-lt)">Presets:</span>
        <button type="button" @click="setWeekdays()"
                style="font-size:.75rem;padding:5px 11px;border:1.5px solid var(--warm-bd);border-radius:8px;background:#fff;cursor:pointer;color:var(--txt-md);font-family:'Outfit',sans-serif;transition:background .12s"
                onmouseover="this.style.background='var(--sage-lt)'" onmouseout="this.style.background='#fff'">
            Weekdays
        </button>
        <button type="button" @click="setMonWedFri()"
                style="font-size:.75rem;padding:5px 11px;border:1.5px solid var(--warm-bd);border-radius:8px;background:#fff;cursor:pointer;color:var(--txt-md);font-family:'Outfit',sans-serif;transition:background .12s"
                onmouseover="this.style.background='var(--sage-lt)'" onmouseout="this.style.background='#fff'">
            Mon / Wed / Fri
        </button>
        <button type="button" @click="setTueThuSat()"
                style="font-size:.75rem;padding:5px 11px;border:1.5px solid var(--warm-bd);border-radius:8px;background:#fff;cursor:pointer;color:var(--txt-md);font-family:'Outfit',sans-serif;transition:background .12s"
                onmouseover="this.style.background='var(--sage-lt)'" onmouseout="this.style.background='#fff'">
            Tue / Thu / Sat
        </button>
        <button type="button" @click="copyMonToWeekdays()"
                style="font-size:.75rem;padding:5px 11px;border:1.5px solid var(--warm-bd);border-radius:8px;background:#fff;cursor:pointer;color:var(--txt-md);font-family:'Outfit',sans-serif;transition:background .12s"
                onmouseover="this.style.background='var(--sage-lt)'" onmouseout="this.style.background='#fff'">
            Copy Mon → Tue–Fri
        </button>
        <button type="button" @click="clearWeekend()"
                style="font-size:.75rem;padding:5px 11px;border:1.5px solid var(--warm-bd);border-radius:8px;background:#fff;cursor:pointer;color:var(--txt-md);font-family:'Outfit',sans-serif;transition:background .12s"
                onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='#fff'">
            Clear weekends
        </button>
        <button type="button" @click="enableAll()"
                style="font-size:.75rem;padding:5px 11px;border:1.5px solid var(--warm-bd);border-radius:8px;background:#fff;cursor:pointer;color:var(--txt-md);font-family:'Outfit',sans-serif;transition:background .12s"
                onmouseover="this.style.background='var(--sage-lt)'" onmouseout="this.style.background='#fff'">
            Enable all days
        </button>
    </div>
</div>

{{-- ── RIGHT sidebar ───────────────────────────────────────────────────────── --}}
<div class="dr-sidebar-sticky" style="position:sticky;top:78px;display:flex;flex-direction:column;gap:14px">

    <div class="panel" style="padding:16px 18px">
        <div style="font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:12px">Weekly Summary</div>
        @foreach($days as $dayKey => $dayLabel)
        <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--parch)">
            <span style="font-size:.82rem;font-weight:500;color:var(--txt-md)">{{ substr($dayLabel,0,3) }}</span>
            <template x-if="schedule['{{ $dayKey }}'].enabled">
                <span style="font-size:.78rem;color:var(--leaf);font-weight:600"
                      x-text="slotCount('{{ $dayKey }}') + ' slots'"></span>
            </template>
            <template x-if="!schedule['{{ $dayKey }}'].enabled">
                <span style="font-size:.78rem;color:var(--txt-lt)">Off</span>
            </template>
        </div>
        @endforeach
        <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--warm-bd);display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:.82rem;color:var(--txt-md)">Total / week</span>
            <span style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;font-weight:500;color:var(--txt)"
                  x-text="totalWeekSlots()"></span>
        </div>
    </div>

    <div class="panel" style="padding:14px 18px;background:var(--parch)">
        <div style="font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:7px">Info</div>
        <div style="font-size:.78rem;color:var(--txt-md);line-height:1.6">
            Each slot is <strong>{{ $duration }} minutes</strong>.<br>
            Patients see available dates & times when booking.<br>
            Existing bookings are not affected by changes.
        </div>
    </div>

    <button type="button"
            class="dr-save-btn"
            @click="submitForm()"
            :disabled="saving || hasErrors()"
            :class="{ 'is-busy': saving, 'is-blocked': hasErrors() }">
        <span class="dr-save-spinner" x-show="saving"></span>
        <svg class="dr-save-icon" x-show="!saving && !hasErrors()" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <span x-show="!saving && !hasErrors()">Save Availability</span>
        <span x-show="!saving && hasErrors()">Fix time errors first</span>
        <span x-show="saving">Saving…</span>
    </button>

    <div x-show="hasErrors()" style="font-size:.78rem;color:#dc2626;text-align:center;padding:0 4px">
        ⚠ One or more time blocks have errors.
    </div>

    <a href="{{ route('doctor.appointments.calendar') }}"
       style="display:block;text-align:center;font-size:.8125rem;color:var(--txt-lt);text-decoration:none;padding:4px"
       onmouseover="this.style.color='var(--txt)'" onmouseout="this.style.color='var(--txt-lt)'">
        ← Back to Calendar
    </a>
</div>

</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- ── BLOCK SPECIFIC DATES ─────────────────────────────────────────────────── --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<div class="panel" style="margin-top:20px">
    <div style="padding:16px 22px;border-bottom:1px solid var(--warm-bd);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--txt)">Block Specific Dates</div>
            <div style="font-size:.78rem;color:var(--txt-lt);margin-top:2px">Mark dates or time slots unavailable for leave, travel, or personal work</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <button type="button" @click="showBlockForm = true"
                    x-show="!showBlockForm"
                    style="display:flex;align-items:center;gap:6px;padding:7px 14px;background:var(--ink,#2d2d2d);color:#fff;border:none;border-radius:9px;font-size:.8125rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:opacity .15s"
                    onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Block
            </button>
            <button type="button" @click="showBlockForm = false"
                    x-show="showBlockForm"
                    style="display:flex;align-items:center;gap:6px;padding:7px 14px;background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca;border-radius:9px;font-size:.8125rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .15s"
                    onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                ✕ Cancel
            </button>
        </div>
    </div>

    {{-- ── Add Block Form ──────────────────────────────────────────────────── --}}
    <div x-show="showBlockForm"
         style="padding:18px 22px;border-bottom:1px solid var(--warm-bd);background:var(--parch)">

        <div class="dr-block-form-grid" style="display:grid;grid-template-columns:180px auto 1fr;gap:14px;align-items:start">

            {{-- Date --}}
            <div>
                <label style="font-size:.75rem;font-weight:600;display:block;margin-bottom:5px"
                       :style="blockTouched && !blockForm.date ? 'color:#dc2626' : 'color:var(--txt-md)'">
                    Date <span x-show="blockTouched && !blockForm.date" style="font-weight:400">— required</span>
                </label>
                <input type="date"
                       x-model="blockForm.date"
                       :min="todayStr"
                       @change="onBlockDateChange()"
                       :style="blockTouched && !blockForm.date
                           ? 'border-color:#dc2626;background:#fef2f2'
                           : 'border-color:var(--warm-bd);background:#fff'"
                       style="width:100%;padding:8px 10px;border:1.5px solid;border-radius:9px;font-size:.875rem;color:var(--txt);font-family:'Outfit',sans-serif;box-sizing:border-box;transition:border-color .15s">
            </div>

            {{-- Block type --}}
            <div>
                <label style="font-size:.75rem;font-weight:600;color:var(--txt-md);display:block;margin-bottom:8px">Block Type</label>
                <div style="display:flex;gap:14px;margin-top:2px">
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:.875rem;color:var(--txt-md)">
                        <input type="radio" x-model="blockForm.type" value="full_day" style="accent-color:#2d2d2d;cursor:pointer;width:15px;height:15px">
                        Full Day
                    </label>
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:.875rem;color:var(--txt-md)">
                        <input type="radio" x-model="blockForm.type" value="partial" style="accent-color:#2d2d2d;cursor:pointer;width:15px;height:15px">
                        Specific Times
                    </label>
                </div>
            </div>

            {{-- Reason --}}
            <div>
                <label style="font-size:.75rem;font-weight:600;color:var(--txt-md);display:block;margin-bottom:5px">
                    Reason <span style="font-weight:400;color:var(--txt-lt)">(optional)</span>
                </label>
                <input type="text" x-model="blockForm.reason"
                       placeholder="e.g. Personal leave, Conference, Holiday…"
                       style="width:100%;padding:8px 10px;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.875rem;color:var(--txt);background:#fff;font-family:'Outfit',sans-serif;box-sizing:border-box">
            </div>
        </div>

        {{-- Full-day conflict warning (appointments already exist) --}}
        <div x-show="blockForm.type === 'full_day' && blockForm.date && bookedSlotsForDate.length > 0"
             style="margin-top:14px;padding:12px 14px;background:#fef9c3;border:1.5px solid #fde047;border-radius:9px;display:flex;align-items:flex-start;gap:10px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#854d0e" stroke-width="2.2" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <div style="font-size:.82rem;color:#854d0e;line-height:1.55">
                <strong>Cannot block full day</strong> — this date already has
                <strong x-text="bookedSlotsForDate.length"></strong> confirmed appointment(s)
                at <span x-text="bookedSlotsForDate.join(', ')"></span>.<br>
                Cancel or reschedule those appointments first, or switch to <strong>Specific Times</strong> to block only the free slots.
            </div>
        </div>

        {{-- Checking appointments spinner --}}
        <div x-show="loadingBookedSlots" style="margin-top:12px;font-size:.8rem;color:var(--txt-lt);display:flex;align-items:center;gap:7px">
            <span style="width:12px;height:12px;border:2px solid #d1d5db;border-top-color:#6b7280;border-radius:50%;animation:spin .6s linear infinite;flex-shrink:0;display:inline-block"></span>
            Checking appointments…
        </div>

        {{-- Partial slot picker --}}
        <div x-show="blockForm.type === 'partial' && blockForm.date" style="margin-top:14px">
            {{-- Section label — turns red when touched with no slots selected --}}
            <label style="font-size:.75rem;font-weight:600;display:block;margin-bottom:8px"
                   :style="blockTouched && blockForm.slots.length === 0 && slotsForBlockDate().length > 0
                       ? 'color:#dc2626' : 'color:var(--txt-md)'">
                Select Time Slots to Block
                <span x-show="blockTouched && blockForm.slots.length === 0 && slotsForBlockDate().length > 0"
                      style="font-weight:400"> — select at least one</span>
            </label>

            {{-- No available slots notice --}}
            <div x-show="slotsForBlockDate().length === 0"
                 style="font-size:.8rem;color:var(--txt-lt);font-style:italic;padding:6px 0">
                No slots available for this day — the day may be off or have no schedule set.
            </div>

            {{-- Slot pills wrapper — red outline when validation fails --}}
            <div :style="blockTouched && blockForm.slots.length === 0 && slotsForBlockDate().length > 0
                     ? 'border:1.5px dashed #fca5a5;border-radius:10px;padding:10px;background:#fef2f2'
                     : 'border:1.5px dashed transparent;border-radius:10px;padding:10px'"
                 style="transition:all .2s">
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                    <template x-for="slot in slotsForBlockDate()" :key="slot">
                        {{-- Booked slot — greyed out, not selectable --}}
                        <div x-show="bookedSlotsForDate.includes(slot)"
                             style="display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border-radius:20px;border:1.5px solid #e5e7eb;background:#f3f4f6;font-size:.78rem;font-weight:500;color:#9ca3af;cursor:not-allowed;user-select:none"
                             title="This slot already has a confirmed appointment">
                            <span x-text="slot"></span>
                            <span style="font-size:.62rem;background:#e5e7eb;color:#9ca3af;padding:1px 6px;border-radius:7px;line-height:1.5">booked</span>
                        </div>
                        {{-- Free slot — selectable --}}
                        <button type="button"
                                x-show="!bookedSlotsForDate.includes(slot)"
                                @click="toggleSlot(slot)"
                                :style="blockForm.slots.includes(slot)
                                    ? 'background:#c0737a;color:#fff;border-color:#c0737a'
                                    : 'background:#fff;color:var(--txt-md);border-color:var(--warm-bd)'"
                                style="display:inline-flex;align-items:center;padding:5px 11px;border-radius:20px;border:1.5px solid;font-size:.78rem;font-weight:500;cursor:pointer;transition:all .12s;font-family:'Outfit',sans-serif">
                            <span x-text="slot"></span>
                        </button>
                    </template>
                </div>
                <div x-show="slotsForBlockDate().length > 0" style="margin-top:8px;display:flex;align-items:center;gap:6px">
                    {{-- Select all: only free (non-booked) slots --}}
                    <button type="button"
                            @click="blockForm.slots = slotsForBlockDate().filter(s => !bookedSlotsForDate.includes(s))"
                            style="font-size:.72rem;color:var(--txt-lt);background:none;border:none;cursor:pointer;padding:0;font-family:'Outfit',sans-serif"
                            onmouseover="this.style.color='#c0737a'" onmouseout="this.style.color='var(--txt-lt)'">
                        Select all free
                    </button>
                    <span style="color:var(--warm-bd)">·</span>
                    <button type="button" @click="blockForm.slots = []"
                            style="font-size:.72rem;color:var(--txt-lt);background:none;border:none;cursor:pointer;padding:0;font-family:'Outfit',sans-serif"
                            onmouseover="this.style.color='var(--txt)'" onmouseout="this.style.color='var(--txt-lt)'">
                        Clear
                    </button>
                </div>
            </div>
        </div>

        {{-- Save error --}}
        <div x-show="blockSaveError"
             style="margin-top:12px;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;font-size:.82rem;color:#dc2626;display:flex;align-items:center;gap:8px">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <span x-text="blockSaveError"></span>
        </div>

        <div style="margin-top:16px;display:flex;align-items:center;gap:10px">
            <button type="button" @click="addBlockedDate()"
                    :disabled="addingBlock"
                    style="padding:8px 22px;background:#c0737a;color:#fff;border:none;border-radius:9px;font-size:.875rem;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;display:flex;align-items:center;gap:7px;transition:opacity .12s"
                    :style="addingBlock ? 'opacity:.6;cursor:not-allowed' : 'opacity:1'"
                    onmouseover="if(!this.disabled) this.style.opacity='.82'" onmouseout="if(!this.disabled) this.style.opacity='1'">
                <span x-show="addingBlock" style="width:13px;height:13px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;flex-shrink:0"></span>
                <span x-show="!addingBlock">Block This Date</span>
                <span x-show="addingBlock">Saving…</span>
            </button>
        </div>
    </div>

    {{-- ── Blocked Dates List ──────────────────────────────────────────────── --}}
    <div style="padding:16px 22px">

        {{-- Success flash --}}
        <div x-show="blockSaved" x-transition
             style="margin-bottom:14px;padding:10px 14px;background:#eef5f3;border:1px solid #b5ddd5;border-radius:9px;font-size:.82rem;color:#2a7a6a;display:flex;align-items:center;gap:8px">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Date blocked successfully. Patients will not be able to book this slot.
        </div>

        <div x-show="blockedList().length === 0"
             style="text-align:center;padding:24px 0;font-size:.85rem;color:var(--txt-lt)">
            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.3" style="margin:0 auto 8px;display:block;opacity:.35"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            No dates are currently blocked. Use the button above to add leave or unavailability.
        </div>

        <template x-if="blockedList().length > 0">
            <div>
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:10px"
                     x-text="blockedList().length + ' blocked date' + (blockedList().length !== 1 ? 's' : '')"></div>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <template x-for="entry in blockedList()" :key="entry.date">
                        <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:1.5px solid var(--warm-bd);border-radius:10px;background:#fff;flex-wrap:wrap">

                            {{-- Date --}}
                            <div style="min-width:155px">
                                <div style="font-size:.875rem;font-weight:600;color:var(--txt)" x-text="formatDate(entry.date)"></div>
                                <div style="font-size:.7rem;color:var(--txt-lt);margin-top:1px" x-text="entry.date"></div>
                            </div>

                            {{-- Type badge --}}
                            <div :style="entry.type === 'full_day'
                                    ? 'background:#fef2f2;color:#dc2626;border-color:#fecaca'
                                    : 'background:#fff7ed;color:#c2710c;border-color:#fed7aa'"
                                 style="padding:3px 10px;border-radius:20px;border:1px solid;font-size:.72rem;font-weight:600;flex-shrink:0;white-space:nowrap">
                                <span x-show="entry.type === 'full_day'">Full Day Off</span>
                                <span x-show="entry.type !== 'full_day'"
                                      x-text="(entry.slots ? entry.slots.length : 0) + ' slots blocked'"></span>
                            </div>

                            {{-- Blocked slot times (partial) --}}
                            <div x-show="entry.type === 'partial' && entry.slots && entry.slots.length > 0"
                                 style="display:flex;flex-wrap:wrap;gap:4px;flex:1">
                                <template x-for="s in (entry.slots || [])" :key="s">
                                    <span x-text="s"
                                          style="font-size:.7rem;padding:2px 7px;border-radius:10px;background:#fff7ed;color:#c2710c;border:1px solid #fed7aa"></span>
                                </template>
                            </div>

                            {{-- Reason --}}
                            <div x-show="entry.reason" style="flex:1;font-size:.8rem;color:var(--txt-lt);font-style:italic;min-width:80px"
                                 x-text="entry.reason"></div>

                            {{-- Unblock --}}
                            <button type="button" @click="removeBlockedDate(entry.date)"
                                    style="padding:5px 12px;border:1.5px solid var(--warm-bd);border-radius:8px;background:transparent;font-size:.75rem;color:var(--txt-lt);cursor:pointer;font-family:'Outfit',sans-serif;flex-shrink:0;transition:all .12s"
                                    onmouseover="this.style.background='#fef2f2';this.style.color='#dc2626';this.style.borderColor='#fecaca'"
                                    onmouseout="this.style.background='transparent';this.style.color='var(--txt-lt)';this.style.borderColor='var(--warm-bd)'">
                                Unblock
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>

</div>
@endsection

@push('styles')
<style>
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Save Availability button (right sidebar) ─────────────────────────── */
.dr-save-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: .85rem 1rem;
    background: var(--ink, #1c2b2a);
    color: #fff;
    border: none;
    border-radius: 11px;
    font-family: 'Outfit', sans-serif;
    font-size: .9375rem;
    font-weight: 700;
    letter-spacing: .01em;
    line-height: 1.2;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(28, 43, 42, .22);
    transition: opacity .15s, box-shadow .15s, transform .12s;
}
.dr-save-btn:hover:not(:disabled) {
    opacity: .88;
    box-shadow: 0 4px 16px rgba(28, 43, 42, .28);
}
.dr-save-btn:active:not(:disabled) { transform: translateY(1px); }
.dr-save-btn:focus-visible {
    outline: 2px solid var(--leaf, #3d7a6e);
    outline-offset: 2px;
}
.dr-save-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
    box-shadow: none;
}
.dr-save-btn.is-blocked {
    background: #8a4b4f;
    box-shadow: 0 2px 10px rgba(138, 75, 79, .22);
}
.dr-save-btn.is-busy { cursor: progress; }
.dr-save-icon { flex-shrink: 0; }
.dr-save-spinner {
    width: 15px;
    height: 15px;
    border: 2px solid rgba(255, 255, 255, .3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .6s linear infinite;
    flex-shrink: 0;
}

@media (max-width: 640px) {
    .dr-day-row { flex-wrap: wrap; }
    .dr-block-form-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush

@push('scripts')
<script>
function slotsManager() {
    const DURATION = {{ $duration }};
    const DAYS     = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
    const initial  = @json($slots);
    const CSRF     = '{{ csrf_token() }}';

    function normalise(raw) {
        if (Array.isArray(raw) && raw.length > 0) {
            return {
                enabled: true,
                blocks:  raw.map(b => ({ start: b.start || '09:00', end: b.end || '13:00', error: '' })),
            };
        }
        return { enabled: false, blocks: [{ start: '09:00', end: '13:00', error: '' }] };
    }

    const norm = {};
    DAYS.forEach(d => { norm[d] = normalise(initial[d]); });

    const td   = new Date();
    const pad2 = n => String(n).padStart(2, '0');
    const todayStr = `${td.getFullYear()}-${pad2(td.getMonth() + 1)}-${pad2(td.getDate())}`;

    return {
        schedule: norm,
        saving:   false,

        // ── Blocked dates state ──────────────────────────────────────────────
        blocked:             @json((object)$blocked),
        showBlockForm:       false,
        blockForm:           { date: '', type: 'full_day', reason: '', slots: [] },
        addingBlock:         false,
        blockTouched:        false,
        blockSaved:          false,
        blockSaveError:      '',
        bookedSlotsForDate:  [],   // confirmed bookings on the selected date
        loadingBookedSlots:  false,
        todayStr,

        // ── Slot counting ────────────────────────────────────────────────────
        blockSlotCount(block) {
            if (!block.start || !block.end) return 0;
            const [sh, sm] = block.start.split(':').map(Number);
            const [eh, em] = block.end.split(':').map(Number);
            const mins = (eh * 60 + em) - (sh * 60 + sm);
            return mins > 0 ? Math.floor(mins / DURATION) : 0;
        },

        slotCount(day) {
            if (!this.schedule[day]?.enabled) return 0;
            return this.schedule[day].blocks.reduce((s, b) => s + this.blockSlotCount(b), 0);
        },

        totalWeekSlots() {
            return DAYS.reduce((s, d) => s + this.slotCount(d), 0);
        },

        // ── Validation ───────────────────────────────────────────────────────
        validateBlock(day, bi) {
            const b = this.schedule[day].blocks[bi];
            if (!b.start || !b.end) { b.error = ''; return; }
            const [sh, sm] = b.start.split(':').map(Number);
            const [eh, em] = b.end.split(':').map(Number);
            b.error = (eh * 60 + em) <= (sh * 60 + sm) ? 'End must be after start' : '';
        },

        hasErrors() {
            return DAYS.some(d =>
                this.schedule[d].enabled &&
                this.schedule[d].blocks.some(b => b.error)
            );
        },

        // ── Time block manipulation ──────────────────────────────────────────
        addBlock(day) {
            const last  = this.schedule[day].blocks.at(-1);
            if (!last?.end) return;
            const [eh, em] = last.end.split(':').map(Number);
            const sMin = Math.min(eh * 60 + em + 30, 22 * 60);
            const eMin = Math.min(sMin + 60, 23 * 60);
            this.schedule[day].blocks.push({
                start: `${pad2(Math.floor(sMin / 60))}:${pad2(sMin % 60)}`,
                end:   `${pad2(Math.floor(eMin / 60))}:${pad2(eMin % 60)}`,
                error: ''
            });
        },

        removeBlock(day, idx) {
            this.schedule[day].blocks.splice(idx, 1);
        },

        // ── Quick presets ────────────────────────────────────────────────────
        setWeekdays() {
            ['monday','tuesday','wednesday','thursday','friday'].forEach(d => { this.schedule[d].enabled = true; });
            ['saturday','sunday'].forEach(d => { this.schedule[d].enabled = false; });
        },

        setMonWedFri() {
            const mwf = ['monday','wednesday','friday'];
            DAYS.forEach(d => { this.schedule[d].enabled = mwf.includes(d); });
        },

        setTueThuSat() {
            const tts = ['tuesday','thursday','saturday'];
            DAYS.forEach(d => { this.schedule[d].enabled = tts.includes(d); });
        },

        copyMonToWeekdays() {
            const src = this.schedule.monday.blocks.map(b => ({ ...b, error: '' }));
            ['tuesday','wednesday','thursday','friday'].forEach(d => {
                this.schedule[d].enabled = this.schedule.monday.enabled;
                this.schedule[d].blocks  = src.map(b => ({ ...b }));
            });
        },

        clearWeekend() {
            ['saturday','sunday'].forEach(d => { this.schedule[d].enabled = false; });
        },

        enableAll() {
            const src = this.schedule.monday.blocks.map(b => ({ ...b, error: '' }));
            DAYS.forEach(d => {
                this.schedule[d].enabled = true;
                if (!this.schedule[d].blocks.length) {
                    this.schedule[d].blocks = src.map(b => ({ ...b }));
                }
            });
        },

        // ── Weekly schedule form submission ──────────────────────────────────
        submitForm() {
            if (this.hasErrors() || this.saving) return;
            this.saving = true;

            const container = document.getElementById('slots-hidden-inputs');
            container.innerHTML = '';

            const add = (name, value) => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = name;
                inp.value = value;
                container.appendChild(inp);
            };

            DAYS.forEach(day => {
                if (this.schedule[day].enabled) {
                    add(`slots[${day}][enabled]`, '1');
                    this.schedule[day].blocks.forEach((b, bi) => {
                        if (b.start && b.end && !b.error) {
                            add(`slots[${day}][blocks][${bi}][start]`, b.start);
                            add(`slots[${day}][blocks][${bi}][end]`,   b.end);
                        }
                    });
                }
            });

            document.getElementById('slots-form').submit();
        },

        // ── Blocked dates: compute slot times from current schedule ──────────
        slotsForBlockDate() {
            const dateStr = this.blockForm.date;
            if (!dateStr) return [];
            // Use noon to avoid DST edge cases
            const d = new Date(dateStr + 'T12:00:00');
            const dayNames = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
            const dayName  = dayNames[d.getDay()];

            if (!this.schedule[dayName]?.enabled) return [];

            const slots = [];
            this.schedule[dayName].blocks.forEach(block => {
                if (!block.start || !block.end || block.error) return;
                const [sh, sm] = block.start.split(':').map(Number);
                const [eh, em] = block.end.split(':').map(Number);
                let cursor = sh * 60 + sm;
                const endMin = eh * 60 + em;
                while (cursor + DURATION <= endMin) {
                    slots.push(`${pad2(Math.floor(cursor / 60))}:${pad2(cursor % 60)}`);
                    cursor += DURATION;
                }
            });

            return slots;
        },

        // ── Fetch confirmed bookings for a date (to prevent double-blocking) ──
        async onBlockDateChange() {
            this.blockForm.slots  = [];
            this.bookedSlotsForDate = [];
            this.blockSaveError   = '';
            if (!this.blockForm.date) return;
            this.loadingBookedSlots = true;
            try {
                const res  = await fetch(
                    '{{ route("doctor.appointments.slots.available") }}?date=' + this.blockForm.date,
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                const json = await res.json();
                this.bookedSlotsForDate = json.booked || [];
            } catch (e) {
                this.bookedSlotsForDate = [];
            } finally {
                this.loadingBookedSlots = false;
            }
        },

        // ── Toggle a slot pill (skip if already booked) ───────────────────────
        toggleSlot(slot) {
            if (this.bookedSlotsForDate.includes(slot)) return;
            const idx = this.blockForm.slots.indexOf(slot);
            if (idx >= 0) this.blockForm.slots.splice(idx, 1);
            else          this.blockForm.slots.push(slot);
        },

        // ── Blocked dates list helpers ────────────────────────────────────────
        blockedList() {
            return Object.entries(this.blocked)
                .map(([date, data]) => ({ date, ...data }))
                .sort((a, b) => a.date.localeCompare(b.date));
        },

        formatDate(dateStr) {
            const d = new Date(dateStr + 'T12:00:00');
            return d.toLocaleDateString('en-IN', {
                weekday: 'short', day: 'numeric', month: 'short', year: 'numeric',
            });
        },

        // ── Add a blocked date (AJAX) ─────────────────────────────────────────
        async addBlockedDate() {
            this.blockTouched   = true;
            this.blockSaveError = '';

            // Client-side validation
            if (!this.blockForm.date) return;
            if (this.blockForm.type === 'partial' && this.blockForm.slots.length === 0) return;

            // Prevent full-day block when confirmed appointments exist on that date
            if (this.blockForm.type === 'full_day' && this.bookedSlotsForDate.length > 0) {
                this.blockSaveError =
                    `This date has ${this.bookedSlotsForDate.length} confirmed appointment(s). ` +
                    'Cancel or reschedule them first, or choose "Specific Times" to block only the free slots.';
                return;
            }

            this.addingBlock = true;

            const data = new FormData();
            data.append('_token', CSRF);
            data.append('date',   this.blockForm.date);
            data.append('type',   this.blockForm.type);
            if (this.blockForm.reason) data.append('reason', this.blockForm.reason);
            if (this.blockForm.type === 'partial') {
                this.blockForm.slots.forEach(s => data.append('slots[]', s));
            }

            try {
                const res  = await fetch('{{ route("doctor.appointments.slots.block") }}', {
                    method: 'POST', body: data,
                });
                // Always parse JSON — server may return 422 with error details
                const json = await res.json();
                if (json.success) {
                    this.blocked            = json.blocked;
                    this.blockForm          = { date: '', type: 'full_day', reason: '', slots: [] };
                    this.bookedSlotsForDate = [];
                    this.blockTouched       = false;
                    this.showBlockForm      = false;
                    this.blockSaved         = true;
                    setTimeout(() => { this.blockSaved = false; }, 3000);
                } else {
                    this.blockSaveError = json.error || 'Could not save the block. Please try again.';
                }
            } catch (e) {
                this.blockSaveError = 'Something went wrong. Check your connection and try again.';
            } finally {
                this.addingBlock = false;
            }
        },

        // ── Remove a blocked date (AJAX) ──────────────────────────────────────
        async removeBlockedDate(date) {
            const data = new FormData();
            data.append('_token', CSRF);
            data.append('date',   date);

            try {
                const res  = await fetch('{{ route("doctor.appointments.slots.unblock") }}', {
                    method: 'POST', body: data,
                });
                const json = await res.json();
                if (json.success) {
                    const updated = { ...this.blocked };
                    delete updated[date];
                    this.blocked = updated;
                }
            } catch (e) { console.error(e); }
        },
    };
}
</script>
@endpush
