@extends('layouts.doctor')
@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
@php
    use Illuminate\Support\Str;
    $fmt = fn($n) => '₹' . number_format((float) $n, 2);
@endphp

{{-- ── KPI cards (5 across) ─────────────────────────────────────────────── --}}
<style>
    /* Override layout's 4-col default for this page only */
    .doc-pay-kpi-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 16px;
        margin-bottom: 22px;
        align-items: stretch;
    }
    @media (max-width: 1100px) {
        .doc-pay-kpi-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 680px) {
        .doc-pay-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 420px) {
        .doc-pay-kpi-grid { grid-template-columns: 1fr; }
    }
    /* No icon badges on these cards — keep value on a single line.
       Shrink the value font slightly so even "₹7,200.00" fits at 6-col width. */
    .doc-pay-kpi-grid .stat-card {
        min-height: 116px;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
    }
    .doc-pay-kpi-grid .stat-value {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 1.35rem;
        line-height: 1.2;
    }
    .doc-pay-kpi-grid .stat-label {
        font-size: .68rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<div class="doc-pay-kpi-grid">

    {{-- 1. Collected on bills ─────────────────────────────────────────── --}}
    <div class="stat-card" style="border-top: 3px solid var(--leaf)">
        <div class="stat-label">Collected (bills)</div>
        <div class="stat-value" style="color:#065f46">{{ $fmt($totals['bill_collected']) }}</div>
        <div class="stat-sub">Money received via bills</div>
    </div>

    {{-- 2. Paid (appointment fees, all time) ─────────────────────────── --}}
    <div class="stat-card" style="border-top: 3px solid var(--sage)">
        <div class="stat-label">Paid (all time)</div>
        <div class="stat-value">{{ $fmt($totals['paid']) }}</div>
        <div class="stat-sub">Sum of marked-paid visit fees</div>
    </div>

    {{-- 3. Online (Razorpay+UPI) ───────────────────────────────────────── --}}
    <div class="stat-card" style="border-top: 3px solid #3b82f6">
        <div class="stat-label">Online (Razorpay+UPI)</div>
        <div class="stat-value" style="color:#1e40af">{{ $fmt($totals['online_total']) }}</div>
    </div>

    {{-- 4. Cash collected ──────────────────────────────────────────────── --}}
    <div class="stat-card" style="border-top: 3px solid var(--amber)">
        <div class="stat-label">Cash Collected</div>
        <div class="stat-value" style="color:#92400e">{{ $fmt($totals['cash_total']) }}</div>
    </div>

    {{-- 5. Outstanding on bills ───────────────────────────────────────── --}}
    <div class="stat-card" style="border-top: 3px solid var(--coral)">
        <div class="stat-label">Outstanding (bills)</div>
        <div class="stat-value" style="color:#b45309">{{ $fmt($totals['bill_outstanding']) }}</div>
        <div class="stat-sub">Balances on open &amp; partial bills</div>
    </div>

    {{-- 6. Waived ─────────────────────────────────────────────────────── --}}
    <div class="stat-card" style="border-top: 3px solid #9d8fc0">
        <div class="stat-label">Waived</div>
        <div class="stat-value" style="color:#7c6f94">{{ $fmt($totals['bill_waived']) }}</div>
        <div class="stat-sub">Bills marked waived</div>
    </div>

</div>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
    <div>
        <div style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--txt)">Payments</div>
        <div style="font-size:.8rem;color:var(--txt-lt);margin-top:2px">All visits, their bills and how they were paid.</div>
    </div>
    <a href="{{ route('doctor.payments.export', array_filter(($tab !== 'all' ? ['tab' => $tab] : []) + $filters, fn ($v) => $v !== '' && $v !== null)) }}"
       style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;background:#0f766e;color:#fff;border:1px solid #0f766e;font-size:.78rem;font-weight:600;font-family:'Outfit',sans-serif;text-decoration:none;transition:background .15s"
       onmouseover="this.style.background='#115e59'" onmouseout="this.style.background='#0f766e'"
       title="Download the current list (respects tab and filters) as Excel">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 19h16"/>
        </svg>
        Export Excel
    </a>
