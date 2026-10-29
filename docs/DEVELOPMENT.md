# Development reference

## Application layout

The active Laravel 12 application lives at the repository root (`app/`, `routes/`, `resources/`). A second tree exists under `medtech/`; do not mirror changes there unless deployment requirements explicitly call for it. Apache should serve root `public/`.

## Appointment prescription history

Implemented 2026-10-28 for `/doctor/appointments/{appointment}` (including `/doctor/appointments/29`).

- Route: `routes/doctor.php`; controller: `App\Http\Controllers\Doctor\AppointmentController::show`.
- The existing appointment ownership gate runs before loading any history.
- Query rules live in `Prescription::scopeForAppointmentPatient`: issued, non-deleted prescriptions from the appointment's doctor for its patient account and exact family member. A null family member means the account holder only.
- Dates are inclusive through the earlier of today and the appointment date. Same-day prescriptions are included because `prescribed_date` has date precision. Future prescriptions and prescriptions after a historical appointment date are excluded.
- Results sort by prescription date then ID descending, five per page. Pagination uses `prescriptions_page` and anchors back to `#previous-prescriptions`.
- `resources/views/doctor/appointments/show.blade.php` displays Patient, Details, appointment actions (including Write Prescription), then `_previous-prescriptions.blade.php`, in that order (updated 2026-10-29). The history section shows prescription number, date, diagnosis, medicines and an empty state, including for completed/cancelled appointments. Actions retain their existing status visibility rules.
- Full details reuse `doctor.prescriptions.show`. Its existing ownership rule permits the issuing doctor only; do not expose another doctor's prescriptions here without designing the corresponding access checks.
- The section's "View patient history" link uses `doctor.patients.history` with the appointment's patient ID and `tab=prescriptions` (for example `/doctor/patients/24/history?tab=prescriptions`). Family appointments also pass `member` to preserve the selected person. The history page retains its existing access checks. The link remains visible when there are no previous prescriptions.
- No migration or new endpoint is required. Medicines are eager-loaded to avoid per-prescription queries.
- The patient card's "View Patient" and family-member view buttons also pass `tab=prescriptions` in their URLs. The history view reads this parameter to open and highlight Prescriptions; family-member links retain `member`.

## Verification

The patient history page (`resources/views/doctor/patients/history.blade.php`) has a Back link above the patient card on every tab. It uses browser history to return to the previous page, with the patients list as fallback when no previous history entry exists or JavaScript is disabled.

Run `php artisan test --filter=AppointmentPrescriptionHistoryTest` and `php artisan view:cache`. Manually open an owned appointment, check full-prescription links and pagination, and check account-holder/family-member separation and the empty state. Never put patient data or credentials in this document.
