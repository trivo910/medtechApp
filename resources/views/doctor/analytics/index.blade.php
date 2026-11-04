@extends('layouts.doctor')
@section('title', 'Analytics')
@section('page-title', 'Practice Analytics')

@section('content')
<div class="fade-in">

{{-- ── KPI row ──────────────────────────────────────────────────────────────── --}}
<div class="an-kpi" style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px">
@php $kpiCards = [
    ['label'=>'Total Patients',       'val'=>$kpis['total_patients'],       'sub'=>$kpis['patients_this_month'].' this month',    'color'=>'#3d7a6e'],
    ['label'=>'Total Appointments',   'val'=>$kpis['total_appointments'],   'sub'=>$kpis['apts_this_month'].' this month',        'color'=>'#4a3760'],
    ['label'=>'Prescriptions Written','val'=>$kpis['total_prescriptions'],  'sub'=>$kpis['rx_this_month'].' this month',          'color'=>'#c98a3a'],
    ['label'=>'Cancellation Rate',    'val'=>$kpis['no_show_rate'],         'sub'=>$kpis['cancelled_apts'].' cancelled total',    'color'=>'#c0737a'],
];
@endphp
@foreach($kpiCards as $k)
<div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:14px;padding:18px 20px">
    <div style="font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:500;color:{{ $k['color'] }};line-height:1;margin-bottom:6px">
        {{ $k['val'] }}
    </div>
    <div style="font-size:.82rem;font-weight:600;color:var(--txt)">{{ $k['label'] }}</div>
    <div style="font-size:.72rem;color:var(--txt-lt);margin-top:2px">{{ $k['sub'] }}</div>
    <div style="height:3px;border-radius:2px;background:{{ $k['color'] }};margin-top:12px;opacity:.6"></div>
</div>
@endforeach
</div>

{{-- ── Revenue + Completion row ────────────────────────────────────────────── --}}
<div class="an-3col" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:22px">
    <div style="background:linear-gradient(135deg,#1c2b2a 0%,#263635 100%);border-radius:14px;padding:18px 20px;color:#fff">
        <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.5);margin-bottom:6px">Revenue This Month</div>
        <div style="font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:500;color:#d4a853">
            ₹{{ number_format($kpis['revenue_month'], 0) }}
        </div>
        <div style="font-size:.75rem;color:rgba(255,255,255,.5);margin-top:4px">Total: ₹{{ number_format($kpis['revenue_total'], 0) }}</div>
    </div>
    <div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:14px;padding:18px 20px">
        <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--txt-lt);margin-bottom:6px">Completion Rate</div>
        @php $rate = $kpis['total_appointments'] > 0 ? round($kpis['completed_apts']/$kpis['total_appointments']*100) : 0; @endphp
        <div style="font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:500;color:#3d7a6e">{{ $rate }}%</div>
        <div style="height:6px;background:var(--warm-bd);border-radius:3px;overflow:hidden;margin-top:10px">
            <div style="height:100%;background:#3d7a6e;border-radius:3px;width:{{ $rate }}%;transition:width .6s"></div>
        </div>
    </div>
    <div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:14px;padding:18px 20px">
        <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--txt-lt);margin-bottom:10px">Exports</div>
        @foreach(['Patients' => route('doctor.analytics.export.patients'), 'Appointments' => route('doctor.analytics.export.appointments'), 'Prescriptions' => route('doctor.analytics.export.prescriptions')] as $lbl => $url)
        <a href="{{ $url }}"
           style="display:flex;align-items:center;gap:6px;padding:5px 0;font-size:.8rem;color:var(--txt-md);text-decoration:none;border-bottom:1px solid var(--warm-bd);transition:color .12s"
           onmouseover="this.style.color='var(--leaf)'" onmouseout="this.style.color='var(--txt-md)'">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {{ $lbl }} (.xlsx)
        </a>
        @endforeach
    </div>
</div>

{{-- ── Charts row ───────────────────────────────────────────────────────────── --}}
<div class="an-charts" style="display:grid;grid-template-columns:1.6fr 1fr;gap:16px;margin-bottom:22px">

    {{-- Appointment 6-month trend --}}
    <div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:14px;padding:0;overflow:hidden">
        <div style="padding:14px 20px;border-bottom:1px solid var(--warm-bd)">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt)">Appointment Trend</div>
            <div style="font-size:.75rem;color:var(--txt-lt)">Last 6 months</div>
        </div>
        <div style="padding:18px;height:220px">
            <canvas id="aptTrendChart"></canvas>
        </div>
    </div>

    {{-- Day of week chart --}}
    <div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:14px;padding:0;overflow:hidden">
        <div style="padding:14px 20px;border-bottom:1px solid var(--warm-bd)">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt)">Busiest Days</div>
            <div style="font-size:.75rem;color:var(--txt-lt)">Appointments by day of week</div>
        </div>
        <div style="padding:18px;height:220px">
            <canvas id="dayChart"></canvas>
        </div>
    </div>
