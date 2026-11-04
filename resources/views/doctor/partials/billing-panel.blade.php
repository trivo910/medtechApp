@php
    /** @var \App\Models\Appointment $appointment */
    $bill        = $bill ?? $appointment->bill?->load('items', 'payments');
    $prescription = $prescription ?? null;

    if ($bill) {
        $bill->loadMissing(['items', 'payments']);
    }

    $heads = collect(config('medtech.billing_heads', []))
        ->map(fn ($h, $k) => ['value' => $k, 'label' => $h['label']])
        ->values();

    /* Doctor's saved services: quick-add lines with default price/quantity. */
    $services = \App\Models\DoctorService::where('doctor_user_id', auth()->id())
        ->active()->ordered()->get()
        ->map(fn ($s) => ['id' => $s->id, 'head' => $s->head, 'label' => $s->name, 'price' => (float) $s->default_price, 'qty' => $s->default_quantity])
        ->values();

    if ($bill && $bill->items->isNotEmpty()) {
        $items = $bill->items->map(fn ($i) => [
            'head'       => $i->head,
            'label'      => $i->label,
            'quantity'   => (int) $i->quantity,
            'unit_price' => (float) $i->unit_price,
        ])->values();
    } else {
        $items = collect([[
            'head'       => 'opd_fee',
            'label'      => 'OPD / Consultation',
            'quantity'   => 1,
            'unit_price' => (float) $appointment->fee,
        ]]);
    }

    $alreadyPaid = (float) ($bill->amount_paid ?? 0);
    $dueNow      = $bill ? max(0, (float) $bill->balance) : (float) $appointment->fee;

    $status      = $bill->status ?? 'draft';
    $settled     = in_array($status, ['paid', 'waived'], true);
    $isCompleted = in_array($appointment->status, ['completed', 'cancelled'], true);

    $statusStyle = [
        'draft'   => ['bg' => '#f1f5f9', 'fg' => '#475569'],
        'unpaid'  => ['bg' => '#fff7ed', 'fg' => '#9a3412'],
        'partial' => ['bg' => '#fef9c3', 'fg' => '#854d0e'],
        'paid'    => ['bg' => '#dcfce7', 'fg' => '#166534'],
        'waived'  => ['bg' => '#ede9fe', 'fg' => '#5b21b6'],
        'refunded'=> ['bg' => '#fee2e2', 'fg' => '#991b1b'],
    ][$status] ?? ['bg' => '#f1f5f9', 'fg' => '#475569'];

    $billingConfig = [
        'heads'          => $heads,
        'services'       => $services,
        'items'          => $items,
        'alreadyPaid'    => $alreadyPaid,
        'discountType'   => 'amount',
        'discountValue'  => (float) ($bill->discount ?? 0),
        'method'         => $appointment->payment_preference === 'cash' ? 'cash' : 'upi_qr',
        'received'       => round($dueNow, 2),
        'notes'          => $bill->notes ?? '',
        'collectUrl'     => route('doctor.appointments.billing.collect', $appointment),
        'waiveUrl'       => route('doctor.appointments.billing.waive', $appointment),
        'prescriptionId' => $prescription?->id,
    ];
@endphp