</div>

{{-- ── Filter / search bar ────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('doctor.payments.all') }}" class="doc-pay-searchbar">
    @if($tab !== 'all')
        <input type="hidden" name="tab" value="{{ $tab }}">
    @endif

    <div class="doc-pay-searchbar-field doc-pay-searchbar-field--grow dp-suggest-wrap">
        <label for="dp-q">Search</label>
        <input type="text" id="dp-q" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="Patient, doctor, ID, notes…"
               autocomplete="off"
               role="combobox"
               aria-autocomplete="list"
               aria-expanded="false"
               aria-controls="dp-q-suggestions"
               data-suggest-url="{{ route('doctor.payments.suggest') }}">
        <ul id="dp-q-suggestions" class="dp-suggest-list" role="listbox" hidden></ul>
    </div>

    <div class="doc-pay-searchbar-field">
        <label for="dp-status">Status</label>
        <div class="doc-pay-select-wrap">
            <select id="dp-status" name="status">
                <option value=""         {{ ($filters['status'] ?? '') === ''         ? 'selected' : '' }}>All</option>
                <option value="paid"     {{ ($filters['status'] ?? '') === 'paid'     ? 'selected' : '' }}>Paid</option>
                <option value="due"      {{ ($filters['status'] ?? '') === 'due'      ? 'selected' : '' }}>Due</option>
            </select>
        </div>
    </div>

    <div class="doc-pay-searchbar-field">
        <label for="dp-method">Method</label>
        <div class="doc-pay-select-wrap">
            <select id="dp-method" name="method">
                <option value=""      {{ ($filters['method'] ?? '') === ''      ? 'selected' : '' }}>All</option>
                <option value="cash"   {{ ($filters['method'] ?? '') === 'cash'   ? 'selected' : '' }}>Cash</option>
                <option value="online" {{ ($filters['method'] ?? '') === 'online' ? 'selected' : '' }}>Online</option>
            </select>
        </div>
    </div>

    <div class="doc-pay-searchbar-field">
        <label for="dp-from">From</label>
        <div class="dp-date-wrap">
            <input type="text" id="dp-from-display" readonly
                   value="{{ $filters['from'] ?? '' }}"
                   placeholder="dd-mm-yyyy"
                   class="dp-date-display"
                   tabindex="-1" aria-hidden="true">
            <input type="date" id="dp-from" name="from"
                   value="{{ $filters['from'] ? \Carbon\Carbon::createFromFormat('d-m-Y', $filters['from'])->format('Y-m-d') : '' }}"
                   class="dp-date-input"
                   data-display-target="dp-from-display">
            <button type="button" class="dp-date-btn" data-target="dp-from" title="Open calendar" aria-label="Open calendar">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="doc-pay-searchbar-field">
        <label for="dp-to">To</label>
        <div class="dp-date-wrap">
            <input type="text" id="dp-to-display" readonly
                   value="{{ $filters['to'] ?? '' }}"
                   placeholder="dd-mm-yyyy"
                   class="dp-date-display"
                   tabindex="-1" aria-hidden="true">
            <input type="date" id="dp-to" name="to"
                   value="{{ $filters['to'] ? \Carbon\Carbon::createFromFormat('d-m-Y', $filters['to'])->format('Y-m-d') : '' }}"
                   class="dp-date-input"
                   data-display-target="dp-to-display">
            <button type="button" class="dp-date-btn" data-target="dp-to" title="Open calendar" aria-label="Open calendar">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="doc-pay-searchbar-actions">
        <button type="submit" class="doc-pay-btn doc-pay-btn--primary">Filter</button>
        <a  href="{{ route('doctor.payments.all', $tab !== 'all' ? ['tab' => $tab] : []) }}"
            class="doc-pay-btn doc-pay-btn--ghost">Clear</a>
    </div>
</form>

