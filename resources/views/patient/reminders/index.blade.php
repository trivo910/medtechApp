@extends('layouts.patient')
@section('title', 'Medication Reminders')
@section('page-title', 'Medication Reminders')

@push('styles')
<style>
.rem-card { transition: box-shadow .15s; }
.rem-card:hover { box-shadow: 0 3px 16px rgba(74,55,96,.1); }
.time-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: .75rem; font-weight: 600; background: var(--plum); color: #fff; font-family: 'Lora', serif; }
.toggle-switch { position: relative; width: 38px; height: 22px; cursor: pointer; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-track { position: absolute; inset: 0; border-radius: 11px; background: var(--warm-bd); transition: .25s; }
.toggle-track::before { content: ''; position: absolute; left: 3px; top: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: .25s; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
input:checked + .toggle-track { background: var(--sage); }
input:checked + .toggle-track::before { transform: translateX(16px); }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 900px) {
    .rem-grid    { grid-template-columns: 1fr !important; }
    .rem-sidebar { position: static !important; }
    .rem-grid > div { min-width: 0; }
}
</style>
@endpush

@section('content')
<div class="fade-slide" x-data="reminders()">

{{-- ── Today's schedule (if any active) ───────────────────────────────────── -- --}}
@if($dueToday->isNotEmpty())
<div class="panel" style="padding:16px 20px;margin-bottom:20px;border-left:4px solid var(--amber);background:var(--amber-lt)">
    <div style="font-family:'Lora',serif;font-size:.95rem;color:var(--txt);margin-bottom:10px;display:flex;align-items:center;gap:8px">
        <span>⏰</span> Today's Medication Schedule
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
        @foreach($dueToday as $rem)
        @foreach($rem->reminder_times ?? [] as $t)
        <div style="padding:7px 14px;background:rgba(201,138,58,.15);border:1px solid rgba(201,138,58,.3);border-radius:9px;display:flex;align-items:center;gap:8px">
            <span style="font-family:'Lora',serif;font-size:.9rem;font-weight:600;color:var(--amber)">
                {{ \Carbon\Carbon::createFromFormat('H:i', $t)->format('h:i A') }}
            </span>
            <div>
                <div style="font-size:.8125rem;font-weight:600;color:var(--txt)">{{ $rem->medicine_name }}</div>
                <div style="font-size:.7rem;color:var(--txt-lt)">{{ $rem->dosage }}
                    @if($rem->familyMember) · For {{ $rem->familyMember->full_name }} @endif
                </div>
            </div>
        </div>
        @endforeach
        @endforeach
    </div>
</div>
@endif

{{-- ── Main grid ───────────────────────────────────────────────────────────── -- --}}
<div class="rem-grid" style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

{{-- ── LEFT: Active reminders list ─────────────────────────────────────────── -- --}}
<div style="display:flex;flex-direction:column;gap:14px">

    <div style="display:flex;align-items:center;justify-content:space-between">
        <div>
            <h2 style="font-family:'Lora',serif;font-size:1.05rem;font-weight:500;color:var(--txt)">Active Reminders</h2>
            <div style="font-size:.75rem;color:var(--txt-lt);margin-top:1px">{{ $active->count() }} active · {{ $inactive->count() }} inactive</div>
        </div>
        <button @click="showForm=true" type="button"
                style="display:flex;align-items:center;gap:6px;padding:7px 14px;background:var(--plum);color:#fff;border:none;border-radius:9px;font-size:.8rem;font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New Reminder
        </button>
    </div>

    @if($active->isEmpty())
    <div class="panel" style="padding:36px 24px;text-align:center;color:var(--txt-lt)">
        <div style="font-size:2rem;margin-bottom:10px">💊</div>
        <div style="font-family:'Lora',serif;font-size:.9375rem;color:var(--txt-md)">No active reminders</div>
        <p style="font-size:.78rem;margin-top:4px;margin-bottom:14px">Set up WhatsApp or SMS reminders for your medications.</p>
        <button @click="showForm=true" type="button"
                style="padding:9px 20px;background:var(--plum);color:#fff;border:none;border-radius:10px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif">
            Add First Reminder
        </button>
    </div>
    @else
    @foreach($active as $rem)
    @php
        $chanIcon = match($rem->channel) { 'whatsapp'=>'💬', 'sms'=>'📱', default=>'🔔' };
        $chanLabel= match($rem->channel) { 'whatsapp'=>'WhatsApp', 'sms'=>'SMS', 'in_app'=>'In-app', default=>'Both' };
        $daysLeft = $rem->end_date ? today()->diffInDays($rem->end_date, false) : null;
    @endphp
    <div class="panel rem-card" style="padding:16px 20px" x-data="{ toggling: false }">
        <div style="display:flex;align-items:flex-start;gap:12px">
            {{-- Medicine icon --}}
            <div style="width:42px;height:42px;border-radius:11px;background:var(--plum);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0">💊</div>

            {{-- Info --}}
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap">
                    <div>
                        <div style="font-weight:600;font-size:.9375rem;color:var(--txt)">{{ $rem->medicine_name }}</div>
                        <div style="font-size:.78rem;color:var(--txt-lt);margin-top:2px">
                            {{ $rem->dosage }}
                            @if($rem->familyMember) · For <strong style="color:var(--txt-md)">{{ $rem->familyMember->full_name }}</strong> @endif
                        </div>
                    </div>

                    {{-- Active toggle --}}
                    <label class="toggle-switch" title="{{ $rem->is_active ? 'Pause' : 'Activate' }}">
                        <input type="checkbox"
                               {{ $rem->is_active ? 'checked' : '' }}
                               x-show="!toggling"
                               @change="toggleReminder({{ $rem->id }}, $event.target.checked, '{{ route('patient.reminders.toggle', $rem) }}')">
                        <span class="toggle-track"></span>
                    </label>
                </div>

                @if($rem->prescriptionMedicine)
                <p style="font-size:.78rem;margin-top:8px">
                    {{ $rem->prescriptionMedicine->frequency }} · {{ str_replace('_', ' ', $rem->prescriptionMedicine->timing) }}
                    @if($rem->interval_days === 7) · Weekly from {{ $rem->start_date->format('l') }} @endif
                    @if($rem->start_date->gt(today())) · Starts {{ $rem->start_date->format('d M Y') }} @endif
                    <br>{{ $rem->prescriptionMedicine->special_instructions }}
                </p>
                <details style="font-size:.78rem;margin-top:8px">
                    <summary>Adjust notification times</summary>
                    <p>Suggested alert times. Follow the prescription's dose and food instructions.</p>
                    <form method="POST" action="{{ route('patient.reminders.update', $rem) }}">
                        @csrf @method('PUT')
                        @foreach($rem->reminder_times as $time)
                        <input type="time" name="reminder_times[]" value="{{ $time }}" required aria-label="Notification time">
                        @endforeach
                        <button type="submit">Save times</button>
                    </form>
                </details>
                @endif
                {{-- Reminder times --}}
                <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:10px">
                    @foreach($rem->reminder_times ?? [] as $t)
                    <span class="time-chip">
                        <svg width="9" height="9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        {{ \Carbon\Carbon::createFromFormat('H:i', $t)->format('h:i A') }}
                    </span>
                    @endforeach
                    <span style="font-size:.72rem;padding:3px 9px;border-radius:20px;background:var(--sand);color:var(--txt-md)">
                        {{ $chanIcon }} {{ $chanLabel }}
                    </span>
                </div>

                {{-- Date range + days left --}}
                <div style="margin-top:8px;display:flex;gap:14px;flex-wrap:wrap;font-size:.75rem;color:var(--txt-lt)">
                    <span>{{ $rem->start_date->gt(today()) ? 'Starts' : 'Started' }} {{ $rem->start_date->format('d M Y') }}</span>
                    @if($rem->end_date)
                    <span style="color:{{ $daysLeft <= 3 ? 'var(--rose)' : 'inherit' }};font-weight:{{ $daysLeft <= 3 ? '600' : '400' }}">
                        Ends {{ $rem->end_date->format('d M Y') }} (inclusive)
                        @if($daysLeft !== null) ({{ $daysLeft }}d left) @endif
                    </span>
                    @else
                    <span>No end date</span>
                    @endif
                    @if($rem->prescription)
                    <span>📋 From prescription {{ $rem->prescription->prescription_number }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Delete --}}
        <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--warm-bd);display:flex;justify-content:flex-end">
            <form method="POST" action="{{ route('patient.reminders.destroy', $rem) }}"
                  onsubmit="return confirm('Delete reminder for {{ $rem->medicine_name }}?')">
                @csrf @method('DELETE')
                <button type="submit"
                        style="font-size:.72rem;padding:4px 10px;border:1px solid #fecaca;border-radius:7px;background:transparent;color:#dc2626;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:background .12s"
                        onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                    Remove
                </button>
            </form>
        </div>
    </div>
    @endforeach
    @endif

    {{-- Inactive/expired --}}
    @if($inactive->isNotEmpty())
    <div style="margin-top:8px">
        <div style="font-family:'Lora',serif;font-size:.9rem;color:var(--txt-md);margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--warm-bd)">
            Inactive / Completed
        </div>
        @foreach($inactive as $rem)
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--warm-bd);opacity:.7">
            <div style="width:32px;height:32px;border-radius:8px;background:var(--sand);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">💊</div>
            <div style="flex:1;min-width:0">
                <div style="font-size:.8125rem;font-weight:500;color:var(--txt-md)">{{ $rem->medicine_name }}</div>
                <div style="font-size:.72rem;color:var(--txt-lt)">{{ $rem->dosage }}
                    @if($rem->end_date && $rem->end_date->lt(today())) · Ended {{ $rem->end_date->format('d M Y') }}@endif
                    @if($rem->schedule_note)<br>{{ $rem->schedule_note }}@endif
                    @if($rem->prescriptionMedicine)
                        <br>{{ $rem->prescriptionMedicine->frequency }} · {{ $rem->prescriptionMedicine->special_instructions }}
                    @endif
                </div>
            </div>
            <span style="font-size:.68rem;padding:2px 8px;border-radius:20px;background:var(--sand);color:var(--txt-lt);font-weight:600">
                {{ $rem->schedule_note ? 'Needs review / As needed' : ($rem->end_date?->lt(today()) ? 'Completed' : ($rem->prescription_id && $rem->prescription?->status !== 'issued' ? 'Stopped' : 'Paused')) }}
            </span>
            @if(!$rem->is_active && !$rem->schedule_note && (!$rem->end_date || $rem->end_date->gte(today())) && (!$rem->prescription_id || $rem->prescription?->status === 'issued'))
            <form method="POST" action="{{ route('patient.reminders.toggle', $rem) }}">
                @csrf
                <button type="submit">Resume reminders</button>
            </form>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- ── RIGHT: New reminder form ─────────────────────────────────────────────── -- --}}
