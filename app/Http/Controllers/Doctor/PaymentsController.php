<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Bill;
use App\Services\ExcelExportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function __construct(private ExcelExportService $excel) {}

    // Step 3: list of all the doctor's appointments with payment info.
    public function index(Request $request)
    {
        $doctor = auth()->user();

        $tab     = $this->tabFrom($request);
        $filters = $this->filtersFrom($request);

        $query = $this->filteredQuery($doctor, $tab, $filters);

        // Counts for tabs (always reflect the un-filtered totals so the
        // tab badges don't shrink while the user types in the search box).
        $base = $this->baseQuery($doctor);

        $counts = [
            'all'    => (clone $base)->count(),
            'paid'   => (clone $base)->where('payment_status', 'paid')->count(),
            'cash'   => (clone $base)->where('payment_status', 'paid')->where('payment_preference', 'cash')->count(),
            'unpaid' => (clone $base)->where('payment_status', 'pending')->count(),
        ];

        $payments = $query->paginate(20)->withQueryString();
        $totals   = $this->paymentTotals($doctor->id)
                    + $this->billTotals($doctor->id);

        return view('doctor.payments.index', compact('payments', 'tab', 'counts', 'totals', 'filters'));
    }

    /**
     * Excel export of the payments list as a 3-sheet workbook (payments,
     * per-line charges, revenue by head). Honors the same tab, search and
     * filter inputs as the page, so all sheets match what the doctor sees.
     */
    public function export(Request $request)
    {
        $doctor = auth()->user();

        $appointments = $this->filteredQuery($doctor, $this->tabFrom($request), $this->filtersFrom($request))
            ->with('bill.items')
            ->get();

        return $this->excel->exportPayments($doctor, $appointments);
    }

    // ── Query building (shared by index + export) ─────────────────────────

    /** Active tab from the request: all | paid | cash | unpaid. */
    private function tabFrom(Request $request): string
    {
        return match ($request->get('tab', 'all')) {
            'paid', 'cash', 'unpaid' => $request->get('tab'),
            default                  => 'all',
        };
    }

    /** Search / filter bar inputs, exactly as the page defines them. */
    private function filtersFrom(Request $request): array
    {
        return [
            'q'      => trim((string) $request->get('q', '')),
            'status' => $request->get('status', ''),  // '', 'paid', 'due'
            'method' => $request->get('method', ''),  // '', 'cash', 'online'
            'from'   => $request->get('from', ''),   // dd-mm-yyyy
            'to'     => $request->get('to', ''),     // dd-mm-yyyy
        ];
    }

    /** All appointments eligible for the payments page, with bill eager loads. */
    private function baseQuery($doctor): Builder
    {
        return Appointment::where('doctor_user_id', $doctor->id)
            ->with(['patient.profile', 'familyMember', 'bill.items'])
            ->whereIn('status', ['completed', 'confirmed', 'booked'])
            ->orderByDesc('slot_datetime');
    }

    /** Base query + tab + search/filter bar inputs applied. */
    private function filteredQuery($doctor, string $tab, array $filters): Builder
    {
        $query = clone $this->baseQuery($doctor);

        switch ($tab) {
            case 'paid':
                $query->where('payment_status', 'paid');
                break;
            case 'cash':
                $query->where('payment_status', 'paid')
                    ->where('payment_preference', 'cash');
                break;
            case 'unpaid':
                $query->where('payment_status', 'pending');
                break;
        }

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply the search/filter form inputs to the appointments query.
     * Always called *after* the tab switch, so any filter composes on top
     * of the active tab.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        // Free-text: patient name (via user_profiles), phone, email,
        // appointment #, or visit notes
        if ($filters['q'] !== '') {
            $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']) . '%';
            $query->where(function (Builder $q) use ($needle) {
                $q->where('appointment_number', 'like', $needle)
                  ->orWhere('completion_note', 'like', $needle)
                  ->orWhere('reason', 'like', $needle)
                  ->orWhereHas('patient', function (Builder $pq) use ($needle) {
                      $pq->where('mobile_number', 'like', $needle)
                         ->orWhere('email', 'like', $needle)
                         ->orWhereHas('profile', function (Builder $up) use ($needle) {
                             $up->where('full_name', 'like', $needle);
                         });
                  });
            });
        }

        // Status (secondary filter on top of the tab)
        if ($filters['status'] === 'paid') {
            $query->where('payment_status', 'paid');
        } elseif ($filters['status'] === 'due') {
            $query->where('payment_status', 'pending');
        }

        // Method: schema values are 'cash' and 'online'
        if (in_array($filters['method'], ['cash', 'online'], true)) {
            $query->where('payment_preference', $filters['method']);
        }

        // Date range on the appointment slot
        if ($from = $this->parseDate($filters['from'])) {
            $query->where('slot_datetime', '>=', $from->startOfDay());
        }
        if ($to = $this->parseDate($filters['to'])) {
            $query->where('slot_datetime', '<=', $to->endOfDay());
        }
    }

    /** Parse a dd-mm-yyyy string (or null/garbage) into a Carbon, or null. */
    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }
        $dt = Carbon::createFromFormat('d-m-Y', $value);
        // createFromFormat() does not throw on bad input — it returns a
        // Carbon with a default date. Reject anything that didn't match
        // the literal dd-mm-yyyy pattern.
        if ($dt && $dt->format('d-m-Y') === $value) {
            return $dt->startOfDay();
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * AJAX endpoint backing the autocomplete list in the search box.
     * Returns up to 8 distinct patient names that this doctor has seen
     * (or whose appointment rows the doctor owns), filtered by a `q`
     * query string. Used as the <datalist> for the search input.
     */
    public function patientSuggestions(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 1) {
            return response()->json(['suggestions' => []]);
        }

        $doctor = auth()->user();
        $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        // Only patients this doctor has actually had an appointment with.
        // groupBy('patient_user_id') so each patient appears at most once.
        $rows = Appointment::query()
            ->select('patient_user_id')
            ->where('doctor_user_id', $doctor->id)
            ->whereNotNull('patient_user_id')
            ->whereHas('patient', function (Builder $pq) use ($needle) {
                $pq->whereHas('profile', function (Builder $up) use ($needle) {
                    $up->where('full_name', 'like', $needle);
                });
            })
            ->groupBy('patient_user_id')
            ->limit(8)
            ->with(['patient.profile'])
            ->get();

        $suggestions = $rows->map(function (Appointment $appt) {
            $name  = $appt->patient?->profile?->full_name ?? '';
            $phone = $appt->patient?->full_mobile ?? $appt->patient?->mobile_number ?? '';
            return [
                'name'  => $name,
                'phone' => $phone,
            ];
        })
        ->filter(fn ($s) => $s['name'] !== '')
        ->unique('name')
        ->values();

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * Scoped payment totals for the KPI strip on the doctor's payments page.
     * Computed entirely from the appointments table since there is no
     * separate payments table — the appointment row carries both the
     * amount (fee), the status (payment_status) and the method
     * (payment_preference: 'cash' or 'online').
     */
    private function paymentTotals(int $doctorUserId): array
    {
        $base = Appointment::where('doctor_user_id', $doctorUserId)
            ->whereNotIn('status', ['cancelled']);

        $paid = (clone $base)->where('payment_status', 'paid');
        $due  = (clone $base)->where('payment_status', 'pending')
            ->whereNotNull('fee')->where('fee', '>', 0);

        return [
            'all'          => (int)   (clone $base)->count(),
            'paid'         => (float) (clone $paid)->sum('fee'),
            'cash_total'   => (float) (clone $paid)->where('payment_preference', 'cash')->sum('fee'),
            'online_total' => (float) (clone $paid)->where('payment_preference', 'online')->sum('fee'),
            'due_total'    => (float) $due->sum('fee'),
        ];
    }

    /**
     * Bill-model totals: money actually collected and still owed per the
     * bills table (appointments alone cannot see added lines or discounts).
     * Waived bills carry no expectation of payment, so their totals are
     * shown separately so doctors can see how much was waived.
     */
    private function billTotals(int $doctorUserId): array
    {
        $base = Bill::where('doctor_user_id', $doctorUserId)
            ->whereNull('deleted_at');

        return [
            'bill_collected'   => (float) (clone $base)->whereIn('status', ['partial', 'paid'])->sum('amount_paid'),
            'bill_outstanding' => (float) (clone $base)->whereIn('status', ['draft', 'unpaid', 'partial'])->sum('balance'),
            'bill_waived'      => (float) (clone $base)->where('status', 'waived')->sum('total'),
        ];
    }
}