@push('scripts')
<script>
(function () {
    // ── Date inputs: keep hidden type=date for the native calendar pop,
    //    and reflect the picked value (YYYY-MM-DD) in a visible text
    //    field as dd-mm-yyyy so it matches the form contract.
    document.querySelectorAll('.dp-date-input').forEach(function (input) {
        var display = document.getElementById(input.getAttribute('data-display-target'));
        function sync() {
            if (!display) return;
            if (!input.value) { display.value = ''; return; }
            var p = input.value.split('-');
            display.value = (p.length === 3) ? (p[2] + '-' + p[1] + '-' + p[0]) : input.value;
        }
        input.addEventListener('change', sync);
        sync();
    });

    // Calendar button → open the hidden date input
    document.querySelectorAll('.dp-date-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var t = document.getElementById(btn.getAttribute('data-target'));
            if (t && typeof t.showPicker === 'function') { t.showPicker(); }
            else if (t) { t.focus(); }
        });
    });

    // ── Patient name suggestions: custom dropdown with avatars.
    var qInput     = document.getElementById('dp-q');
    var suggestEl  = document.getElementById('dp-q-suggestions');
    var suggestUrl = qInput && qInput.getAttribute('data-suggest-url');
    var debounceId = null;
    var lastQuery  = '';
    var activeIdx  = -1;
    var items      = [];

    function initials(name) {
        var parts = (name || '').trim().split(/\s+/).filter(Boolean);
        if (parts.length === 0) return '?';
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function avatarColor(name) {
        // Deterministic pastel background based on first char code.
        var palette = ['#fde68a','#bbf7d0','#bfdbfe','#fbcfe8','#fed7aa','#c7d2fe','#fecaca','#a7f3d0'];
        var sum = 0;
        for (var i = 0; i < name.length; i++) { sum += name.charCodeAt(i); }
        return palette[sum % palette.length];
    }

    function closeList() {
        if (!suggestEl) return;
        suggestEl.innerHTML = '';
        suggestEl.hidden = true;
        qInput.setAttribute('aria-expanded', 'false');
        activeIdx = -1;
        items = [];
    }

    function renderList(suggestions) {
        if (!suggestions || suggestions.length === 0) { closeList(); return; }
        var html = '';
        suggestions.forEach(function (s, i) {
            var initialsStr = initials(s.name);
            var bg = avatarColor(s.name);
            var name  = (s.name  || '').replace(/</g, '&lt;');
            var phone = (s.phone || '').replace(/</g, '&lt;');
            html += ''
                + '<li class="dp-suggest-item" role="option" data-idx="' + i + '" data-name="' + name + '">'
                +   '<span class="dp-suggest-avatar" style="background:' + bg + '">' + initialsStr + '</span>'
                +   '<span class="dp-suggest-text">'
                +     '<span class="dp-suggest-name">' + name + '</span>'
                +     '<span class="dp-suggest-phone">' + phone + '</span>'
                +   '</span>'
                + '</li>';
        });
        suggestEl.innerHTML = html;
        suggestEl.hidden = false;
        qInput.setAttribute('aria-expanded', 'true');
        items = suggestions;
        activeIdx = -1;
    }

    function highlight(idx) {
        var nodes = suggestEl.querySelectorAll('.dp-suggest-item');
        nodes.forEach(function (n, i) {
            n.classList.toggle('is-active', i === idx);
        });
        if (idx >= 0 && nodes[idx]) {
            nodes[idx].scrollIntoView({ block: 'nearest' });
        }
    }

    function fetchSuggestions(q) {
        if (!suggestUrl || q === lastQuery) return;
        lastQuery = q;
        if (q.length < 1) { closeList(); return; }
        fetch(suggestUrl + '?q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.ok ? r.json() : { suggestions: [] }; })
        .then(function (data) {
            if (qInput.value.trim() !== q) return; // stale
            renderList(data.suggestions || []);
        })
        .catch(function () { closeList(); });
    }

    if (qInput) {
        qInput.addEventListener('input', function () {
            clearTimeout(debounceId);
            var q = qInput.value.trim();
            debounceId = setTimeout(function () { fetchSuggestions(q); }, 150);
        });

        qInput.addEventListener('keydown', function (e) {
            if (suggestEl.hidden) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = Math.min(activeIdx + 1, items.length - 1);
                highlight(activeIdx);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                highlight(activeIdx);
            } else if (e.key === 'Enter') {
                if (activeIdx >= 0 && items[activeIdx]) {
                    e.preventDefault();
                    qInput.value = items[activeIdx].name;
                    closeList();
                }
            } else if (e.key === 'Escape') {
                closeList();
            }
        });

        qInput.addEventListener('focus', function () {
            if (qInput.value.trim().length >= 1) {
                lastQuery = ''; // force refetch
                fetchSuggestions(qInput.value.trim());
            }
        });

        qInput.addEventListener('blur', function () {
            // Delay so a click on an item registers before we close.
            setTimeout(closeList, 150);
        });

        suggestEl.addEventListener('mousedown', function (e) {
            var li = e.target.closest('.dp-suggest-item');
            if (!li) return;
            e.preventDefault();
            var idx = parseInt(li.getAttribute('data-idx'), 10);
            if (items[idx]) {
                qInput.value = items[idx].name;
            }
            closeList();
        });
    }
})();
</script>
@endpush