</div>

{{-- ── Revenue by payment head ─────────────────────────────────────────────── --}}
<div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:14px;overflow:hidden;margin-bottom:22px">
    <div style="padding:14px 20px;border-bottom:1.5px solid var(--warm-bd);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <div>
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt)">Revenue by Payment Head</div>
            <div style="font-size:.75rem;color:var(--txt-lt)">From money-received bills (partial &amp; paid)</div>
        </div>
        @php $hrTotalBilled = array_sum(array_column($headRevenue, 'billed')); $hrTotalCollected = array_sum(array_column($headRevenue, 'collected')); @endphp
        <div style="font-size:.75rem;color:var(--txt-md)">
            Billed <strong style="color:var(--txt)">₹{{ number_format($hrTotalBilled, 0) }}</strong> ·
            Collected <strong style="color:#2a7a6a">₹{{ number_format($hrTotalCollected, 0) }}</strong>
        </div>
    </div>

    @if($headRevenue === [])
    <div style="padding:28px;text-align:center;color:var(--txt-lt);font-size:.875rem">
        No payments recorded yet — revenue will appear once visits are completed through the billing panel.
    </div>
    @else
    <div class="dr-table-wrap">
    <table style="width:100%;border-collapse:collapse;min-width:620px">
        <thead><tr style="border-bottom:1.5px solid var(--warm-bd)">
            <th style="padding:9px 18px;text-align:left;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt)">Payment head</th>
            <th style="padding:9px 18px;text-align:left;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt)">Billed</th>
            <th style="padding:9px 18px;text-align:left;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt)">Collected (pro rata)</th>
            <th style="padding:9px 18px;text-align:right;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt);padding-right:20px">Share of billings</th>
        </tr></thead>
        <tbody>
        @foreach($headRevenue as $row)
        <tr style="border-bottom:1px solid var(--warm-bd)">
            <td style="padding:11px 18px;font-size:.875rem;font-weight:500;color:var(--txt)">{{ $row['label'] }}</td>
            <td style="padding:11px 18px;font-size:.85rem;font-weight:600;color:var(--txt)">₹{{ number_format($row['billed'], 2) }}</td>
            <td style="padding:11px 18px;font-size:.85rem;color:#2a7a6a">₹{{ number_format($row['collected'], 2) }}</td>
            <td style="padding:11px 18px;text-align:right;padding-right:20px">
                <div style="display:inline-flex;align-items:center;gap:8px">
                    <div style="height:6px;background:var(--warm-bd);border-radius:3px;width:110px;overflow:hidden">
                        <div style="height:100%;background:#d4a853;border-radius:3px;width:{{ $hrTotalBilled > 0 ? min(100, $row['billed'] / $hrTotalBilled * 100) : 0 }}%"></div>
                    </div>
                    <span style="font-size:.75rem;color:var(--txt-lt);width:52px;text-align:right">{{ $hrTotalBilled > 0 ? round($row['billed'] / $hrTotalBilled * 100) : 0 }}%</span>
                </div>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @if($hrTotalBilled > 0)
    <div style="padding:10px 20px;border-top:1px solid var(--warm-bd);font-size:.7rem;color:var(--txt-lt)">
        Collected is estimated pro rata: each bill's payments spread across its lines by share of the total. Balances still outstanding reduce it.
    </div>
    @endif
    @endif
</div>

