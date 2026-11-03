@extends('layouts.patient')
@section('title', isset($fm) ? $fm->full_name.'\'s History' : 'Medical History')
@section('page-title', isset($fm) ? $fm->full_name.'\'s History' : 'Medical History')

@push('styles')
<style>
    /* ── Family member switcher ─────────────────────────────────────── */
    #fm-switcher::-webkit-scrollbar { height: 5px; }
    #fm-switcher::-webkit-scrollbar-thumb { background: var(--warm-bd); border-radius: 10px; }
    .fm-switcher-item {
        display: flex; flex-direction: column; align-items: center; gap: 6px;
        flex-shrink: 0;
    }
    .fm-switcher-ring {
        width: 60px; height: 60px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        border: 2.5px solid transparent; padding: 3px;
        box-sizing: border-box; transition: border-color .15s;
    }
    .fm-switcher-name {
        font-size: .72rem; font-weight: 500; color: var(--txt-md);
        max-width: 64px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        text-align: center;
    }

    /* ── Stat cards ──────────────────────────────────────────────────── */
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(74,55,96,.1); }

    /* ── Timeline rows ───────────────────────────────────────────────── */
    .timeline-row { transition: box-shadow .15s, transform .15s; }
    .timeline-row:hover { box-shadow: 0 4px 18px rgba(74,55,96,.1); transform: translateY(-1px); }

    /* ── Responsive ──────────────────────────────────────────────────── */
    @media (max-width: 768px) {
        #stats-strip { grid-template-columns: repeat(3, 1fr) !important; }
        #filter-bar { flex-direction: column; align-items: stretch !important; gap: 8px !important; }
        .filter-date-group { margin-left: 0 !important; }
        #upload-guide-grid { grid-template-columns: 1fr !important; }
        #upload-meta-grid  { grid-template-columns: 1fr !important; }
    }
    @media (max-width: 480px) {
        #stats-strip { grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; }
    }
</style>
@endpush

@section('content')
@php
$vtColors = [
    'consultation'    => ['color'=>'#3d7a6e','bg'=>'#eef5f3'],
    'follow_up'       => ['color'=>'#6a9e8e','bg'=>'#eef5f3'],
    'emergency'       => ['color'=>'#c0737a','bg'=>'#fce7ef'],
    'procedure'       => ['color'=>'#4a3760','bg'=>'#f4f0fa'],
    'teleconsultation'=> ['color'=>'#3d5e7a','bg'=>'#e8f0f9'],
];
@endphp

<div class="fade-slide">

{{-- ── Family member switcher ───────────────────────────────────────────────── --}}
@php $switcherMembers = $patient->familyMembers->where('relation', '!=', 'self')->values(); @endphp
@if($switcherMembers->isNotEmpty())
@php
    $fmColors = ['#4a3760','#3d7a6e','#7a5c3d','#3d5e7a','#7a3d4a'];
@endphp
<div id="fm-switcher" style="display:flex;gap:16px;margin-bottom:20px;overflow-x:auto;padding:2px 2px 8px">
    <a href="{{ route('patient.history.index') }}" class="fm-switcher-item" style="text-decoration:none">
        <div class="fm-switcher-ring" style="{{ !isset($fm) ? 'border-color:var(--plum)' : '' }}">
            <x-avatar name="{{ $patient->profile?->full_name ?? 'Me' }}" :photo="$patient->profile?->profile_photo"
                       :size="54" :radius="27" bg="var(--plum)" font-size=".85rem" />
        </div>
        <span class="fm-switcher-name" style="{{ !isset($fm) ? 'color:var(--plum);font-weight:700' : '' }}">Myself</span>
    </a>
    @foreach($switcherMembers as $i => $member)
    @php $active = isset($fm) && $fm->id === $member->id; @endphp
    <a href="{{ route('patient.history.member', $member->id) }}" class="fm-switcher-item" style="text-decoration:none">
        <div class="fm-switcher-ring" style="{{ $active ? 'border-color:var(--plum)' : '' }}">
            <x-avatar name="{{ $member->full_name }}" :photo="$member->profile_photo"
                       :size="54" :radius="27" :bg="$fmColors[$i % count($fmColors)]" font-size=".85rem" />
        </div>
        <span class="fm-switcher-name" style="{{ $active ? 'color:var(--plum);font-weight:700' : '' }}">{{ Str::limit($member->full_name, 12) }}</span>
    </a>
    @endforeach
</div>
@endif