<style>
/* ── Filter / search bar ─────────────────────────────────────────── */
.doc-pay-searchbar {
    background: var(--cream);
    border: 1px solid var(--warm-bd);
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 14px 16px;
    align-items: flex-end;
    margin-bottom: 16px;
}
.doc-pay-searchbar-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 130px;
    flex: 0 0 auto;
}
.doc-pay-searchbar-field--grow {
    flex: 1 1 220px;
    min-width: 180px;
}
.doc-pay-searchbar-field label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--txt-lt);
}
.doc-pay-searchbar-field input[type="text"],
.doc-pay-searchbar-field select {
    font-family: 'Outfit', sans-serif;
    font-size: .82rem;
    color: var(--txt);
    background: #fff;
    border: 1px solid var(--warm-bd);
    border-radius: 9px;
    padding: 8px 11px;
    height: 38px;
    box-sizing: border-box;
    width: 100%;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.doc-pay-searchbar-field input[type="text"]:focus,
.doc-pay-searchbar-field select:focus {
    border-color: var(--sage, #7b9e89);
    box-shadow: 0 0 0 3px rgba(123, 158, 137, .15);
}
.doc-pay-searchbar-field input::placeholder { color: var(--txt-lt); }

/* ── Date wrapper: visible display (dd-mm-yyyy) + invisible native date
      input + calendar button. The native picker pops on button click
      via JS .showPicker() and the value is reflected back in dd-mm-yyyy. */
.dp-date-wrap {
    position: relative;
    display: flex;
    align-items: stretch;
    width: 100%;
}
.dp-date-display {
    width: 100%;
    padding-right: 38px !important;
    background: #fff;
    cursor: pointer;
    color: var(--txt);
}
.dp-date-input {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    opacity: 0;
    pointer-events: none;
    border: 0;
    padding: 0;
    margin: 0;
    z-index: 0;
}
.dp-date-btn {
    position: absolute;
    right: 4px; top: 50%;
    transform: translateY(-50%);
    width: 30px; height: 30px;
    display: flex; align-items: center; justify-content: center;
    background: transparent;
    color: var(--txt-md);
    border: none;
    border-radius: 6px;
    cursor: pointer;
    z-index: 2;
    transition: background .15s, color .15s;
}
.dp-date-btn:hover { background: var(--parch); color: var(--leaf); }
.dp-date-btn:focus { outline: 2px solid var(--sage, #7b9e89); outline-offset: 1px; }

/* ── Suggestion dropdown ─────────────────────────────────────────── */
.dp-suggest-wrap { position: relative; }
.dp-suggest-list {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 60;
    margin: 0;
    padding: 6px;
    list-style: none;
    background: #fff;
    border: 1px solid var(--warm-bd);
    border-radius: 12px;
    box-shadow:
        0 1px 2px rgba(0, 0, 0, 0.04),
        0 8px 24px rgba(28, 43, 42, 0.10);
    max-height: 280px;
    overflow-y: auto;
    font-size: .85rem;
    color: var(--txt);
}
.dp-suggest-list[hidden] { display: none; }
.dp-suggest-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: background .12s;
    user-select: none;
}
.dp-suggest-item.is-active,
.dp-suggest-item:hover {
    background: var(--parch);
}
.dp-suggest-avatar {
    flex: 0 0 auto;
    width: 32px; height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .72rem;
    font-weight: 700;
    color: #1c2b2a;
    letter-spacing: .02em;
}
.dp-suggest-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: 1.25;
}
.dp-suggest-name {
    font-weight: 500;
    color: var(--txt);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dp-suggest-phone {
    font-size: .72rem;
    color: var(--txt-lt);
    font-family: 'SFMono-Regular', ui-monospace, monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.doc-pay-select-wrap { position: relative; }
.doc-pay-select-wrap::after {
    content: "";
    position: absolute;
    right: 12px; top: 50%;
    width: 8px; height: 8px;
    border-right: 2px solid var(--txt-md);
    border-bottom: 2px solid var(--txt-md);
    transform: translateY(-70%) rotate(45deg);
    pointer-events: none;
}
.doc-pay-select-wrap select {
    appearance: none;
    -webkit-appearance: none;
    padding-right: 30px !important;
}
.doc-pay-searchbar-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-left: auto;
}
.doc-pay-btn {
    font-family: 'Outfit', sans-serif;
    font-size: .78rem;
    font-weight: 600;
    padding: 9px 18px;
    border-radius: 9px;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    height: 38px;
    box-sizing: border-box;
    line-height: 18px;
    transition: background .15s, border-color .15s, color .15s;
}
.doc-pay-btn--primary {
    background: #6366f1;
    color: #fff;
    border-color: #6366f1;
}
.doc-pay-btn--primary:hover { background: #4f46e5; border-color: #4f46e5; }
.doc-pay-btn--ghost {
    background: #fff;
    color: var(--txt-md);
    border-color: var(--warm-bd);
}
.doc-pay-btn--ghost:hover { color: var(--txt); border-color: #c8b9a0; }
@media (max-width: 720px) {
    .doc-pay-searchbar-actions { margin-left: 0; width: 100%; }
    .doc-pay-btn { flex: 1; text-align: center; }
}
</style>

<style>
.doc-pay-wrap { background:var(--cream); border:1px solid var(--warm-bd); border-radius:14px; overflow:hidden; }
.doc-pay-grid-head,
.doc-pay-grid-row { display:grid; grid-template-columns:1.2fr 1.2fr 1fr 1.3fr 1fr 1.5fr 1fr; align-items:center; gap:8px; }
.doc-pay-grid-head { background:var(--parch); padding:10px 18px; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--txt-lt); }
.doc-pay-grid-row  { padding:12px 18px; border-top:1px solid var(--warm-bd); font-size:.85rem; }
@media (max-width: 900px) {
    .doc-pay-grid-head,
    .doc-pay-grid-row { grid-template-columns: 1.2fr 1.2fr 1fr 1.3fr 1fr 1fr; }
    .doc-pay-col-notes { display:none; }
}
@media (max-width: 640px) {
    .doc-pay-grid-head { display:none; }
    .doc-pay-grid-row {
        grid-template-columns: 1fr 1fr;
        grid-template-areas:
            "patient patient"
            "date    fee"
            "bill    status"
            "notes   notes"
            "action  action";
        gap: 8px 12px;
        padding: 14px 14px;
    }
    .doc-pay-area-patient { grid-area: patient; }
    .doc-pay-area-date    { grid-area: date; }
    .doc-pay-area-fee     { grid-area: fee; text-align:right; }
    .doc-pay-area-bill    { grid-area: bill; }
    .doc-pay-area-status  { grid-area: status; justify-items:end; }
    .doc-pay-area-notes   { grid-area: notes; }
    .doc-pay-area-action  { grid-area: action; }
    .doc-pay-area-fee::before { content:"Fee "; font-size:.65rem; color:var(--txt-lt); font-weight:600; text-transform:uppercase; letter-spacing:.05em; display:block; }
    .doc-pay-area-date::before { content:"Date "; font-size:.65rem; color:var(--txt-lt); font-weight:600; text-transform:uppercase; letter-spacing:.05em; display:block; }
    .doc-pay-area-bill::before { content:"Bill "; font-size:.65rem; color:var(--txt-lt); font-weight:600; text-transform:uppercase; letter-spacing:.05em; display:block; }
    .doc-pay-area-notes { padding-top:6px; border-top:1px dashed var(--warm-bd); }
    .doc-pay-area-notes::before { content:"Notes"; font-size:.65rem; color:var(--txt-lt); font-weight:600; text-transform:uppercase; letter-spacing:.05em; display:block; margin-bottom:2px; }
    .doc-pay-area-action button, .doc-pay-area-action a { width:100%; }
}
@media (max-width: 380px) {
    .doc-pay-grid-row { padding:12px 12px; }
}
@media (max-width: 480px) {
    .doc-pay-filter a { padding:6px 10px !important; font-size:.72rem !important; }
}
</style>

<div class="doc-pay-wrap">
    <div class="doc-pay-grid-head">
        <div>Patient</div>
        <div>Date</div>
        <div>Fee</div>
        <div>Bill</div>
        <div>Status</div>
        <div class="doc-pay-col-notes">Notes</div>
        <div>Action</div>
    </div>

    @forelse($payments as $appt)
        @php
            $pName = $appt->patient?->profile?->full_name ?? '—';
            $methodLabel = $appt->payment_preference === 'cash' ? 'Cash'
                         : ($appt->payment_preference === 'online' ? 'Online' : '—');
            $bill = $appt->bill;
            $billBadge = null;
            if ($bill) {
                $billBadge = match ($bill->status) {
                    'paid'    => ['background:#dcfce7;color:#166534', 'Paid'],
                    'partial' => ['background:#fef9c3;color:#854d0e', 'Partial · ₹' . number_format((float) $bill->amount_paid, 0) . ' of ₹' . number_format((float) $bill->total, 0)],
                    'waived'  => ['background:#ede9fe;color:#5b4a8a', 'Waived'],
                    default   => ['background:#e8e4dc;color:#57534e', ucfirst($bill->status ?? 'Open')],
                };
            }
        @endphp
        <div class="doc-pay-grid-row">
            <div class="doc-pay-area-patient">
                <a href="{{ route('doctor.appointments.show', $appt) }}" style="color:var(--txt);text-decoration:none;font-weight:500">{{ $pName }}</a>
                <div style="font-size:.7rem;color:var(--txt-lt);font-family:monospace">#{{ $appt->appointment_number ?? $appt->id }}</div>
            </div>
            <div class="doc-pay-area-date" style="color:var(--txt-md)">{{ $appt->slot_datetime?->format('d M Y, h:i A') }}</div>
            <div class="doc-pay-area-fee" style="color:var(--txt);font-weight:500">{{ $fmt($bill ? $bill->total : $appt->fee) }}</div>
            <div class="doc-pay-area-bill">
                @if($bill)
                    @php $headsSummary = $bill->items ? $bill->items->map(fn ($i) => $i->head_label . ': ' . $i->label)->implode(' · ') : ''; @endphp
                    <div style="display:flex;flex-direction:column;gap:3px;align-items:flex-start">
                        <span style="display:inline-flex;align-items:center;background:{{ $billBadge[0] }};font-size:.68rem;font-weight:700;padding:3px 9px;border-radius:20px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $billBadge[1] }}</span>
                        <span style="font-size:.68rem;color:var(--txt-lt);font-family:monospace" title="{{ $bill->bill_number }}">
                            <a href="{{ route('doctor.bills.receipt', $bill) }}" target="_blank" rel="noopener" style="color:var(--txt-lt);text-decoration:none">{{ Str::limit($bill->bill_number, 18) }}</a>
                        </span>
                        @if($headsSummary)
                            <span style="font-size:.66rem;color:var(--txt-md);line-height:1.3" title="{{ $headsSummary }}">{{ Str::limit($headsSummary, 64) }}</span>
                        @endif
                    </div>
                @else
                    <span style="color:var(--txt-lt);font-size:.78rem">No bill</span>
                @endif
            </div>
            <div class="doc-pay-area-status">
                @if($bill && $bill->balance > 0 && in_array($bill->status, ['draft','unpaid','partial']))
                    <div style="display:flex;flex-direction:column;gap:3px;align-items:flex-start">
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#fee2e2;color:#b91c1c;font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:20px">Balance {{ $fmt($bill->balance) }}</span>
                    </div>
                @elseif($appt->payment_status === 'paid')
                    <span style="display:inline-flex;align-items:center;gap:4px;background:#dcfce7;color:#166534;font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:20px">Paid · {{ $methodLabel }}</span>
                @elseif($bill && $bill->status === 'waived')
                    <span style="display:inline-flex;align-items:center;gap:4px;background:#ede9fe;color:#5b4a8a;font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:20px">Nothing due</span>
                @else
                    <div style="display:flex;flex-direction:column;gap:3px;align-items:flex-start">
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#fef9c3;color:#854d0e;font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:20px">Due
                            @if($appt->payment_preference === 'cash') · cash expected @endif
                        </span>
                        @if($appt->payment_requested_at)
                            <span style="font-size:.65rem;color:#b45309;font-weight:500" title="Patient notified on {{ $appt->payment_requested_at->format('d M Y, h:i A') }}">
                                Requested {{ $appt->payment_requested_at->diffForHumans(null, true, true) }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>
            <div class="doc-pay-area-notes doc-pay-col-notes" style="font-size:.78rem;color:var(--txt-md)">
                @if($appt->completion_note)
                    <span title="{{ $appt->completion_note }}">{{ Str::limit($appt->completion_note, 80) }}</span>
                @elseif($appt->reason)
                    <span style="color:var(--txt-md)" title="{{ $appt->reason }}">{{ Str::limit($appt->reason, 80) }}</span>
                @else
                    <span style="color:var(--txt-lt)">—</span>
                @endif
            </div>
            <div class="doc-pay-area-action">
                @if($appt->payment_status === 'pending' && $appt->status !== 'cancelled')
                    <form method="POST" action="{{ route('doctor.appointments.request-payment', $appt) }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Sending…'">
                        @csrf
                        <button type="submit" style="padding:6px 12px;background:#f59e0b;color:#fff;border:0;border-radius:8px;font-size:.72rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;white-space:nowrap;width:100%">
                            {{ $appt->payment_requested_at ? 'Resend Request' : 'Request Payment' }}
                        </button>
                    </form>
                @elseif($bill && !$bill->isWaived())
                    <a href="{{ route('doctor.bills.receipt', $bill) }}" target="_blank" rel="noopener"
                       style="display:inline-block;text-align:center;padding:6px 12px;background:#fff;color:var(--txt-md);border:1px solid var(--warm-bd);border-radius:8px;font-size:.72rem;font-weight:600;font-family:'Outfit',sans-serif;white-space:nowrap;width:100%;text-decoration:none">
                        View Receipt
                    </a>
                @else
                    <span style="color:var(--txt-lt);font-size:.78rem">—</span>
                @endif
            </div>
        </div>
    @empty
        <div style="padding:30px 18px;text-align:center;color:var(--txt-lt);font-size:.85rem;grid-column:1/-1">No appointments found for this filter.</div>
    @endforelse

    @if(method_exists($payments, 'links'))
    <div style="padding:10px 16px;border-top:1px solid var(--warm-bd)">{{ $payments->links() }}</div>
    @endif
</div>
@endsection