{{-- ── Top Patients ─────────────────────────────────────────────────────────── --}}
<div style="background:#fff;border:1.5px solid var(--warm-bd);border-radius:14px;overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1.5px solid var(--warm-bd)">
        <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt)">Top Patients</div>
        <div style="font-size:.75rem;color:var(--txt-lt)">Most frequent visitors</div>
    </div>

    @if($topPatients->isEmpty())
    <div style="padding:28px;text-align:center;color:var(--txt-lt);font-size:.875rem">
        No patient visit data yet.
    </div>
    @else
    <div class="dr-table-wrap">
    <table style="width:100%;border-collapse:collapse;min-width:560px">
        <thead><tr style="border-bottom:1.5px solid var(--warm-bd)">
            <th style="padding:9px 18px;text-align:left;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt)">#</th>
            <th style="padding:9px 18px;text-align:left;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt)">Patient</th>
            <th style="padding:9px 18px;text-align:left;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt)">Visits</th>
            <th style="padding:9px 18px;text-align:left;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-lt)">Last Visit</th>
            <th style="padding:9px 18px"></th>
        </tr></thead>
        <tbody>
        @foreach($topPatients as $i => $tp)
        @php
            $name     = $tp->patient?->profile?->full_name ?? 'Unknown';
            $colors   = ['#3d7a6e','#4a3760','#c98a3a','#3d5e7a','#c0737a','#6b7280'];
            $color    = $colors[$i % count($colors)];
        @endphp
        <tr style="border-bottom:1px solid var(--warm-bd);transition:background .1s" onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">
            <td style="padding:11px 18px;font-size:.85rem;color:var(--txt-lt);font-weight:500">{{ $i + 1 }}</td>
            <td style="padding:11px 18px">
                <div style="display:flex;align-items:center;gap:10px">
                    <x-avatar name="{{ $name }}" :photo="$tp->patient?->profile?->profile_photo"
                               :size="34" :radius="9" :bg="$color" font-size=".8rem" />
                    <div style="font-weight:500;font-size:.875rem;color:var(--txt)">{{ $name }}</div>
                </div>
            </td>
            <td style="padding:11px 18px">
                <div style="display:flex;align-items:center;gap:8px">
                    <div style="height:6px;background:var(--warm-bd);border-radius:3px;width:80px;overflow:hidden">
                        <div style="height:100%;background:{{ $color }};border-radius:3px;width:{{ min(100, $tp->visit_count / ($topPatients->first()->visit_count) * 100) }}%"></div>
                    </div>
                    <span style="font-size:.85rem;font-weight:600;color:var(--txt)">{{ $tp->visit_count }}</span>
                </div>
            </td>
            <td style="padding:11px 18px;font-size:.8rem;color:var(--txt-lt)">
                {{ \Carbon\Carbon::parse($tp->last_visit)->format('d M Y') }}
            </td>
            <td style="padding:11px 18px;text-align:right">
                <a href="{{ route('doctor.patients.history', $tp->patient_user_id) }}"
                   style="font-size:.75rem;padding:4px 10px;border:1.5px solid var(--warm-bd);border-radius:8px;color:var(--txt-md);text-decoration:none;transition:all .12s"
                   onmouseover="this.style.background='var(--parch)'" onmouseout="this.style.background='transparent'">View</a>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>

</div>

@push('styles')
<style>
    @media (max-width: 1024px) {
        .an-kpi { grid-template-columns: 1fr 1fr !important; }
        .an-3col { grid-template-columns: 1fr !important; }
        .an-charts { grid-template-columns: 1fr !important; }
    }
    @media (max-width: 480px) {
        .an-kpi { grid-template-columns: 1fr !important; }
    }
</style>
@endpush
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartOpts = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { labels: { font: { family:'Outfit', size:11 }, boxWidth:10 } } },
    scales: {
        x: { grid:{ display:false }, ticks:{ font:{ family:'Outfit', size:10 } } },
        y: { grid:{ color:'#ede8e0' }, ticks:{ font:{ family:'Outfit', size:10 }, precision:0 } }
    }
};

// Appointment trend
new Chart(document.getElementById('aptTrendChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: {!! json_encode(array_column($aptTrend, 'label')) !!},
        datasets: [
            { label:'Completed', data:{!! json_encode(array_column($aptTrend,'completed')) !!}, backgroundColor:'#3d7a6e', borderRadius:4, borderSkipped:false },
            { label:'Cancelled', data:{!! json_encode(array_column($aptTrend,'cancelled')) !!}, backgroundColor:'#e8724a', borderRadius:4, borderSkipped:false },
        ]
    },
    options: { ...chartOpts, scales: { ...chartOpts.scales, x:{ ...chartOpts.scales.x, stacked:true }, y:{ ...chartOpts.scales.y, stacked:true } } }
});

// Day of week
new Chart(document.getElementById('dayChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: {!! json_encode(array_values($dayLabels)) !!},
        datasets: [{
            label:'Appointments',
            data: {!! json_encode($dayData) !!},
            backgroundColor: ['#e8e2da','#3d7a6e','#3d7a6e','#3d7a6e','#3d7a6e','#3d7a6e','#e8e2da'],
            borderRadius: 5, borderSkipped: false,
        }]
    },
    options: { ...chartOpts, plugins:{ legend:{ display:false } } }
});
</script>
@endpush