{{-- ── Stats strip ──────────────────────────────────────────────────────────── --}}
<div id="stats-strip" style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:22px">
    @php
    $strips = [
        ['v' => $stats['total_visits'],        'l' => 'Total Visits',       'icon' => '🏥', 'bg' => '#eef5f3'],
        ['v' => $stats['visits_this_year'],     'l' => 'This Year',          'icon' => '📅', 'bg' => '#e8f0f9'],
        ['v' => $stats['total_prescriptions'],  'l' => 'Prescriptions',      'icon' => '📋', 'bg' => '#f4f0fa'],
        ['v' => $stats['doctors_seen'],         'l' => 'Doctors Seen',       'icon' => '👨‍⚕️', 'bg' => '#fce7ef'],
        ['v' => $stats['last_visit'] ? \Carbon\Carbon::parse($stats['last_visit'])->format('d M') : '—',
         'l' => 'Last Visit', 'icon' => '🕐', 'raw' => true, 'bg' => '#fef9ec'],
    ];
    @endphp
    @foreach($strips as $s)
    <div class="panel stat-card" style="padding:14px 14px 12px;text-align:center;transition:transform .15s,box-shadow .15s">
        <div style="width:30px;height:30px;border-radius:9px;background:{{ $s['bg'] }};display:flex;align-items:center;justify-content:center;font-size:1rem;margin:0 auto 7px">{{ $s['icon'] }}</div>
        <div style="font-family:'Lora',serif;font-size:{{ ($s['raw'] ?? false) ? '1rem' : '1.6rem' }};font-weight:500;color:var(--txt);line-height:1.1">
            {{ $s['v'] }}
        </div>
        <div style="font-size:.66rem;font-weight:600;color:var(--txt-lt);margin-top:3px;text-transform:uppercase;letter-spacing:.04em">{{ $s['l'] }}</div>
    </div>
    @endforeach
</div>