<div id="billing" style="background:var(--cream);border:1px solid var(--warm-bd);border-radius:14px;margin-bottom:16px;overflow:hidden">
    <div style="padding:13px 20px;border-bottom:1px solid var(--warm-bd);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:9px">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="var(--leaf)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
            <span style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;font-weight:500;color:var(--txt)">Complete Visit &amp; Payment</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            @if($bill)
            <span style="font-family:monospace;font-size:.72rem;color:var(--txt-lt)">{{ $bill->bill_number }}</span>
            @endif
            <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:3px 9px;border-radius:20px;background:{{ $statusStyle['bg'] }};color:{{ $statusStyle['fg'] }}">{{ ucfirst($status) }}</span>
        </div>
    </div>

    @if($settled)
        {{-- ── Read-only receipt ─────────────────────────────────────────── --}}
        <div style="padding:16px 20px">
            @if($status === 'waived')
                <div style="background:#ede9fe;border:1px solid #ddd6fe;color:#5b21b6;border-radius:9px;padding:9px 12px;font-size:.8rem;margin-bottom:12px">
                    <strong>Completed without payment.</strong>
                    {{ $appointment->completion_note }}
                </div>
            @else
                <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:9px;padding:9px 12px;font-size:.8rem;margin-bottom:12px">
                    <strong>Payment received in full.</strong> ₹{{ number_format((float) $bill->amount_paid, 2) }} collected.
                </div>
            @endif

            <table style="width:100%;border-collapse:collapse;font-size:.82rem">
                <tbody>
                @foreach($bill->items as $item)
                    <tr>
                        <td style="padding:6px 0;border-bottom:1px solid var(--parch)">
                            <div style="font-weight:500;color:var(--txt)">{{ $item->label }}</div>
                            <div style="font-size:.68rem;color:var(--txt-lt);text-transform:uppercase;letter-spacing:.04em">{{ $item->head_label }}</div>
                        </td>
                        <td style="padding:6px 0;border-bottom:1px solid var(--parch);text-align:center;color:var(--txt-lt);width:70px">{{ $item->quantity }} × ₹{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td style="padding:6px 0;border-bottom:1px solid var(--parch);text-align:right;font-weight:600;color:var(--txt);width:100px">₹{{ number_format((float) $item->amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div style="margin-top:12px;font-size:.82rem">
                <div style="display:flex;justify-content:space-between;padding:3px 0;color:var(--txt-md)"><span>Subtotal</span><span>₹{{ number_format((float) $bill->subtotal, 2) }}</span></div>
                @if((float) $bill->discount > 0)
                <div style="display:flex;justify-content:space-between;padding:3px 0;color:var(--txt-md)"><span>Discount</span><span>−₹{{ number_format((float) $bill->discount, 2) }}</span></div>
                @endif
                <div style="display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid var(--warm-bd);margin-top:4px;font-weight:700;color:var(--txt)"><span>Total</span><span>₹{{ number_format((float) $bill->total, 2) }}</span></div>
                <div style="display:flex;justify-content:space-between;padding:3px 0;color:#065f46"><span>Paid</span><span>₹{{ number_format((float) $bill->amount_paid, 2) }}</span></div>
                @if((float) $bill->balance > 0)
                <div style="display:flex;justify-content:space-between;padding:3px 0;color:#9a3412"><span>Balance</span><span>₹{{ number_format((float) $bill->balance, 2) }}</span></div>
                @endif
            </div>

            @if($bill->payments->where('status', 'paid')->isNotEmpty())
            <div style="margin-top:12px;padding-top:10px;border-top:1px dashed var(--warm-bd)">
                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-lt);margin-bottom:6px">Payments</div>
                @foreach($bill->payments->where('status', 'paid') as $pay)
                <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--txt-md);padding:2px 0">
                    <span>{{ ucfirst(str_replace('_', ' ', $pay->payment_method)) }} · {{ $pay->paid_at?->format('d M Y, h:i A') }}</span>
                    <span style="font-weight:600;color:var(--txt)">₹{{ number_format((float) $pay->amount, 2) }}</span>
                </div>
                @endforeach
            </div>
            @endif

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                <a href="{{ route('doctor.bills.receipt', $bill) }}" target="_blank"
                   style="display:inline-flex;align-items:center;gap:6px;padding:9px 15px;border:1.5px solid var(--warm-bd);border-radius:9px;color:var(--txt-md);text-decoration:none;font-size:.82rem;font-weight:600">
                    Print receipt
                </a>
                @if(!$isCompleted)
                <form method="POST" action="{{ route('doctor.appointments.complete', $appointment) }}">
                    @csrf
                    <button type="submit" style="padding:9px 15px;background:#16a34a;color:#fff;border:0;border-radius:9px;font-size:.82rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif">Mark visit complete</button>
                </form>
                @endif
            </div>
        </div>
    @else
        {{-- ── Editable bill ─────────────────────────────────────────────── --}}
        <div x-data="billingPanel(@js($billingConfig))" style="padding:16px 20px">
            <template x-for="(item, idx) in items" :key="idx">
                <div style="display:grid;grid-template-columns:150px 1fr 66px 104px 34px;gap:8px;align-items:end;margin-bottom:8px" class="bill-row">
                    <div>
                        <label style="display:block;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--txt-lt);margin-bottom:3px" x-text="headLabel(item.head)"></label>
                        <input type="text" x-model="item.label" maxlength="200" placeholder="Description"
                               style="width:100%;padding:7px 9px;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.8rem;font-family:'Outfit',sans-serif;background:var(--cream);color:var(--txt);outline:none">
                    </div>
                    <div>
                        <label style="display:block;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--txt-lt);margin-bottom:3px">Amount</label>
                        <input type="number" min="0" step="0.01" x-model.number="item.unit_price"
                               style="width:100%;padding:7px 9px;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.8rem;font-family:'Outfit',sans-serif;background:var(--cream);color:var(--txt);outline:none">
                    </div>
                    <div>
                        <label style="display:block;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--txt-lt);margin-bottom:3px">Qty</label>
                        <input type="number" min="1" max="999" x-model.number="item.quantity"
                               style="width:100%;padding:7px 9px;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.8rem;font-family:'Outfit',sans-serif;background:var(--cream);color:var(--txt);outline:none">
                    </div>
                    <div>
                        <label style="display:block;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--txt-lt);margin-bottom:3px">Line total</label>
                        <div style="padding:7px 9px;background:var(--parch);border:1px solid var(--warm-bd);border-radius:8px;font-size:.8rem;font-weight:600;color:var(--txt);text-align:right" x-text="'₹' + money(lineTotal(item))"></div>
                    </div>
                    <button type="button" @click="removeItem(idx)" title="Remove"
                            style="height:33px;border:1.5px solid var(--warm-bd);background:transparent;border-radius:8px;color:#ef4444;cursor:pointer;font-size:1rem;line-height:1">×</button>
                </div>
            </template>

            <div style="display:flex;align-items:center;gap:10px;margin:10px 0 14px;flex-wrap:wrap">
                <select @change="addHead($event.target.value); $event.target.value=''"
                        style="padding:8px 10px;border:1.5px dashed var(--warm-bd);border-radius:8px;background:var(--cream);color:var(--txt-md);font-size:.8rem;font-family:'Outfit',sans-serif;cursor:pointer">
                    <option value="">+ Add payment head</option>
                    <template x-for="h in heads" :key="h.value">
                        <option :value="h.value" x-text="h.label"></option>
                    </template>
                </select>
                @if($services->isNotEmpty())
                <select @change="addService($event.target.value); $event.target.value=''"
                        style="padding:8px 10px;border:1.5px dashed var(--leaf);border-radius:8px;background:var(--cream);color:var(--leaf);font-size:.8rem;font-family:'Outfit',sans-serif;cursor:pointer">
                    <option value="">+ Add saved service</option>
                    <template x-for="s in services" :key="s.id">
                        <option :value="s.id" x-text="s.label + ' · ₹' + money(s.price)"></option>
                    </template>
                </select>
                @endif
                <span style="font-size:.72rem;color:var(--txt-lt)">OPD fee is pre-filled from the visit. <a href="{{ route('doctor.services.index') }}" target="_blank" style="color:var(--leaf);text-decoration:none">Manage services</a></span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 240px;gap:16px;align-items:start" class="bill-summary">
                <div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div>
                            <label style="display:block;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--txt-lt);margin-bottom:3px">Discount</label>
                            <div style="display:flex;gap:6px">
                                <select x-model="discountType" style="padding:7px 8px;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.8rem;font-family:'Outfit',sans-serif;background:var(--cream);color:var(--txt)">
                                    <option value="amount">₹</option>
                                    <option value="percent">%</option>
                                </select>
                                <input type="number" min="0" step="0.01" x-model.number="discountValue"
                                       style="flex:1;min-width:0;padding:7px 9px;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.8rem;font-family:'Outfit',sans-serif;background:var(--cream);color:var(--txt);outline:none">
                            </div>
                        </div>
                        <div>
                            <label style="display:block;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--txt-lt);margin-bottom:3px">Payment method</label>
                            <div style="display:flex;gap:4px;background:var(--parch);padding:3px;border-radius:8px">
                                <template x-for="m in [{v:'cash',l:'Cash'},{v:'upi_qr',l:'UPI'},{v:'other',l:'Card'},{v:'razorpay',l:'Online'}]" :key="m.v">
                                    <button type="button" @click="method = m.v"
                                            :style="method === m.v ? 'flex:1;padding:6px 4px;border:0;border-radius:6px;background:#fff;color:var(--txt);font-size:.72rem;font-weight:700;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,.06)' : 'flex:1;padding:6px 4px;border:0;border-radius:6px;background:transparent;color:var(--txt-lt);font-size:.72rem;font-weight:600;cursor:pointer'"
                                            x-text="m.l"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
                        <div>
                            <label style="display:block;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--txt-lt);margin-bottom:3px">Reference (optional)</label>
                            <input type="text" maxlength="200" x-model="reference" placeholder="Receipt / UPI ref"
                                   style="width:100%;padding:7px 9px;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.8rem;font-family:'Outfit',sans-serif;background:var(--cream);color:var(--txt);outline:none">
                        </div>
                        <div>
                            <label style="display:block;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--txt-lt);margin-bottom:3px">Note (optional)</label>
                            <input type="text" maxlength="1000" x-model="note" placeholder="e.g. follow-up visit"
                                   style="width:100%;padding:7px 9px;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.8rem;font-family:'Outfit',sans-serif;background:var(--cream);color:var(--txt);outline:none">
                        </div>
                    </div>
                </div>

                <div style="background:var(--parch);border:1px solid var(--warm-bd);border-radius:10px;padding:12px 14px">
                    <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--txt-md);padding:2px 0"><span>Subtotal</span><span x-text="'₹' + money(subtotal())"></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--txt-md);padding:2px 0"><span>Discount</span><span x-text="'−₹' + money(discountAmount())"></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:.95rem;font-weight:700;color:var(--txt);padding:7px 0;border-top:1px solid var(--warm-bd);margin-top:4px"><span>Total</span><span x-text="'₹' + money(total())"></span></div>
                    <template x-if="alreadyPaid > 0">
                        <div style="display:flex;justify-content:space-between;font-size:.78rem;color:#065f46;padding:2px 0"><span>Already paid</span><span x-text="'₹' + money(alreadyPaid)"></span></div>
                    </template>
                    <div style="display:flex;justify-content:space-between;font-size:.78rem;color:#9a3412;padding:2px 0"><span>Balance</span><span x-text="'₹' + money(balance())"></span></div>

                    <label style="display:block;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--txt-lt);margin:8px 0 3px">Amount received now</label>
                    <input type="number" min="0" step="0.01" x-model.number="received"
                           style="width:100%;padding:8px 9px;border:1.5px solid var(--warm-bd);border-radius:8px;font-size:.9rem;font-weight:600;font-family:'Outfit',sans-serif;background:var(--cream);color:var(--txt);outline:none">
                    <button type="button" @click="received = balance()" style="margin-top:4px;background:none;border:0;color:var(--leaf);font-size:.72rem;font-weight:600;cursor:pointer;padding:0">Pay full balance</button>
                </div>
            </div>

            <div x-show="error" x-text="error" style="display:none;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:8px 12px;font-size:.78rem;margin-top:12px"></div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;align-items:center">
                <button type="button" @click="collect()" :disabled="saving"
                        style="padding:10px 18px;background:var(--leaf);color:#fff;border:0;border-radius:9px;font-size:.85rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif"
                        x-text="saving ? 'Saving…' : 'Collect & complete'"></button>
                <button type="button" @click="waive()" :disabled="saving"
                        style="padding:10px 16px;background:transparent;color:var(--txt-md);border:1.5px solid var(--warm-bd);border-radius:9px;font-size:.82rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif">
                    Complete without payment
                </button>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
function billingPanel(cfg) {
    return {
        heads: cfg.heads,
        services: cfg.services || [],
        items: cfg.items.length ? cfg.items : [{ head: 'opd_fee', label: 'OPD / Consultation', quantity: 1, unit_price: 0 }],
        discountType: cfg.discountType || 'amount',
        discountValue: Number(cfg.discountValue || 0),
        method: cfg.method || 'cash',
        reference: '',
        note: cfg.notes || '',
        received: Number(cfg.received || 0),
        alreadyPaid: Number(cfg.alreadyPaid || 0),
        saving: false,
        error: '',

        money(v) {
            return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        lineTotal(item) {
            return Math.round((Number(item.quantity) || 0) * (Number(item.unit_price) || 0) * 100) / 100;
        },
        headLabel(head) {
            const h = this.heads.find(x => x.value === head);
            return h ? h.label : head;
        },
        subtotal() {
            return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
        },
        discountAmount() {
            const sub = this.subtotal();
            const val = Math.max(0, Number(this.discountValue) || 0);
            const d = this.discountType === 'percent' ? sub * Math.min(100, val) / 100 : val;
            return Math.round(Math.min(sub, d) * 100) / 100;
        },
        total() {
            return Math.max(0, Math.round((this.subtotal() - this.discountAmount()) * 100) / 100);
        },
        balance() {
            return Math.max(0, Math.round((this.total() - this.alreadyPaid) * 100) / 100);
        },
        addHead(head) {
            if (!head) return;
            this.items.push({ head: head, label: this.headLabel(head), quantity: 1, unit_price: 0 });
        },
        addService(id) {
            const s = this.services.find(x => String(x.id) === String(id));
            if (!s) return;
            this.items.push({ head: s.head, label: s.label, quantity: s.qty || 1, unit_price: s.price || 0 });
        },
        removeItem(idx) {
            this.items.splice(idx, 1);
        },
        payload() {
            return {
                items: this.items.map(i => ({
                    head: i.head,
                    label: i.label,
                    quantity: Number(i.quantity) || 1,
                    unit_price: Number(i.unit_price) || 0,
                })),
                discount_type: this.discountType,
                discount_value: Number(this.discountValue) || 0,
                payment_method: this.method,
                amount_received: Number(this.received) || 0,
                reference: this.reference,
                note: this.note,
                prescription_id: cfg.prescriptionId || null,
            };
        },
        validate() {
            if (!this.items.length) return 'Add at least one payment head.';
            if (this.items.some(i => !String(i.label || '').trim())) return 'Every line needs a description.';
            if (Number(this.received) > this.balance() + 0.001) return 'Amount received is more than the balance.';
            return '';
        },
        async post(url, body) {
            this.error = '';
            this.saving = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content
                    || document.querySelector('input[name="_token"]')?.value;
                const r = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(body),
                });
                const data = await r.json().catch(() => ({}));
                if (r.ok && data.success) {
                    window.location.reload();
                    return;
                }
                if (r.status === 422 && data.errors) {
                    this.error = Object.values(data.errors)[0][0] || data.message || 'Validation failed.';
                } else {
                    this.error = data.message || data.error || 'Could not save the payment.';
                }
            } catch (e) {
                this.error = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },
        collect() {
            const problem = this.validate();
            if (problem) { this.error = problem; return; }
            this.post(cfg.collectUrl, this.payload());
        },
        waive() {
            const reason = window.prompt('Reason for completing without payment (required):', this.note || '');
            if (reason === null) return;
            if (!reason.trim()) { this.error = 'A reason is required.'; return; }
            this.note = reason.trim();
            this.post(cfg.waiveUrl, { note: this.note, items: this.payload().items });
        },
    };
}
</script>
@endpush
