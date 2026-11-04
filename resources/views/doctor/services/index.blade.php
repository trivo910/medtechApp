@extends('layouts.doctor')
@section('title', 'My Service List')
@section('page-title', 'Service List')

@push('styles')
<style>
.svc-table th { font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt);padding:10px 14px;border-bottom:1.5px solid var(--warm-bd);white-space:nowrap; }
.svc-table td { padding:10px 14px;border-bottom:1px solid var(--parch);font-size:.8625rem;color:var(--txt);vertical-align:middle; }
.svc-table tr:last-child td { border-bottom:none; }
.svc-table tr:hover td { background:var(--parch); }
.badge-off  { display:inline-block;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:600;background:#fef2f2;color:#9a3412;border:1px solid #fecaca;vertical-align:middle; }
.inp { width:100%;padding:.5rem .75rem;border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.8625rem;color:var(--txt);background:#fff;outline:none;font-family:'Outfit',sans-serif;transition:border-color .15s;box-sizing:border-box; }
.inp:focus { border-color:var(--leaf); }
.inp.err { border-color:#ef4444; }
select.inp { cursor:pointer; }
.svc-fg-4 { grid-template-columns:2fr 1.2fr 1fr .8fr; }
.svc-fg-5 { grid-template-columns:2fr 1fr 1fr 1fr 1fr; }
@media (max-width:900px) {
    .svc-fg-4, .svc-fg-5 { grid-template-columns:1fr 1fr; }
}
@media (max-width:500px) {
    .svc-fg-4, .svc-fg-5 { grid-template-columns:1fr; }
}
</style>
@endpush

@section('content')

@if(session('success'))
<div style="padding:11px 16px;background:#eef5f3;border:1px solid #b5ddd5;border-radius:10px;margin-bottom:16px;font-size:.875rem;color:#2a7a6a;display:flex;align-items:center;gap:8px">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    {{ session('success') }}
</div>
@endif

<div x-data="serviceManager()">

{{-- ── Header row ──────────────────────────────────────────────────────── --}}
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px">
    <div>
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.35rem;font-weight:500;color:var(--txt);margin:0">My Service List</h2>
        <p style="font-size:.78rem;color:var(--txt-lt);margin:3px 0 0">
            <strong style="color:var(--txt)">{{ $total }}</strong> service{{ $total !== 1 ? 's' : '' }} saved · offered as quick-add lines in the <strong style="color:var(--txt)">Complete Visit &amp; Payment</strong> panel
        </p>
    </div>
    <button type="button" @click="showAdd = !showAdd"
            style="display:flex;align-items:center;gap:6px;padding:8px 16px;background:var(--ink,#2d2d2d);color:#fff;border:none;border-radius:9px;font-size:.8125rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:opacity .15s"
            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        <span x-text="showAdd ? 'Cancel' : 'Add Service'"></span>
    </button>
</div>

{{-- ── Add Service Form ────────────────────────────────────────────────── --}}
<div x-show="showAdd" x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 -translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="panel" style="padding:20px 22px;margin-bottom:18px;border:1.5px solid var(--leaf,#3d7a5c)">
    <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--leaf);margin-bottom:14px">New Service</div>
    <form @submit.prevent="submitAdd()">
        <div class="svc-fg-4" style="display:grid;gap:12px;margin-bottom:14px">
            <div>
                <label style="display:block;font-size:.7rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Service Name *</label>
                <input type="text" x-model="addForm.name" class="inp" :class="addErr.name?'err':''" placeholder="e.g. Blood Pressure Check, CBC Test" autocomplete="off">
                <p x-show="addErr.name" x-text="addErr.name" style="font-size:.7rem;color:#ef4444;margin-top:3px"></p>
            </div>
            <div>
                <label style="display:block;font-size:.7rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Payment Head *</label>
                <select x-model="addForm.head" class="inp" :class="addErr.head?'err':''">
                    <option value="">Select…</option>
                    @foreach($heads as $key => $head)
                    <option value="{{ $key }}">{{ $head['label'] }}</option>
                    @endforeach
                </select>
                <p x-show="addErr.head" x-text="addErr.head" style="font-size:.7rem;color:#ef4444;margin-top:3px"></p>
            </div>
            <div>
                <label style="display:block;font-size:.7rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Default Price (₹) *</label>
                <input type="number" min="0" step="0.01" x-model="addForm.default_price" class="inp" :class="addErr.default_price?'err':''" placeholder="e.g. 500">
                <p x-show="addErr.default_price" x-text="addErr.default_price" style="font-size:.7rem;color:#ef4444;margin-top:3px"></p>
            </div>
            <div>
                <label style="display:block;font-size:.7rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:4px">Default Qty</label>
                <input type="number" min="1" max="999" x-model="addForm.default_quantity" class="inp" placeholder="1">
            </div>
        </div>
        <div style="display:flex;gap:8px">
            <button type="submit" :disabled="saving"
                    style="display:flex;align-items:center;gap:6px;padding:8px 20px;background:var(--leaf,#3d7a5c);color:#fff;border:none;border-radius:9px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;transition:opacity .15s"
                    :style="saving?'opacity:.6;cursor:not-allowed':''">
                <span x-show="saving" style="width:13px;height:13px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;flex-shrink:0"></span>
                <span x-text="saving ? 'Saving…' : 'Save Service'"></span>
            </button>
            <button type="button" @click="resetAdd()"
                    style="padding:8px 14px;border:1.5px solid var(--warm-bd);border-radius:9px;background:transparent;font-size:.875rem;color:var(--txt-md);cursor:pointer;font-family:'Outfit',sans-serif">
                Clear
            </button>
        </div>
    </form>
</div>

{{-- ── Table ───────────────────────────────────────────────────────────── --}}
<div class="panel" style="overflow:hidden;padding:0">
    @if($services->isEmpty())
    <div style="padding:52px 24px;text-align:center;color:var(--txt-lt)">
        <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 12px;display:block;opacity:.3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-2.485 0-4.5-1.343-4.5-3s2.015-3 4.5-3m9 10.5a9.5 9.5 0 11-19 0 9.5 9.5 0 0119 0z"/></svg>
        <p style="font-size:.9rem;font-weight:500;color:var(--txt-md)">
            Your service list is empty.
        </p>
        <p style="font-size:.78rem;margin-top:4px">Click <strong>Add Service</strong> above to save the charges you bill most often.</p>
    </div>
    @else
    <div class="dr-table-wrap">
    @foreach($services as $headLabel => $group)
    <div style="padding:9px 14px;background:var(--parch);border-bottom:1.5px solid var(--warm-bd);font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt)">
        {{ $headLabel }}
    </div>
    <table style="width:100%;border-collapse:collapse;min-width:620px" class="svc-table">
        <thead>
            <tr style="background:#fff">
                <th style="text-align:left">Service</th>
                <th style="text-align:left">Head</th>
                <th style="text-align:left">Price</th>
                <th style="text-align:left">Qty</th>
                <th style="text-align:right;padding-right:18px">Actions</th>
            </tr>
        </thead>
        <tbody>
        @foreach($group as $service)
        <tr x-data="editRow({{ $service->id }}, @js([
                'head'             => $service->head,
                'name'             => $service->name,
                'default_price'    => (string) $service->default_price,
                'default_quantity' => $service->default_quantity,
                'is_active'        => $service->is_active,
            ]))" style="position:relative;opacity:{{ $service->is_active ? '1' : '0.55' }}">

            {{-- View mode --}}
            <td x-show="!editing">
                <div style="font-weight:600;color:var(--txt)">
                    {{ $service->name }}
                    @if(!$service->is_active)<span class="badge-off" style="margin-left:6px">Inactive</span>@endif
                </div>
            </td>
            <td x-show="!editing" style="color:var(--txt-md);width:110px">{{ $service->headLabel() }}</td>
            <td x-show="!editing" style="color:var(--txt-md);width:120px;font-weight:600">₹{{ number_format((float) $service->default_price, 2) }}</td>
            <td x-show="!editing" style="color:var(--txt-md);width:70px">× {{ $service->default_quantity }}</td>
            <td x-show="!editing" style="width:300px;text-align:right;padding-right:18px;white-space:nowrap">
                <button type="button" @click="toggleActive({{ $service->id }}, {{ $service->is_active ? 'true' : 'false' }})"
                        style="padding:4px 11px;border:1.5px solid var(--warm-bd);border-radius:7px;background:transparent;font-size:.75rem;color:var(--txt-md);cursor:pointer;font-family:'Outfit',sans-serif;transition:all .12s;margin-right:4px"
                        onmouseover="this.style.background='var(--parch)';this.style.color='var(--txt)'" onmouseout="this.style.background='transparent';this.style.color='var(--txt-md)'">
                    {{ $service->is_active ? 'Deactivate' : 'Activate' }}
                </button>
                <button type="button" @click="editing = true"
                        style="padding:4px 11px;border:1.5px solid var(--warm-bd);border-radius:7px;background:transparent;font-size:.75rem;color:var(--txt-md);cursor:pointer;font-family:'Outfit',sans-serif;transition:all .12s;margin-right:4px"
                        onmouseover="this.style.background='var(--parch)';this.style.color='var(--txt)'" onmouseout="this.style.background='transparent';this.style.color='var(--txt-md)'">
                    Edit
                </button>
                <button type="button" @click="confirmDelete({{ $service->id }}, '{{ addslashes($service->name) }}')"
                        style="padding:4px 11px;border:1.5px solid #fecaca;border-radius:7px;background:transparent;font-size:.75rem;color:#dc2626;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .12s"
                        onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                    Delete
                </button>
            </td>

            {{-- Edit mode (inline, spans all columns) --}}
            <td x-show="editing" colspan="5" style="padding:14px 16px;background:var(--parch)">
                <div class="svc-fg-5" style="display:grid;gap:8px;margin-bottom:10px">
                    <div>
                        <label style="display:block;font-size:.65rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:3px">Name *</label>
                        <input type="text" x-model="form.name" class="inp" style="font-size:.82rem" placeholder="Service name">
                    </div>
                    <div>
                        <label style="display:block;font-size:.65rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:3px">Payment Head</label>
                        <select x-model="form.head" class="inp" style="font-size:.82rem">
                            @foreach($heads as $key => $head)
                            <option value="{{ $key }}">{{ $head['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:.65rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:3px">Price (₹)</label>
                        <input type="number" min="0" step="0.01" x-model="form.default_price" class="inp" style="font-size:.82rem">
                    </div>
                    <div>
                        <label style="display:block;font-size:.65rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:3px">Default Qty</label>
                        <input type="number" min="1" max="999" x-model="form.default_quantity" class="inp" style="font-size:.82rem">
                    </div>
                    <div>
                        <label style="display:block;font-size:.65rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:3px">Available</label>
                        <select x-model="form.is_active" class="inp" style="font-size:.82rem">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:8px">
                    <button type="button" @click="saveEdit({{ $service->id }})" :disabled="saving"
                            style="padding:6px 16px;background:var(--leaf,#3d7a5c);color:#fff;border:none;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;display:flex;align-items:center;gap:5px">
                        <span x-show="saving" style="width:11px;height:11px;border:1.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite"></span>
                        <span x-text="saving?'Saving…':'Save'"></span>
                    </button>
                    <button type="button" @click="editing=false;form={...original}"
                            style="padding:6px 12px;border:1.5px solid var(--warm-bd);border-radius:8px;background:transparent;font-size:.8rem;color:var(--txt-md);cursor:pointer;font-family:'Outfit',sans-serif">
                        Cancel
                    </button>
                    <span x-show="saveMsg" x-text="saveMsg" style="font-size:.78rem;color:var(--leaf);align-self:center"></span>
                </div>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endforeach
    </div>
    @endif
</div>

{{-- ── How it works ────────────────────────────────────────────────────── --}}
<div class="panel" style="padding:14px 18px;margin-top:14px">
    <div style="font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt);margin-bottom:6px">How this list works</div>
    <p style="font-size:.8rem;color:var(--txt-md);margin:0;line-height:1.55">
        Saved services appear as a quick-add dropdown inside the <strong>Complete Visit &amp; Payment</strong> panel on the
        appointment and prescription pages. Adding one fills the line automatically with its name, payment head, default
        price and quantity — you can still edit the line before collecting. Charges billed most often are the best
        candidates for this list.
    </p>
</div>

{{-- Delete confirmation modal --}}
<div x-show="deleteConfirm.show"
     style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;padding:20px"
     @click.self="deleteConfirm.show=false">
    <div style="background:#fff;border-radius:14px;padding:26px 28px;max-width:380px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.18)">
        <div style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;color:var(--txt);margin-bottom:8px">Remove service?</div>
        <p style="font-size:.875rem;color:var(--txt-md);margin-bottom:20px">
            "<strong x-text="deleteConfirm.name"></strong>" will be removed from your list. Existing bills are not affected.
        </p>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button type="button" @click="deleteConfirm.show=false"
                    style="padding:8px 16px;border:1.5px solid var(--warm-bd);border-radius:9px;background:transparent;font-size:.875rem;color:var(--txt-md);cursor:pointer;font-family:'Outfit',sans-serif">
                Cancel
            </button>
            <button type="button" @click="doDelete()" :disabled="deleteConfirm.loading"
                    style="padding:8px 18px;border:none;border-radius:9px;background:#dc2626;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;display:flex;align-items:center;gap:6px"
                    :style="deleteConfirm.loading?'opacity:.6':''">
                <span x-show="deleteConfirm.loading" style="width:13px;height:13px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite"></span>
                <span x-text="deleteConfirm.loading?'Removing…':'Remove'"></span>
            </button>
        </div>
    </div>
</div>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
const STORE_URL   = '{{ route("doctor.services.store") }}';
const UPDATE_BASE = '{{ url("doctor/services") }}';

function serviceManager() {
    return {
        showAdd: @json($services->isEmpty()),
        saving: false,
        addForm: emptyServiceForm(),
        addErr: {},
        deleteConfirm: { show: false, id: null, name: '', loading: false },

        resetAdd() {
            this.addForm = emptyServiceForm();
            this.addErr  = {};
        },

        async submitAdd() {
            this.addErr = {};
            if (!String(this.addForm.name || '').trim()) {
                this.addErr.name = 'Name is required.';
                return;
            }
            if (!this.addForm.head) {
                this.addErr.head = 'Pick a payment head.';
                return;
            }
            this.saving = true;
            try {
                const res = await fetch(STORE_URL, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body:    JSON.stringify(this.addForm),
                });
                const json = await res.json();
                if (res.ok && json.success) {
                    window.location.reload();
                } else if (json.errors) {
                    this.addErr = Object.fromEntries(Object.entries(json.errors).map(([k, v]) => [k, v[0]]));
                }
            } catch { alert('Save failed. Please try again.'); }
            finally { this.saving = false; }
        },

        confirmDelete(id, name) {
            this.deleteConfirm = { show: true, id, name, loading: false };
        },

        async doDelete() {
            this.deleteConfirm.loading = true;
            try {
                const res = await fetch(`${UPDATE_BASE}/${this.deleteConfirm.id}`, {
                    method:  'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
                if (res.ok) {
                    const tr = document.querySelector(`[x-data*="editRow(${this.deleteConfirm.id})"]`);
                    if (tr) tr.remove();
                    window.location.reload();
                }
            } catch { alert('Delete failed.'); }
            finally { this.deleteConfirm.loading = false; }
        },
    };
}

function editRow(id, original) {
    return {
        editing: false,
        saving:  false,
        saveMsg: '',
        original: { ...original },
        form: { ...original },

        async saveEdit(id) {
            if (!String(this.form.name || '').trim()) { alert('Service name is required.'); return; }
            this.saving = true;
            try {
                const res = await fetch(`${UPDATE_BASE}/${id}`, {
                    method:  'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body:    JSON.stringify({
                        ...this.form,
                        is_active: this.form.is_active == '1' || this.form.is_active === true || this.form.is_active == 1,
                    }),
                });
                const json = await res.json();
                if (res.ok && json.success) {
                    window.location.reload();
                }
            } catch { alert('Save failed.'); }
            finally { this.saving = false; }
        },

        async toggleActive(id, currentlyActive) {
            const body = {
                ...this.original,
                is_active: !currentlyActive,
            };
            try {
                const res = await fetch(`${UPDATE_BASE}/${id}`, {
                    method:  'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body:    JSON.stringify(body),
                });
                if (res.ok) window.location.reload();
            } catch { alert('Could not update the service.');
            }
        },
    };
}

function emptyServiceForm() {
    return { head: '', name: '', default_price: '', default_quantity: 1 };
}
</script>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>
@endpush
