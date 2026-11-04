<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\MedicalRecord;
use App\Models\Payment;
use App\Services\ExcelExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __construct(private ExcelExportService $excel) {}

    // ── Main analytics dashboard ──────────────────────────────────────────────

    public function index()
    {
        $doctor = auth()->user();
        $now    = now();

        // ── KPIs ──────────────────────────────────────────────────────────────
        $kpis = [
            'total_patients'      => $this->uniquePatients($doctor->id),
            'patients_this_month' => $this->uniquePatients($doctor->id, $now->copy()->startOfMonth()),
            'total_appointments'  => Appointment::where('doctor_user_id', $doctor->id)->count(),
            'apts_this_month'     => Appointment::where('doctor_user_id', $doctor->id)
                                        ->whereMonth('slot_datetime', $now->month)
                                        ->whereYear('slot_datetime', $now->year)->count(),
            'completed_apts'      => Appointment::where('doctor_user_id', $doctor->id)->where('status','completed')->count(),
            'cancelled_apts'      => Appointment::where('doctor_user_id', $doctor->id)->where('status','cancelled')->count(),
            'total_prescriptions' => Prescription::where('doctor_user_id', $doctor->id)->count(),
            'rx_this_month'       => Prescription::where('doctor_user_id', $doctor->id)
                                        ->whereMonth('prescribed_date', $now->month)->count(),
            'revenue_month'       => Payment::whereHas('appointment', fn($q) => $q->where('doctor_user_id', $doctor->id))
                                        ->paid()->whereMonth('paid_at', $now->month)->sum('amount'),
            'revenue_total'       => Payment::whereHas('appointment', fn($q) => $q->where('doctor_user_id', $doctor->id))
                                        ->paid()->sum('amount'),
            'no_show_rate'        => $this->noShowRate($doctor->id),
        ];

        // ── 6-month appointment trend ─────────────────────────────────────────
        $aptTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $now->copy()->subMonths($i);
            $base = Appointment::where('doctor_user_id', $doctor->id)
                ->whereYear('slot_datetime', $m->year)->whereMonth('slot_datetime', $m->month);
            $aptTrend[] = [
                'label'     => $m->format('M'),
                'total'     => (clone $base)->whereNotIn('status',['cancelled'])->count(),
                'completed' => (clone $base)->where('status','completed')->count(),
                'cancelled' => (clone $base)->where('status','cancelled')->count(),
            ];
        }

        // ── Top 10 patients (most visits) ─────────────────────────────────────
        $topPatients = MedicalRecord::where('doctor_user_id', $doctor->id)
            ->select('patient_user_id', DB::raw('count(*) as visit_count'), DB::raw('max(visit_date) as last_visit'))
            ->groupBy('patient_user_id')
            ->orderByDesc('visit_count')
            ->limit(8)
            ->with('patient.profile')
            ->get();

        // ── Appointment types breakdown ───────────────────────────────────────
        $aptTypes = Appointment::where('doctor_user_id', $doctor->id)
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        // ── Day-of-week heatmap ───────────────────────────────────────────────
        $dayHeatmap = Appointment::where('doctor_user_id', $doctor->id)
            ->whereNotIn('status',['cancelled'])
            ->select(DB::raw('DAYOFWEEK(slot_datetime) as dow'), DB::raw('count(*) as total'))
            ->groupBy('dow')
            ->pluck('total', 'dow')
            ->toArray();

        $dayLabels = [1=>'Sun',2=>'Mon',3=>'Tue',4=>'Wed',5=>'Thu',6=>'Fri',7=>'Sat'];
        $dayData   = array_map(fn($dow) => $dayHeatmap[$dow] ?? 0, array_keys($dayLabels));

        // ── Revenue by payment head (bill_items → bills) ─────────────────────
        $headRevenue = $this->revenueByHead($doctor->id);

        return view('doctor.analytics.index', compact(
            'kpis', 'aptTrend', 'topPatients', 'aptTypes', 'dayLabels', 'dayData', 'headRevenue'
        ));
    }

    // ── Exports ───────────────────────────────────────────────────────────────

    public function exportPatients()
    {
        return $this->excel->exportPatients(auth()->user());
    }

    public function exportAppointments(Request $request)
    {
        return $this->excel->exportAppointments(auth()->user(), $request->from, $request->to);
    }

    public function exportPrescriptions()
    {
        return $this->excel->exportPrescriptions(auth()->user());
    }

    // stubs (redirect to main)
    public function patients()      { return redirect()->route('doctor.analytics.index'); }
    public function appointments()  { return redirect()->route('doctor.analytics.index'); }
    public function revenue()       { return redirect()->route('doctor.analytics.index'); }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function uniquePatients(int $doctorId, ?Carbon $since = null): int
    {
        return Appointment::where('doctor_user_id', $doctorId)
            ->when($since, fn($q) => $q->where('slot_datetime', '>=', $since))
            ->distinct('patient_user_id')
            ->count('patient_user_id');
    }

    private function noShowRate(int $doctorId): string
    {
        $total     = Appointment::where('doctor_user_id', $doctorId)
                        ->whereIn('status',['completed','cancelled'])->count();
        $cancelled = Appointment::where('doctor_user_id', $doctorId)->where('status','cancelled')->count();

        return $total > 0 ? round($cancelled / $total * 100) . '%' : '0%';
    }

    /**
     * Revenue grouped by billing head.
     *
     * Only bills that actually received money count (status partial|paid with
     * amount_paid > 0); waived and draft bills are excluded. `billed` is the
     * raw line value; `collected` apportions each bill's payments to its lines
     * pro rata (bill.amount_paid / bill.total), because payments are not
     * allocated to individual lines — see docs/BILLING.md.
     */
    private function revenueByHead(int $doctorId): array
    {
        $rows = DB::table('bill_items as bi')
            ->join('bills as b', 'b.id', '=', 'bi.bill_id')
            ->where('b.doctor_user_id', $doctorId)
            ->whereNull('b.deleted_at')
            ->whereIn('b.status', ['partial', 'paid'])
            ->where('b.amount_paid', '>', 0)
            ->select(
                'bi.head',
                DB::raw('SUM(bi.amount) as billed'),
                DB::raw('ROUND(SUM(bi.amount * b.amount_paid / NULLIF(b.total, 0)), 2) as collected')
            )
            ->groupBy('bi.head')
            ->orderByDesc('billed')
            ->get();

        return $rows->map(fn ($r) => [
            'head'      => $r->head,
            'label'     => (string) config(
                'medtech.billing_heads.' . $r->head . '.label',
                ucfirst(str_replace('_', ' ', $r->head))
            ),
            'billed'    => (float) $r->billed,
            'collected' => min((float) $r->billed, (float) $r->collected),
        ])->values()->all();
    }
}