<div class="rem-sidebar" style="position:sticky;top:78px" x-show="showForm" x-transition.opacity.duration.250ms>
    <div class="panel" style="padding:20px 22px" x-data="reminderForm()">
        <div style="font-family:'Lora',serif;font-size:1rem;font-weight:500;color:var(--txt);margin-bottom:4px">
            {{ $active->isEmpty() ? 'Set Up a Reminder' : 'New Reminder' }}
        </div>
        <div style="font-size:.75rem;color:var(--txt-lt);margin-bottom:16px">WhatsApp or SMS alerts for your medicines</div>

        <form method="POST" action="{{ route('patient.reminders.store') }}">
            @csrf

            @if($errors->any())
            <div style="padding:10px 12px;background:var(--rose-lt);border:1px solid #f0b0b5;border-radius:8px;margin-bottom:12px">
                @foreach($errors->all() as $e)
                <div style="font-size:.75rem;color:var(--rose)">• {{ $e }}</div>
                @endforeach
            </div>
            @endif

            {{-- Medicine name --}}
            <div style="margin-bottom:12px">
                <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);display:block;margin-bottom:5px">Medicine Name *</label>
                <input id="medicine_name_input" type="text" name="medicine_name" value="{{ old('medicine_name') }}"
                       placeholder="e.g. Metformin 500mg"
                       style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.875rem;color:var(--txt);background:var(--cream);outline:none;font-family:'Plus Jakarta Sans',sans-serif"
                       onfocus="this.style.borderColor='var(--plum)'" onblur="this.style.borderColor='var(--warm-bd)'"
                       required>
            </div>

            {{-- Dosage --}}
            <div style="margin-bottom:12px">
                <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);display:block;margin-bottom:5px">Dosage *</label>
                <input type="text" name="dosage" value="{{ old('dosage') }}"
                       placeholder="e.g. 1 tablet after meals"
                       style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.875rem;color:var(--txt);background:var(--cream);outline:none;font-family:'Plus Jakarta Sans',sans-serif"
                       onfocus="this.style.borderColor='var(--plum)'" onblur="this.style.borderColor='var(--warm-bd)'"
                       required>
            </div>

            {{-- Reminder times --}}
            <div style="margin-bottom:12px">
                <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);display:block;margin-bottom:5px">Reminder Times *</label>
                <div style="display:flex;flex-direction:column;gap:5px" id="times-container">
                    <template x-for="(t, i) in times" :key="i">
                        <div style="display:flex;gap:5px;align-items:center">
                            <input type="time" :name="'reminder_times[]'" x-model="times[i]"
                                   style="flex:1;padding:.5rem .7rem;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.875rem;color:var(--txt);background:var(--cream);outline:none;font-family:'Plus Jakarta Sans',sans-serif"
                                   required>
                            <button type="button" @click="removeTime(i)" x-show="times.length > 1"
                                    style="width:28px;height:28px;border:1px solid #fecaca;border-radius:7px;background:transparent;color:#dc2626;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center">
                                ×
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addTime()"
                        style="margin-top:6px;font-size:.75rem;color:var(--plum);background:transparent;border:1px dashed var(--mauve);border-radius:7px;padding:4px 12px;cursor:pointer;width:100%;font-family:'Plus Jakarta Sans',sans-serif">
                    + Add another time
                </button>
            </div>

            {{-- Start / end dates --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">
                <div>
                    <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);display:block;margin-bottom:5px">Start Date *</label>
                    <input type="date" name="start_date" value="{{ old('start_date', today()->format('Y-m-d')) }}"
                           style="width:100%;padding:.5rem .7rem;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.8rem;color:var(--txt);background:var(--cream);outline:none;font-family:'Plus Jakarta Sans',sans-serif"
                           required>
                </div>
                <div>
                    <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);display:block;margin-bottom:5px">End Date</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}"
                           style="width:100%;padding:.5rem .7rem;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.8rem;color:var(--txt);background:var(--cream);outline:none;font-family:'Plus Jakarta Sans',sans-serif">
                </div>
            </div>

            {{-- Channel --}}
            <div style="margin-bottom:12px">
                <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);display:block;margin-bottom:5px">Notify via *</label>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:5px">
                    @foreach(['whatsapp'=>['💬','WhatsApp'],'sms'=>['📱','SMS'],'both'=>['🔔','Both']] as $val=>[$ico,$lbl])
                    <label style="cursor:pointer;text-align:center">
                        <input type="radio" name="channel" value="{{ $val }}" {{ old('channel','whatsapp')===$val?'checked':'' }} style="display:none" class="ch-radio">
                        <div class="ch-pill" style="padding:7px 5px;border:1.5px solid {{ old('channel','whatsapp')===$val ? 'var(--plum)' : 'var(--warm-bd)' }};border-radius:8px;font-size:.72rem;font-weight:500;color:{{ old('channel','whatsapp')===$val ? 'var(--plum)' : 'var(--txt-md)' }};background:{{ old('channel','whatsapp')===$val ? 'var(--sand)' : 'transparent' }};transition:all .15s"
                             onclick="document.querySelectorAll('.ch-pill').forEach(p=>{p.style.borderColor='var(--warm-bd)';p.style.color='var(--txt-md)';p.style.background='transparent'}); this.style.borderColor='var(--plum)'; this.style.color='var(--plum)'; this.style.background='var(--sand)'">
                            {{ $ico }} {{ $lbl }}
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- For family member --}}
            @if($patient->familyMembers->isNotEmpty())
            <div style="margin-bottom:12px">
                <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);display:block;margin-bottom:5px">For</label>
                <select name="family_member_id" x-model="familyMember" @change="clearPrescription()" style="width:100%;padding:.5rem .7rem;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.8rem;color:var(--txt);background:var(--cream);outline:none;font-family:'Plus Jakarta Sans',sans-serif">
                    <option value="">Myself</option>
                    @foreach($patient->familyMembers as $fm)
                    <option value="{{ $fm->id }}" {{ old('family_member_id')==$fm->id?'selected':'' }}>{{ $fm->full_name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- Quick-add from prescription --}}
            @if($prescriptions->isNotEmpty())
            <div style="margin-bottom:14px;padding:10px 12px;background:var(--sand);border-radius:9px">
                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);margin-bottom:7px">Quick-fill from prescription</div>
                @foreach($prescriptions as $rx)
                @foreach($rx->medicines->take(3) as $med)
                <button type="button" x-show="prescriptionMatches({{ $rx->family_member_id ?? 'null' }})"
                        @click="fillFromPrescription('{{ addslashes($med->medicine_name) }}', '{{ addslashes($med->dosage ?? '1 tablet') }}', {{ $rx->id }})"
                        style="display:block;width:100%;text-align:left;padding:5px 8px;border:1px solid var(--warm-bd);border-radius:7px;background:var(--cream);font-size:.75rem;color:var(--txt-md);cursor:pointer;margin-bottom:4px;font-family:'Plus Jakarta Sans',sans-serif;transition:all .12s"
                        onmouseover="this.style.background='var(--rose-lt)'" onmouseout="this.style.background='var(--cream)'">
                    {{ $med->medicine_name }} <span style="color:var(--txt-lt)">· {{ $rx->rx_number }} · {{ $rx->familyMember?->full_name ?? 'Myself' }}</span>
                </button>
                @endforeach
                @endforeach
            </div>
            @endif

            <button type="submit"
                    style="width:100%;padding:.75rem;background:var(--plum);color:#fff;border:none;border-radius:11px;font-size:.9rem;font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:opacity .15s"
                    onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                Set Reminder
            </button>
        </form>
    </div>
</div>

</div>{{-- end grid --}}
</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
function reminders() {
    return {
        showForm: false,
        async toggleReminder(id, isActive, url) {
            await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' }
            });
        }
    };
}

function reminderForm() {
    return {
        times: ['08:00'],
        familyMember: '{{ old('family_member_id', '') }}',
        addTime() {
            if (this.times.length < 6) this.times.push('12:00');
        },
        removeTime(i) {
            this.times.splice(i, 1);
        },
        fillFromPrescription(name, dosage, rxId) {
            const form = this.$el.closest('form');
            form.querySelector('[name="medicine_name"]').value = name;
            form.querySelector('[name="dosage"]').value        = dosage;
            let prescription = form.querySelector('[name="prescription_id"]');
            if (!prescription) {
                prescription = document.createElement('input');
                prescription.type = 'hidden';
                prescription.name = 'prescription_id';
                form.appendChild(prescription);
            }
            prescription.value = rxId;
        },
        clearPrescription() {
            const prescription = this.$el.closest('form').querySelector('[name="prescription_id"]');
            if (prescription) prescription.remove();
        },
        prescriptionMatches(familyMemberId) {
            return String(familyMemberId ?? '') === String(this.familyMember ?? '');
        }
    };
}
</script>
@endpush
