<?php

namespace App\Services;

use App\Models\User;
use App\Models\FamilyMember;
use App\Models\PatientAccessPermission;
use App\Models\DoctorAccessRequest;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;


class AccessControlService
{
    public function __construct(
        private OtpService      $otpService,
        private WhatsAppService $whatsAppService,
    ) {}

    // ─── Patient Lookup ──────────────────────────────────────────────────────

    /**
     * Find a patient by mobile number, Aadhaar, or Sub-ID.
     * Used when doctor raises an access request.
     *
     * Returns ['found' => bool, 'user' => User|null, 'member' => FamilyMember|null]
     */
    public function findPatient(string $identifier, string $type): array
    {
        return match ($type) {
            'mobile'  => $this->findByMobile($identifier),
            'aadhaar' => $this->findByAadhaar($identifier),
            'sub_id'  => $this->findBySubId($identifier),
            default   => ['found' => false, 'user' => null, 'member' => null],
        };
    }

    // ─── Access Request ──────────────────────────────────────────────────────

    /**
     * Doctor raises a request to access patient history.
     * If patient is on full access → approve immediately.
     * If OTP required → create pending request and notify patient.
     *
     * Pass $patient (and optionally $familyMember) when the patient has
     * already been resolved, e.g. by the doctor's search step.
     *
     * Returns ['status' => 'approved'|'pending'|'error', 'message' => string]
     */
    public function raiseRequest(
        User $doctor,
        string $identifier,
        string $identifierType,
        ?User $patient = null,
        ?FamilyMember $familyMember = null,
    ): array {
        // Find the patient unless it was already resolved upstream
        if ($patient) {
            $result = ['found' => true, 'user' => $patient, 'member' => $familyMember];
        } else {
            $result = $this->findPatient($identifier, $identifierType);
        }

        if (! $result['found']) {
            return ['status' => 'error', 'message' => 'No patient found with that identifier.'];
        }

        $patient      = $result['user'];
        $familyMember = $result['member'];

        // Check if this doctor already has an active grant
        $existing = DoctorAccessRequest::where('doctor_user_id', $doctor->id)
            ->where('patient_user_id', $patient->id)
            ->active()
            ->first();

        if ($existing) {
            return [
                'status'  => 'already_active',
                'message' => 'You already have active access to this patient.',
                'request' => $existing,
            ];
        }

        // Determine patient's access preference
        $permission = PatientAccessPermission::where('patient_user_id', $patient->id)
            ->whereNull('family_member_id')
            ->first();

        $accessType = $permission?->access_type ?? 'otp_required';

        // Expire any previous pending requests from this doctor for this patient
        DoctorAccessRequest::where('doctor_user_id', $doctor->id)
            ->where('patient_user_id', $patient->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        // Create a new access request
        $request = DoctorAccessRequest::create([
            'doctor_user_id'    => $doctor->id,
            'patient_user_id'   => $patient->id,
            'family_member_id'  => $familyMember?->id,
            'patient_identifier'=> $identifier,
            'identifier_type'   => $identifierType,
            'status'            => 'pending',
        ]);

        NotificationService::accessRequested($request->load('doctor.profile'));

        // ── Full access: auto-approve ─────────────────────────────────────
        if ($accessType === 'full') {
            return $this->approveRequest($request);
        }

        // ── OTP required: generate OTP and notify patient ─────────────────
        $otp       = $this->generateAccessOtp();
        $expiresAt = now()->addMinutes(config('medtech.access.otp_expires', 10));

        $request->update([
            'otp'            => $otp,
            'otp_expires_at' => $expiresAt,
        ]);

        // Notify patient via WhatsApp
        $this->whatsAppService->sendAccessRequest(
            patient: $patient,
            doctorName: $doctor->profile->full_name,
            otp: $otp,
            expiresMinutes: config('medtech.access.otp_expires', 10),
        );

        Log::info('[Access] OTP request created', [
            'doctor'  => $doctor->id,
            'patient' => $patient->id,
            'otp'     => $otp,  // remove in production
        ]);

        return [
            'status'   => 'pending',
            'message'  => "Access request sent. Ask the patient for the OTP sent to their WhatsApp.",
            'request'  => $request,
        ];
    }

    /**
     * Doctor verifies the OTP the patient shares verbally.
     */
    public function verifyAccessOtp(DoctorAccessRequest $request, string $enteredOtp): array
    {
        if (! $request->isPending()) {
            return ['success' => false, 'message' => 'This request is no longer pending.'];
        }

        if (now()->isAfter($request->otp_expires_at)) {
            $request->update(['status' => 'expired']);
            return ['success' => false, 'message' => 'OTP has expired. Please raise a new request.', 'expired' => true];
        }

        if (! hash_equals((string) $request->otp, (string) $enteredOtp)) {
            return ['success' => false, 'message' => 'Incorrect OTP. Please try again.'];
        }

        return $this->approveRequest($request);
    }

    /**
     * Patient approves a pending access request directly (button click).
     */
    public function patientApprove(DoctorAccessRequest $request): array
    {
        if (! $request->isPending()) {
            return ['success' => false, 'message' => 'Request is not in a pending state.'];
        }

        return $this->approveRequest($request);
    }

    /**
     * Patient denies an access request.
     */
    public function patientDeny(DoctorAccessRequest $request): array
    {
        $request->update(['status' => 'denied']);

        // Notify doctor their request was denied
        $this->whatsAppService->sendAccessDenied(
            doctor: $request->doctor,
            patientName: $request->patient->profile->full_name,
        );

        return ['success' => true, 'message' => 'Access request denied.'];
    }

    /**
     * Revoke an active access grant (patient can do this anytime).
     */
    public function revokeAccess(User $patient, int $doctorId): bool
    {
        return (bool) DoctorAccessRequest::where('doctor_user_id', $doctorId)
            ->where('patient_user_id', $patient->id)
            ->where('status', 'approved')
            ->update([
                'status'            => 'expired',
                'access_expires_at' => now(),
            ]);
    }

    // ─── Permission Management ───────────────────────────────────────────────

    /**
     * Update a patient's global access type (full / otp_required).
     */
    public function updateAccessType(User $patient, string $accessType, ?int $familyMemberId = null): PatientAccessPermission
    {
        return PatientAccessPermission::updateOrCreate(
            [
                'patient_user_id'  => $patient->id,
                'family_member_id' => $familyMemberId,
            ],
            ['access_type' => $accessType]
        );
    }

    /**
     * Get all pending access requests for a patient (to show in their dashboard).
     */
    public function pendingRequestsForPatient(User $patient)
    {
        return DoctorAccessRequest::with(['doctor.profile', 'doctor.doctorProfile'])
            ->where('patient_user_id', $patient->id)
            ->where('status', 'pending')
            ->where('otp_expires_at', '>', now())
            ->latest()
            ->get();
    }

    /**
     * Get all active access grants given to doctors by a patient.
     */
    public function activeGrantsForPatient(User $patient)
    {
        return DoctorAccessRequest::with(['doctor.profile', 'doctor.doctorProfile'])
            ->where('patient_user_id', $patient->id)
            ->active()
            ->latest()
            ->get();
    }

    /**
     * Check whether a doctor has any active access grant for a patient.
     */
    public function doctorHasAccess(User $doctor, int $patientId): bool
    {
        // Check full access permission
        $permission = PatientAccessPermission::where('patient_user_id', $patientId)
            ->whereNull('family_member_id')
            ->first();

        if ($permission?->isFullAccess()) {
            return true;
        }

        // Check active approved request
        return DoctorAccessRequest::where('doctor_user_id', $doctor->id)
            ->where('patient_user_id', $patientId)
            ->active()
            ->exists();
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function approveRequest(DoctorAccessRequest $request): array
    {
        $sessionHours = config('medtech.access.session_hours', 8);
        $expiresAt    = now()->addHours($sessionHours);

        $request->update([
            'status'            => 'approved',
            'approved_at'       => now(),
            'access_expires_at' => $expiresAt,
            'otp'               => null,
            'otp_expires_at'    => null,
        ]);

        return [
            'status'           => 'approved',
            'success'          => true,
            'message'          => 'Access granted. Valid for ' . $sessionHours . ' hours.',
            'request'          => $request->fresh(),
            'access_expires_at'=> $expiresAt,
        ];
    }

    private function generateAccessOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function findByMobile(string $mobile): array
    {
        $mobile = trim($mobile);
        // Strip country code only when explicitly prefixed with +, e.g. +919876543210 → 9876543210
        if (str_starts_with($mobile, '+')) {
            $mobile = preg_replace('/^\+\d{1,3}/', '', $mobile);
        } elseif (strlen($mobile) > 10) {
            // e.g. "919876543210" → take last 10 digits
            $mobile = substr($mobile, -10);
        }

        $user = User::where('mobile_number', $mobile)
            ->where('role', 'patient')
            ->first();

        return [
            'found'  => (bool) $user,
            'user'   => $user,
            'member' => null,
        ];
    }

    private function findByAadhaar(string $aadhaar): array
    {
        // Search in user profiles — Aadhaar is encrypted, so we have to
        // load and decrypt each one (for now; implement a hashed index for scale)
        $profiles = \App\Models\UserProfile::all();

        foreach ($profiles as $profile) {
            try {
                if ($profile->aadhaar_number === $aadhaar) {
                    $user = $profile->user;
                    if ($user->isPatient()) {
                        return ['found' => true, 'user' => $user, 'member' => null];
                    }
                }
            } catch (\Throwable) {
                // Decryption failed for this row — skip
            }
        }

        // Also search in family members
        $members = FamilyMember::all();
        foreach ($members as $member) {
            try {
                if ($member->aadhaar_number === $aadhaar) {
                    return [
                        'found'  => true,
                        'user'   => $member->primaryUser,
                        'member' => $member,
                    ];
                }
            } catch (\Throwable) {}
        }

        return ['found' => false, 'user' => null, 'member' => null];
    }

    private function findBySubId(string $subId): array
    {
        $member = FamilyMember::where('sub_id', strtoupper(trim($subId)))->first();

        if (! $member) {
            return ['found' => false, 'user' => null, 'member' => null];
        }

        return [
            'found'  => true,
            'user'   => $member->primaryUser,
            'member' => $member,
        ];
    }
}