{{-- ── My Documents (patient-uploaded) ─────────────────────────────────────── --}}
<div x-data="historyDocs({{ isset($fm) ? $fm->id : 'null' }})" x-init="load()" style="margin-bottom:24px">

    {{-- Header row --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:8px">
            <span style="font-family:'Lora',serif;font-size:.95rem;font-weight:500;color:var(--txt)">My Documents</span>
            <span x-show="docs.length > 0"
                  x-text="docs.length + ' file' + (docs.length > 1 ? 's' : '')"
                  style="font-size:.68rem;padding:2px 8px;border-radius:20px;background:var(--parch);color:var(--txt-lt);border:1px solid var(--warm-bd)"></span>
        </div>
        <button type="button" @click="showPanel = !showPanel"
                style="display:flex;align-items:center;gap:6px;padding:6px 14px;border:1.5px solid var(--warm-bd);border-radius:8px;background:transparent;font-size:.78rem;font-weight:600;color:var(--txt-md);cursor:pointer;font-family:'Outfit',sans-serif;transition:all .12s"
                onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span x-text="showPanel ? 'Cancel' : 'Upload'"></span>
        </button>
    </div>

    {{-- Upload panel --}}
    <div x-show="showPanel" x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="panel" style="padding:20px;margin-bottom:12px;border:1.5px dashed var(--warm-bd)">

        {{-- Guide strip --}}
        <div style="background:#f9f6ff;border:1px solid #ddd6f3;border-radius:10px;padding:13px 16px;margin-bottom:16px">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#6d5fa6" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span style="font-size:.72rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#6d5fa6">Upload Guide</span>
            </div>
            <div id="upload-guide-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">

                {{-- Accepted formats --}}
                <div>
                    <div style="font-size:.68rem;font-weight:700;color:var(--txt-md);margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em">Accepted Formats</div>
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <div style="display:flex;align-items:center;gap:5px;font-size:.75rem;color:var(--txt)">
                            <span style="width:20px;height:20px;background:#fee2e2;color:#dc2626;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;font-size:.58rem;font-weight:700;flex-shrink:0">PDF</span>
                            PDF documents
                        </div>
                        <div style="display:flex;align-items:center;gap:5px;font-size:.75rem;color:var(--txt)">
                            <span style="width:20px;height:20px;background:#dbeafe;color:#2563eb;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;font-size:.55rem;font-weight:700;flex-shrink:0">JPG</span>
                            JPEG / JPG images
                        </div>
                        <div style="display:flex;align-items:center;gap:5px;font-size:.75rem;color:var(--txt)">
                            <span style="width:20px;height:20px;background:#d1fae5;color:#059669;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;font-size:.55rem;font-weight:700;flex-shrink:0">PNG</span>
                            PNG images
                        </div>
                        <div style="display:flex;align-items:center;gap:5px;font-size:.75rem;color:var(--txt)">
                            <span style="width:20px;height:20px;background:#fef3c7;color:#d97706;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;font-size:.5rem;font-weight:700;flex-shrink:0">WEBP</span>
                            WebP images
                        </div>
                    </div>
                    <div style="margin-top:6px;padding:5px 8px;background:#fee2e2;border-radius:6px;font-size:.68rem;color:#dc2626;font-weight:500">
                        ✕ &nbsp;Word, Excel, ZIP not accepted
                    </div>
                </div>

                {{-- Size & quality --}}
                <div>
                    <div style="font-size:.68rem;font-weight:700;color:var(--txt-md);margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em">File Size</div>
                    <div style="font-size:1.5rem;font-weight:700;color:#6d5fa6;line-height:1;margin-bottom:4px">10 MB</div>
                    <div style="font-size:.72rem;color:var(--txt-lt);margin-bottom:8px">maximum per file</div>
                    <div style="font-size:.68rem;font-weight:700;color:var(--txt-md);margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em">Tips</div>
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <div style="font-size:.72rem;color:var(--txt);display:flex;gap:5px;align-items:flex-start">
                            <span style="color:#6d5fa6;margin-top:1px;flex-shrink:0">✓</span> Scan at 150–300 DPI for clear text
                        </div>
                        <div style="font-size:.72rem;color:var(--txt);display:flex;gap:5px;align-items:flex-start">
                            <span style="color:#6d5fa6;margin-top:1px;flex-shrink:0">✓</span> Ensure full document is visible
                        </div>
                        <div style="font-size:.72rem;color:var(--txt);display:flex;gap:5px;align-items:flex-start">
                            <span style="color:#6d5fa6;margin-top:1px;flex-shrink:0">✓</span> One document per upload
                        </div>
                    </div>
                </div>

                {{-- What to upload --}}
                <div>
                    <div style="font-size:.68rem;font-weight:700;color:var(--txt-md);margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em">What You Can Upload</div>
                    <div style="display:flex;flex-direction:column;gap:3px">
                        @foreach([
                            ['📋', 'Old prescriptions'],
                            ['🧪', 'Lab / blood reports'],
                            ['🏥', 'Discharge summaries'],
                            ['🩻', 'X-rays / scans / MRI'],
                            ['💉', 'Vaccination records'],
                            ['📄', 'Insurance documents'],
                        ] as [$icon, $label])
                        <div style="font-size:.72rem;color:var(--txt);display:flex;gap:5px;align-items:center">
                            <span>{{ $icon }}</span> {{ $label }}
                        </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>

        {{-- Drag zone --}}
        <div @dragover.prevent="dragging=true" @dragleave.prevent="dragging=false"
             @drop.prevent="onDrop($event)"
             :style="dragging ? 'border-color:var(--plum);background:#f9f6ff' : ''"
             style="border:2px dashed var(--warm-bd);border-radius:12px;padding:28px 20px;text-align:center;transition:all .15s;cursor:pointer;margin-bottom:16px"
             @click="$refs.fileInput.click()">
            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"
                 style="margin:0 auto 8px;display:block;color:var(--txt-lt)">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <p style="font-size:.875rem;color:var(--txt-md);margin:0 0 4px">
                <strong>Drag &amp; drop</strong> your file here, or <span style="color:var(--plum);text-decoration:underline">click to browse</span>
            </p>
            <p style="font-size:.72rem;color:var(--txt-lt);margin:0">PDF · JPG · PNG · WebP &nbsp;·&nbsp; Max 10 MB per file</p>
            <input type="file" x-ref="fileInput" accept=".pdf,.jpg,.jpeg,.png,.webp" style="display:none"
                   @change="onFileSelect($event.target.files[0])">
        </div>

        {{-- Selected file preview --}}
        <div x-show="form.file" style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--parch);border-radius:10px;margin-bottom:14px">
            <div style="font-size:1.4rem" x-text="form.mime && form.mime.startsWith('image/') ? '🖼️' : '📄'"></div>
            <div style="flex:1;min-width:0">
                <div style="font-size:.8rem;font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="form.fileName"></div>
                <div style="font-size:.7rem;color:var(--txt-lt)" x-text="form.fileSize"></div>
            </div>
            <button type="button" @click="clearFile()"
                    style="color:var(--txt-lt);background:none;border:none;cursor:pointer;font-size:1rem;padding:2px 6px">✕</button>
        </div>

        {{-- Meta fields --}}
        <div id="upload-meta-grid" style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px;margin-bottom:10px">
            <div>
                <label style="display:block;font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Title *</label>
                <input type="text" x-model="form.title"
                       style="width:100%;padding:.5rem .75rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.8375rem;color:var(--txt);background:#fff;outline:none;font-family:'Outfit',sans-serif;box-sizing:border-box;transition:border-color .15s"
                       :style="uploadErr.title ? 'border-color:#ef4444' : ''"
                       onfocus="this.style.borderColor='var(--plum)'" onblur="this.style.borderColor='var(--warm-bd)'"
                       placeholder="e.g. Blood Test Report Jan 2025">
                <p x-show="uploadErr.title" x-text="uploadErr.title" style="font-size:.68rem;color:#ef4444;margin-top:3px"></p>
            </div>
            <div>
                <label style="display:block;font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Type *</label>
                <select x-model="form.document_type"
                        style="width:100%;padding:.5rem .75rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.8375rem;color:var(--txt);background:#fff;outline:none;font-family:'Outfit',sans-serif;cursor:pointer;box-sizing:border-box">
                    <option value="prescription">Prescription</option>
                    <option value="lab_report">Lab Report</option>
                    <option value="discharge_summary">Discharge Summary</option>
                    <option value="scan_xray">Scan / X-Ray</option>
                    <option value="vaccination">Vaccination</option>
                    <option value="insurance">Insurance</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Document Date</label>
                <input type="date" x-model="form.document_date"
                       style="width:100%;padding:.5rem .75rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.8375rem;color:var(--txt);background:#fff;outline:none;font-family:'Outfit',sans-serif;box-sizing:border-box">
            </div>
        </div>
        <div style="margin-bottom:14px">
            <label style="display:block;font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Notes (optional)</label>
            <input type="text" x-model="form.notes"
                   style="width:100%;padding:.5rem .75rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.8375rem;color:var(--txt);background:#fff;outline:none;font-family:'Outfit',sans-serif;box-sizing:border-box"
                   onfocus="this.style.borderColor='var(--plum)'" onblur="this.style.borderColor='var(--warm-bd)'"
                   placeholder="e.g. Pre-surgery report, AIIMS">
        </div>

        {{-- Progress bar --}}
        <div x-show="uploading" style="margin-bottom:12px">
            <div style="height:4px;background:var(--parch);border-radius:4px;overflow:hidden">
                <div :style="'width:'+progress+'%;transition:width .2s;height:100%;background:var(--plum);border-radius:4px'"></div>
            </div>
            <p style="font-size:.7rem;color:var(--txt-lt);margin-top:4px" x-text="progress < 100 ? 'Uploading…' : 'Processing…'"></p>
        </div>

        <p x-show="uploadErr.file" x-text="uploadErr.file" style="font-size:.75rem;color:#ef4444;margin-bottom:10px"></p>
        <p x-show="uploadErr.general" x-text="uploadErr.general" style="font-size:.75rem;color:#ef4444;margin-bottom:10px"></p>

        <button type="button" @click="upload()" :disabled="uploading || !form.file"
                style="display:flex;align-items:center;gap:7px;padding:8px 20px;background:var(--plum,#4a3760);color:#fff;border:none;border-radius:9px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:opacity .15s"
                :style="(uploading || !form.file) ? 'opacity:.5;cursor:not-allowed' : ''">
            <span x-show="uploading" style="width:13px;height:13px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:doc-spin .6s linear infinite"></span>
            <span x-text="uploading ? 'Uploading…' : 'Save Document'"></span>
        </button>
    </div>

    {{-- Documents list --}}
    <div x-show="loading" style="padding:20px;text-align:center;color:var(--txt-lt);font-size:.8rem">Loading…</div>

    <div x-show="!loading && docs.length === 0 && !showPanel" class="panel">
        <div style="padding:20px 18px;display:flex;align-items:center;gap:12px;color:var(--txt-lt)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="flex-shrink:0;opacity:.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span style="font-size:.8rem">No documents uploaded yet. Upload old prescriptions or reports to keep them in one place.</span>
        </div>
    </div>

    <div x-show="!loading && docs.length > 0" style="display:flex;flex-direction:column;gap:6px">
        <template x-for="doc in docs" :key="doc.id">
            <div class="panel" style="padding:12px 16px;display:flex;align-items:center;gap:12px">
                {{-- Icon --}}
                <div style="width:38px;height:38px;border-radius:10px;background:var(--parch);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.15rem"
                     x-text="doc.is_image ? '🖼️' : '📄'"></div>

                {{-- Info --}}
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:2px">
                        <span style="font-weight:600;font-size:.875rem;color:var(--txt)" x-text="doc.title"></span>
                        <span x-text="docTypeLabel(doc.document_type)"
                              style="font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:20px;background:#f4f0fa;color:#4a3760"></span>
                    </div>
                    <div style="font-size:.72rem;color:var(--txt-lt);display:flex;gap:8px;flex-wrap:wrap">
                        <span x-text="doc.file_name"></span>
                        <span x-text="doc.file_size"></span>
                        <span x-show="doc.document_date" x-text="doc.document_date"></span>
                        <span x-show="doc.notes" x-text="doc.notes" style="font-style:italic"></span>
                    </div>
                </div>

                {{-- Actions --}}
                <div style="display:flex;gap:6px;flex-shrink:0">
                    <button type="button"
                            @click="$store.attachmentPreview.show(doc.view_url, doc.download_url, doc.file_name, doc.is_image ? 'image' : (doc.mime_type === 'application/pdf' ? 'pdf' : 'other'))"
                            style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;background:var(--plum,#4a3760);color:#fff;border:none;border-radius:7px;font-size:.72rem;font-weight:600;cursor:pointer;transition:opacity .12s"
                            onmouseover="this.style.opacity='.82'" onmouseout="this.style.opacity='1'">
                        View
                    </button>
                    <a :href="doc.download_url"
                       style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1.5px solid var(--warm-bd);border-radius:7px;color:var(--txt-md);text-decoration:none;flex-shrink:0"
                       title="Download">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </a>
                    <button type="button" @click="confirmDelete(doc)"
                            style="padding:5px 10px;border:1.5px solid #fecaca;border-radius:7px;background:transparent;font-size:.72rem;color:#dc2626;cursor:pointer;font-family:'Outfit',sans-serif;transition:background .12s"
                            onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                        Delete
                    </button>
                </div>
            </div>
        </template>
    </div>

    {{-- Delete confirm modal --}}
    <div x-show="delConfirm.show"
         style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;padding:20px"
         @click.self="delConfirm.show=false">
        <div style="background:#fff;border-radius:14px;padding:24px 26px;max-width:360px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.18)">
            <div style="font-family:'Lora',serif;font-size:1.1rem;color:var(--txt);margin-bottom:8px">Delete document?</div>
            <p style="font-size:.875rem;color:var(--txt-md);margin-bottom:18px">
                "<strong x-text="delConfirm.title"></strong>" will be permanently deleted.
            </p>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" @click="delConfirm.show=false"
                        style="padding:7px 16px;border:1.5px solid var(--warm-bd);border-radius:8px;background:transparent;font-size:.875rem;color:var(--txt-md);cursor:pointer;font-family:'Outfit',sans-serif">
                    Cancel
                </button>
                <button type="button" @click="doDelete()" :disabled="delConfirm.loading"
                        style="padding:7px 16px;border:none;border-radius:8px;background:#dc2626;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif"
                        :style="delConfirm.loading ? 'opacity:.6' : ''">
                    <span x-text="delConfirm.loading ? 'Deleting…' : 'Delete'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

{{-- ── Filter bar ───────────────────────────────────────────────────────────── --}}
@php $baseParams = isset($fm) ? ['member' => $fm->id] : []; @endphp
<div id="filter-bar" style="display:flex;gap:14px;margin-bottom:20px;flex-wrap:wrap;align-items:center">

    {{-- Type filter --}}
    <div class="filter-group" style="display:flex;gap:5px;overflow-x:auto">
        @foreach([null => 'All', 'record' => '🏥 Visits', 'prescription' => '📋 Prescriptions'] as $val => $lbl)
        <a href="{{ route(isset($fm) ? 'patient.history.member' : 'patient.history.index', array_filter(array_merge($baseParams, ['type'=>$val,'filter'=>$filter]))) }}"
           style="padding:5px 12px;border-radius:7px;font-size:.78rem;font-weight:500;text-decoration:none;white-space:nowrap;flex-shrink:0;border:1.5px solid {{ $type===$val ? 'var(--plum)' : 'var(--warm-bd)' }};background:{{ $type===$val ? 'var(--plum)' : 'transparent' }};color:{{ $type===$val ? '#fff' : 'var(--txt-md)' }};transition:all .12s">
            {{ $lbl }}
        </a>
        @endforeach
    </div>

    {{-- Date filter --}}
    <div class="filter-group filter-date-group" style="display:flex;gap:5px;margin-left:auto;overflow-x:auto">
        @foreach([null => 'All time', 'thisYear' => 'This year', 'last6m' => 'Last 6m', 'last30d' => 'Last 30d'] as $val => $lbl)
        <a href="{{ route(isset($fm) ? 'patient.history.member' : 'patient.history.index', array_filter(array_merge($baseParams, ['type'=>$type,'filter'=>$val]))) }}"
           style="padding:5px 12px;border-radius:7px;font-size:.75rem;font-weight:500;text-decoration:none;white-space:nowrap;flex-shrink:0;border:1.5px solid {{ $filter===$val ? 'var(--plum)' : 'var(--warm-bd)' }};background:{{ $filter===$val ? 'var(--plum)' : 'transparent' }};color:{{ $filter===$val ? '#fff' : 'var(--txt-md)' }};transition:all .12s">
            {{ $lbl }}
        </a>
        @endforeach
    </div>
</div>

{{-- ── Timeline ─────────────────────────────────────────────────────────────── --}}
@if($timeline->isEmpty())
<div class="panel" style="padding:44px 24px;text-align:center;color:var(--txt-lt)">
    <div style="font-size:2.5rem;margin-bottom:12px">📭</div>
    <div style="font-family:'Lora',serif;font-size:1rem;color:var(--txt-md)">No history found</div>
    <p style="font-size:.8rem;margin-top:4px">
        {{ $type || $filter ? 'Try clearing the filters.' : 'Records will appear here after doctor visits.' }}
    </p>
</div>
@else

{{-- Group by year-month --}}
@php
$grouped = $timeline->groupBy(fn($item) => $item['date']->format('M Y'));
@endphp

@foreach($grouped as $monthYear => $items)
<div style="margin-bottom:26px">
    {{-- Month header --}}
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div style="font-family:'Lora',serif;font-size:.95rem;font-weight:500;color:var(--txt)">{{ $monthYear }}</div>
        <div style="flex:1;height:1px;background:var(--warm-bd)"></div>
        <div style="font-size:.7rem;color:var(--txt-lt)">{{ $items->count() }} {{ Str::plural('entry', $items->count()) }}</div>
    </div>

    <div style="display:flex;flex-direction:column;gap:8px">
    @foreach($items as $item)
    @if($item['type'] === 'record')
    {{-- ── Medical Record row ──────────────────────────────────────────────── --}}
    @php $vt = $vtColors[$item['object']->visit_type] ?? $vtColors['consultation']; @endphp
    <a href="{{ route(isset($fm) ? 'patient.history.member.record' : 'patient.history.show', isset($fm) ? [$fm->id, $item['object']->id] : $item['object']->id) }}"
       style="text-decoration:none">
    <div class="panel timeline-row" style="padding:14px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">

        {{-- Icon --}}
        <div style="width:42px;height:42px;border-radius:11px;background:{{ $vt['bg'] }};display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0">
            🏥
        </div>

        {{-- Info --}}
        <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:3px">
                <span style="font-weight:600;font-size:.9rem;color:var(--txt)">
                    {{ Str::limit($item['label'], 70) }}
                </span>
                <span style="font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:20px;background:{{ $vt['bg'] }};color:{{ $vt['color'] }}">
                    {{ ucwords(str_replace('_',' ',$item['object']->visit_type)) }}
                </span>
            </div>
            <div style="font-size:.75rem;color:var(--txt-lt);display:flex;gap:10px;flex-wrap:wrap">
                <span>Dr. {{ $item['doctor'] }}</span>
                @if($item['spec'])<span>{{ $item['spec'] }}</span>@endif
                @if($item['object']->vitals)<span>🔬 Vitals</span>@endif
                @if($item['object']->attachments && count($item['object']->attachments))
                <span>📎 {{ count($item['object']->attachments) }} file{{ count($item['object']->attachments)>1?'s':'' }}</span>
                @endif
                @if($item['object']->follow_up_date)
                <span>📅 Follow-up {{ $item['object']->follow_up_date->format('d M Y') }}</span>
                @endif
            </div>
        </div>

        {{-- Date + arrow --}}
        <div style="text-align:right;flex-shrink:0">
            <div style="font-size:.75rem;font-weight:600;color:var(--txt-md)">{{ $item['date']->format('d M') }}</div>
            <div style="font-size:.68rem;color:var(--txt-lt)">{{ $item['date']->format('Y') }}</div>
        </div>
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:var(--txt-lt);flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </div>
    </a>

    @else
    {{-- ── Prescription row ─────────────────────────────────────────────────── --}}
    <div class="panel timeline-row" style="padding:14px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">

        {{-- Icon --}}
        <div style="width:42px;height:42px;border-radius:11px;background:#f4f0fa;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0">
            📋
        </div>

        {{-- Info --}}
        <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:3px">
                <span style="font-weight:600;font-size:.875rem;color:var(--txt)">{{ $item['object']->prescription_number }}</span>
                <span style="font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:20px;background:#f4f0fa;color:#4a3760">Prescription</span>
                @if($item['object']->is_sent_whatsapp)
                <span style="font-size:.65rem;padding:2px 7px;border-radius:20px;background:#d1fae5;color:#065f46">✓ WhatsApp</span>
                @endif
            </div>
            <div style="font-size:.75rem;color:var(--txt-lt)">
                <span>Dr. {{ $item['doctor'] }}</span>
                @if($item['spec'])<span style="margin-left:8px">{{ $item['spec'] }}</span>@endif
            </div>
            {{-- Medicine pills --}}
            @if($item['object']->medicines->isNotEmpty())
            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:5px">
                @foreach($item['object']->medicines->take(4) as $med)
                <span style="font-size:.68rem;padding:2px 8px;border-radius:20px;background:var(--parch);color:var(--txt-md);border:1px solid var(--warm-bd)">
                    {{ $med->medicine_name }}
                </span>
                @endforeach
                @if($item['object']->medicines->count() > 4)
                <span style="font-size:.68rem;color:var(--txt-lt)">+{{ $item['object']->medicines->count()-4 }} more</span>
                @endif
            </div>
            @endif
        </div>

        {{-- Date + view/download --}}
        @php
            $rxViewUrl     = route('patient.history.prescription.view', $item['object']->id);
            $rxDownloadUrl = route('patient.history.prescription.pdf', $item['object']->id);
            $rxName        = $item['object']->prescription_number . '.pdf';
        @endphp
        <div style="text-align:right;flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;gap:5px" x-data>
            <div>
                <div style="font-size:.75rem;font-weight:600;color:var(--txt-md)">{{ $item['date']->format('d M') }}</div>
                <div style="font-size:.68rem;color:var(--txt-lt)">{{ $item['date']->format('Y') }}</div>
            </div>
            <div style="display:flex;gap:5px">
                <button type="button" @click="$store.attachmentPreview.show(@js($rxViewUrl), @js($rxDownloadUrl), @js($rxName), 'pdf')"
                        style="font-size:.7rem;padding:4px 11px;background:var(--plum);color:#fff;border:none;border-radius:7px;font-weight:600;cursor:pointer;transition:opacity .12s"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    View
                </button>
                <a href="{{ $rxDownloadUrl }}" download="{{ $rxName }}"
                   style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:1.5px solid var(--warm-bd);border-radius:7px;color:var(--txt-md);text-decoration:none"
                   title="Download">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </a>
            </div>
        </div>
    </div>
    @endif

    @endforeach
    </div>
</div>
@endforeach

@endif

</div>
@endsection

@push('scripts')
<script>
const DOC_INDEX_URL   = '{{ route("patient.history.documents.index") }}';
const DOC_STORE_URL   = '{{ route("patient.history.documents.store") }}';
const DOC_DESTROY_BASE= '{{ url("patient/history/documents") }}';
const CSRF_TOKEN      = '{{ csrf_token() }}';

function historyDocs(memberId) {
    return {
        memberId,
        docs: [],
        loading: true,
        showPanel: false,
        dragging: false,
        uploading: false,
        progress: 0,
        form: { file: null, fileName: '', fileSize: '', mime: '', title: '', document_type: 'prescription', document_date: '', notes: '' },
        uploadErr: {},
        delConfirm: { show: false, id: null, title: '', loading: false },

        async load() {
            this.loading = true;
            try {
                const url = DOC_INDEX_URL + (this.memberId ? '?member=' + this.memberId : '');
                const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN } });
                const data = await res.json();
                this.docs  = Array.isArray(data) ? data : [];
            } catch { this.docs = []; }
            finally { this.loading = false; }
        },

        onDrop(e) {
            this.dragging = false;
            const file = e.dataTransfer.files[0];
            if (file) this.setFile(file);
        },

        onFileSelect(file) {
            if (file) this.setFile(file);
        },

        setFile(file) {
            const allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!allowed.includes(file.type)) { this.uploadErr = { file: 'Only PDF, JPG, PNG, WebP files are allowed.' }; return; }
            if (file.size > 10 * 1024 * 1024) { this.uploadErr = { file: 'File must be under 10 MB.' }; return; }
            this.uploadErr = {};
            this.form.file     = file;
            this.form.fileName = file.name;
            this.form.fileSize = this.humanSize(file.size);
            this.form.mime     = file.type;
            if (!this.form.title) this.form.title = file.name.replace(/\.[^/.]+$/, '');
        },

        clearFile() { this.form.file = null; this.form.fileName = ''; this.form.fileSize = ''; this.form.mime = ''; this.$refs.fileInput.value = ''; },

        async upload() {
            this.uploadErr = {};
            if (!this.form.file)    { this.uploadErr = { file: 'Please select a file.' }; return; }
            if (!this.form.title.trim()) { this.uploadErr = { title: 'Title is required.' }; return; }

            const fd = new FormData();
            fd.append('file', this.form.file);
            fd.append('title', this.form.title.trim());
            fd.append('document_type', this.form.document_type);
            fd.append('document_date', this.form.document_date);
            fd.append('notes', this.form.notes);
            if (this.memberId) fd.append('member_id', this.memberId);
            fd.append('_token', CSRF_TOKEN);

            this.uploading = true;
            this.progress  = 0;

            try {
                await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', DOC_STORE_URL);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.setRequestHeader('X-CSRF-TOKEN', CSRF_TOKEN);
                    xhr.upload.onprogress = e => { if (e.lengthComputable) this.progress = Math.round(e.loaded / e.total * 90); };
                    xhr.onload = () => {
                        this.progress = 100;
                        try {
                            if (xhr.status === 201) {
                                const doc = JSON.parse(xhr.responseText);
                                if (!Array.isArray(this.docs)) this.docs = [];
                                this.docs.unshift(doc);
                                this.showPanel = false;
                                this.resetForm();
                                resolve();
                            } else {
                                if (xhr.status === 419) {
                                    this.uploadErr = { general: 'Session expired. Please refresh the page and try again.' };
                                    reject(); return;
                                }
                                let body = {};
                                try { body = JSON.parse(xhr.responseText); } catch {}
                                if (body.errors) {
                                    // flatten all validation messages into one readable string
                                    const msgs = Object.values(body.errors).flat();
                                    this.uploadErr = { general: msgs.join(' ') };
                                } else {
                                    this.uploadErr = { general: body.message || 'Upload failed (HTTP ' + xhr.status + ').' };
                                }
                                reject();
                            }
                        } catch (e) {
                            this.uploadErr = { general: 'Upload failed (HTTP ' + xhr.status + '). ' + (e?.message || 'Please try again.') };
                            reject(e);
                        }
                    };
                    xhr.onerror = () => { this.uploadErr = { general: 'Network error. Please try again.' }; reject(); };
                    xhr.ontimeout = () => { this.uploadErr = { general: 'Request timed out.' }; reject(); };
                    xhr.send(fd);
                });
            } catch { /* errors already set */ }
            finally { this.uploading = false; this.progress = 0; }
        },

        resetForm() {
            this.form = { file: null, fileName: '', fileSize: '', mime: '', title: '', document_type: 'prescription', document_date: '', notes: '' };
            this.uploadErr = {};
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        confirmDelete(doc) { this.delConfirm = { show: true, id: doc.id, title: doc.title, loading: false }; },

        async doDelete() {
            this.delConfirm.loading = true;
            try {
                const res = await fetch(`${DOC_DESTROY_BASE}/${this.delConfirm.id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                });
                const json = await res.json();
                if (json.success) {
                    this.docs = this.docs.filter(d => d.id !== this.delConfirm.id);
                    this.delConfirm.show = false;
                }
            } catch { alert('Delete failed.'); }
            finally { this.delConfirm.loading = false; }
        },

        docTypeLabel(t) {
            return { prescription:'Prescription', lab_report:'Lab Report', discharge_summary:'Discharge', scan_xray:'Scan / X-Ray', vaccination:'Vaccination', insurance:'Insurance', other:'Other' }[t] ?? t;
        },

        humanSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
            return (bytes/1048576).toFixed(1) + ' MB';
        },
    };
}
</script>
<style>@keyframes doc-spin { to { transform: rotate(360deg); } }</style>
@endpush
