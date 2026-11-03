<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\FamilyMember;
use App\Models\Appointment;
use App\Models\DoctorAccessRequest;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\HealthLog;
use App\Models\PatientTimeline;
use App\Models\PatientHistoryDocument;
use Illuminate\Support\Facades\Storage;
use App\Services\AccessControlService;
use App\Services\OtpService;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function __construct(
        private AccessControlService $accessControl,
        private OtpService           $otpService,
    ) {}

    // ─── Patient List ────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $doctor = auth()->user();

        // All unique patients who have ever had an approved access grant
        // OR have medical records / appointments with this doctor
        $patientIds = collect()
            ->merge(
                DoctorAccessRequest::where('doctor_user_id', $doctor->id)
                    ->whereIn('status', ['approved', 'expired'])
                    ->pluck('patient_user_id')
            )
            ->merge(
                \App\Models\MedicalRecord::where('doctor_user_id', $doctor->id)
                    ->pluck('patient_user_id')
            )
            ->merge(
                \App\Models\Appointment::where('doctor_user_id', $doctor->id)
                    ->pluck('patient_user_id')
            )
            ->unique()
            ->values();

        $query = User::whereIn('id', $patientIds)
            ->where('role', 'patient')
            ->with(['profile', 'familyMembers' => fn($q) => $q->where('is_delinked', false)]);

        // Search
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('profile', fn($p) => $p->where('full_name', 'like', "%{$search}%"))
                  ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        // Filter
        if ($filter = $request->get('filter')) {
            match ($filter) {
                'active'  => $query->whereHas('accessRequests', fn($q) =>
                    $q->where('doctor_user_id', $doctor->id)->active()
                ),
                'recent'  => $query->whereHas('medicalRecords', fn($q) =>
                    $q->where('doctor_user_id', $doctor->id)->where('visit_date', '>=', now()->subDays(30))
                ),
                default   => null,
            };
        }

        $patients = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        // Attach per-patient access status + last visit for the list
        $patients->each(function (User $p) use ($doctor) {
            $p->active_access = DoctorAccessRequest::where('doctor_user_id', $doctor->id)
                ->where('patient_user_id', $p->id)
                ->active()
                ->exists();

            $p->last_visit = MedicalRecord::where('doctor_user_id', $doctor->id)
                ->where('patient_user_id', $p->id)
                ->latest('visit_date')
                ->value('visit_date');

            $p->total_visits = MedicalRecord::where('doctor_user_id', $doctor->id)
                ->where('patient_user_id', $p->id)
                ->count();
        });

        $assignTemplate = $request->get('assign_template');

        return view('doctor.patients.index', compact('patients', 'search', 'filter', 'assignTemplate'));
    }

    // ─── AJAX Patient Search (for access request modal) ──────────────────────

    public function search(Request $request)
    {
        $q      = $request->get('q', '');
        $type   = $request->get('type', 'mobile'); // mobile | sub_id | aadhaar | any

        if (strlen($q) < 2) {
            return response()->json(['found' => false, 'patients' => []]);
        }

        // Multi-mode search: try name, mobile, and sub_id
        if ($type === 'mobile' || $type === 'any') {
            $patients = User::where('role', 'patient')
                ->where(function ($query) use ($q) {
                    $query->where('mobile_number', 'like', "%{$q}%")
                          ->orWhereHas('profile', fn($p) => $p->where('full_name', 'like', "%{$q}%"));
                })
                ->with('profile')
                ->limit(10)
                ->get();

            if ($patients->isNotEmpty()) {
                $list = $patients->map(fn($p) => [
                    'id'     => $p->id,
                    'name'   => $p->profile?->full_name ?? 'Unknown',
                    'mobile' => $p->full_mobile ?? $p->mobile_number,
                    'age'    => $p->profile?->age,
                    'gender' => $p->profile?->gender,
                    'city'   => $p->profile?->city,
                ])->values();

                $first = $patients->first();

                return response()->json([
                    'found'           => true,
                    'patient'         => $list->first(),
                    'patients'        => $list,
                    // Resolved identifier — the typed query may have been a name,
                    // which the access-request lookup cannot resolve on its own.
                    'identifier'      => $first->mobile_number,
                    'identifier_type' => 'mobile',
                ]);
            }
        }

        // Fallback to existing access control search for aadhaar/sub_id
        if ($type !== 'any') {
            $result = $this->accessControl->findPatient($q, $type);

            if ($result['found']) {
                $patient = $result['user'];
                $member  = $result['member'];

                $patientData = [
                    'id'     => $patient->id,
                    'name'   => $patient->profile?->full_name ?? 'Unknown',
                    'mobile' => $patient->full_mobile,
                    'age'    => $patient->profile?->age,
                    'gender' => $patient->profile?->gender,
                    'city'   => $patient->profile?->city,
                ];

                return response()->json([
                    'found'           => true,
                    'patient'         => $patientData,
                    'patients'        => [$patientData],
                    'family_member'   => $member ? [
                        'id'       => $member->id,
                        'name'     => $member->full_name,
                        'relation' => $member->relation,
                        'sub_id'   => $member->sub_id,
                    ] : null,
                    'identifier'      => $q,
                    'identifier_type' => $type,
                ]);
            }
        }

        return response()->json(['found' => false, 'patients' => [], 'message' => 'No patient found.']);
    }

    // ─── Raise Access Request (from patient list or modal) ───────────────────

    public function requestAccess(Request $request)
    {
        $request->validate([
            'identifier'       => ['required', 'string', 'min:3'],
            'identifier_type'  => ['required', 'in:mobile,sub_id,aadhaar'],
            'patient_id'       => ['nullable', 'integer', 'exists:users,id'],
            'family_member_id' => ['nullable', 'integer', 'exists:family_members,id'],
        ]);

        $doctor = auth()->user();

        // The modal already resolved the patient from search — use that instead
        // of re-parsing a typed name/mobile, which fails the strict lookup.
        $patient = null;
        $member  = null;

        if ($request->filled('patient_id')) {
            $patient = User::where('id', $request->patient_id)
                ->where('role', 'patient')
                ->first();

            if (! $patient) {
                $error = ['status' => 'error', 'message' => 'No patient found with that identifier.'];

                return $request->expectsJson()
                    ? response()->json($error, 404)
                    : back()->withErrors(['identifier' => $error['message']]);
            }

            if ($request->filled('family_member_id')) {
                $member = FamilyMember::where('id', $request->family_member_id)
                    ->where('primary_user_id', $patient->id)
                    ->first();
            }
        }

        $result = $this->accessControl->raiseRequest(
            $doctor,
            $request->identifier,
            $request->identifier_type,
            $patient,
            $member
        );

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        if ($result['status'] === 'error') {
            return back()->withErrors(['identifier' => $result['message']]);
        }

        $msg = match ($result['status']) {
            'approved'       => 'Access granted immediately. You can now view this patient\'s records.',
            'already_active' => 'You already have active access to this patient.',
            'pending'        => 'OTP sent to patient\'s WhatsApp. Ask them to share the code.',
            default          => $result['message'],
        };

        return back()->with('success', $msg)
            ->with('access_result', $result);
    }

    // ─── Verify OTP (AJAX) ───────────────────────────────────────────────────

    public function verifyAccessOtp(Request $request, DoctorAccessRequest $accessRequest)
    {
        $request->validate(['otp' => ['required', 'digits:6']]);

        if ($accessRequest->doctor_user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $result = $this->accessControl->verifyAccessOtp($accessRequest, $request->otp);

        return response()->json($result);
    }

    // ─── Doctor downloads a patient-uploaded document ────────────────────────

    public function downloadPatientDocument(PatientHistoryDocument $document)
    {
        $doctor = auth()->user();

        // Doctor must have active access to this patient
        if (!$this->accessControl->doctorHasAccess($doctor, $document->patient_user_id)) {
            abort(403, 'You do not have access to this patient\'s documents.');
        }

        if (!Storage::disk('local')->exists($document->file_path)) abort(404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    // ─── Full Patient History ────────────────────────────────────────────────

    public function history(Request $request, int $patientId)
    {
        $doctor  = auth()->user();
        $patient = User::where('id', $patientId)->where('role', 'patient')
            ->with(['profile', 'familyMembers' => fn($q) => $q->where('is_delinked', false)])
            ->firstOrFail();

        // Access check: doctor must have active access OR patient has full-access perm
        $hasAccess  = $this->accessControl->doctorHasAccess($doctor, $patientId);
        $pendingReq = DoctorAccessRequest::where('doctor_user_id', $doctor->id)
            ->where('patient_user_id', $patientId)
            ->where('status', 'pending')
            ->where('otp_expires_at', '>', now())
            ->first();

        // Active access grant details
        $accessGrant = DoctorAccessRequest::where('doctor_user_id', $doctor->id)
            ->where('patient_user_id', $patientId)
            ->active()
            ->first();

        // Which family member are we viewing (default: primary patient)
        $familyMemberId = $request->get('member'); // null = primary
        $viewingMember  = $familyMemberId
            ? $patient->familyMembers->find($familyMemberId)
            : null;

        // ── Medical Records ───────────────────────────────────────────────────
        $records = $hasAccess
            ? MedicalRecord::where('patient_user_id', $patientId)
                ->when($familyMemberId, fn($q) => $q->where('family_member_id', $familyMemberId))
                ->when(!$familyMemberId, fn($q) => $q->whereNull('family_member_id'))
                ->with(['doctor.profile', 'doctor.doctorProfile', 'prescription.medicines', 'prescriptions'])
                ->latest('visit_date')
                ->paginate(10, ['*'], 'rpage')
            : collect();
        // dd($records);
        // ── Prescriptions ─────────────────────────────────────────────────────
        $prescriptions = $hasAccess
            ? Prescription::where('patient_user_id', $patientId)
                ->when($familyMemberId, fn($q) => $q->where('family_member_id', $familyMemberId))
                ->when(!$familyMemberId, fn($q) => $q->whereNull('family_member_id'))
                ->with(['doctor.profile', 'medicines'])
                ->latest('prescribed_date')
                ->paginate(8, ['*'], 'ppage')
            : collect();
        // ── Vitals (last 30 days, for chart) ──────────────────────────────────
        $vitalsData = [];
        if ($hasAccess) {
            foreach (['blood_pressure', 'blood_sugar', 'weight', 'pulse'] as $type) {
                $vitalsData[$type] = HealthLog::where('patient_user_id', $patientId)
                    ->where('log_type', $type)
                    ->where('logged_at', '>=', now()->subDays(30))
                    ->orderBy('logged_at')
                    ->get()
                    ->map(fn($l) => [
                        'date'  => $l->logged_at->format('d M'),
                        'val1'  => $l->value_1,
                        'val2'  => $l->value_2,
                        'label' => $l->logged_at->format('d M Y H:i'),
                    ]);
            }
        }

        // ── Active timelines ──────────────────────────────────────────────────
        $timelines = $hasAccess
            ? PatientTimeline::where('patient_user_id', $patientId)
                ->where('is_active', true)
                ->with('template')
                ->get()
                ->map(fn($pt) => [
                    'timeline'   => $pt,
                    'milestones' => $pt->getMilestonesWithDates(),
                ])
            : collect();

        // ── Patient-uploaded documents ────────────────────────────────────────
        $patientDocuments = $hasAccess
            ? PatientHistoryDocument::where('patient_user_id', $patientId)
                ->when($familyMemberId, fn($q) => $q->where('family_member_id', $familyMemberId))
                ->when(!$familyMemberId, fn($q) => $q->whereNull('family_member_id'))
                ->orderByDesc('document_date')
                ->orderByDesc('created_at')
                ->get()
            : collect();

        // ── Today's open walk-in visit (drives the Start Visit / Visit in progress action) ──
        $activeVisit = $hasAccess
            ? Appointment::openVisitToday($doctor->id, $patientId, $viewingMember?->id)->first()
            : null;

        // ── Summary stats ─────────────────────────────────────────────────────
        $stats = $hasAccess ? [
            'total_visits'   => MedicalRecord::where('patient_user_id', $patientId)->count(),
            'total_rx'       => Prescription::where('patient_user_id', $patientId)->count(),
            'last_visit'     => MedicalRecord::where('patient_user_id', $patientId)->max('visit_date'),
            'first_visit'    => MedicalRecord::where('patient_user_id', $patientId)->min('visit_date'),
            'my_visits'      => MedicalRecord::where('patient_user_id', $patientId)->where('doctor_user_id', $doctor->id)->count(),
        ] : [];

        return view('doctor.patients.history', compact(
            'patient', 'doctor',
            'hasAccess', 'pendingReq', 'accessGrant',
            'viewingMember', 'familyMemberId',
            'records', 'prescriptions',
            'vitalsData', 'timelines', 'stats', 'patientDocuments', 'activeVisit'
        ));
    }
}
