# Development reference

## Application layout

The active Laravel 12 application lives at the repository root (`app/`, `routes/`, `resources/`). A second tree exists under `medtech/`; do not mirror changes there unless deployment requirements explicitly call for it. Apache should serve root `public/`.

## Prescription medication reminders

Prescription medication reminder behavior, schedule defaults, migration, scheduler setup, and verification are documented in [MEDICATION_REMINDERS.md](MEDICATION_REMINDERS.md). New issued prescriptions create per-medicine courses atomically; alerts stop after each medicine's inclusive end date.

## Patient appointment booking selector

- Both `AppointmentController::showSlots` and `showSlotsForMember` exclude `relation = self` from the active family-member list. The booking selector already renders the account holder as "Myself", so the stored self profile must not appear again as a separate person. The self row and existing medical history remain intact.

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

## Walk-in visits from quick register

- `QuickRegisterController::startVisit` creates the appointment with status `confirmed`. The patient is already in the clinic, so there is no patient-confirmation step; the doctor is redirected to the appointment page with the completion actions available.
- The `appointments.status` enum has no `pending` value, but `resources/views/doctor/appointments/show.blade.php` tested for `pending`. That left `booked` appointments (including walk-ins) with no Confirm or Mark Complete action. The detail page now shows Confirm for `booked` and Mark Complete/Cancel for `booked` and `confirmed`, matching `doctor/appointments/index`.
- Mark Complete stays hidden while the slot is still in the future, so a scheduled visit cannot be completed early. Walk-ins use `slot_datetime = now()`, so their action row always includes Mark Complete.
- Walk-in appointments created before this change stay `booked` and now show both actions on the detail page. Mark Complete on an unpaid visit opens the existing payment popup (collect cash or complete anyway).
- `startVisit` is idempotent: it reuses the doctor's unfinished visit for that patient today (`booked`/`confirmed`, `slot_datetime` on today) and redirects to it instead of inserting another row. The patient row is locked inside a transaction so two parallel requests cannot both create an appointment. Repeated clicks, back-button resubmits and the success screen all converge on one visit.
- The success screen shows "Visit in progress — open appointment" instead of "Start Visit Now" while a visit is unfinished, and the submit button disables itself on click.
- Visits are deliberately created when the doctor starts the visit, not when a prescription is written. The appointment carries the fee/payment record, the day's schedule entry, and the ownership used by records, prescriptions and timelines. Many walk-ins end without a prescription (advice-only, procedure, record only), so deferring creation would lose those visits from payments and reporting, and double-clicking Save on the prescription form would create the same duplicate problem one step later.
- To start a second visit for the same patient on the same day, complete or cancel the first one. Historical duplicates already in the database are not removed automatically.
- The patient history header (`resources/views/doctor/patients/history.blade.php`) shows **Start Visit** next to New Rx / Add Record for patients the doctor can access. It posts to `doctor.quick-register.start-visit`, so it inherits the same idempotency, fee snapshot, payment status and access rules. While a visit is open today it becomes **Visit in Progress**, linking to that appointment.
- When the header is showing a family member, the Start Visit form submits `family_member_id`, so dependent walk-ins are recorded against that person. Open-visit detection is member-aware: a member's open visit does not block starting the account holder's visit, and vice versa.

## Appointment care timeline assignment

- The appointment details sidebar includes an inline care timeline form below the clinic card for every status. Its dropdown lists active system templates and the doctor's own active templates, with duration and milestone count. Doctors select a start date and optional notes, then submit through the existing premium-protected assignment endpoint and return to the appointment with a success message.
- The save endpoint verifies the submitted appointment belongs to the doctor and patient, and derives the family member from that appointment. An owned appointment authorizes assignment without a separate patient-access request (fixes the 403 on appointment submissions). Active system/owned templates and patient-scoped family membership are checked before assignment. Standalone assignments require unexpired approved patient access and still return to patient history. Premium middleware applies to both flows.
- The standalone assignment screen accepts `member` to preselect a family member and preserves submitted selection on validation errors.
- Appointment assignment submits with JSON and displays success or actionable failure guidance inline, preserving the form. Messages cover validation, unavailable templates/appointments, access, Premium (with a plans link), expired sessions, connection failures and unexpected server errors. Uncertain outcomes advise checking patient history before retrying to avoid duplicates. Standard form submission remains a fallback without JavaScript.

## Verification

The patient history page (`resources/views/doctor/patients/history.blade.php`) has a Back link above the patient card on every tab. It uses browser history to return to the previous page, with the patients list as fallback when no previous history entry exists or JavaScript is disabled.

The medical record form (`resources/views/doctor/records/create.blade.php`, used for `/doctor/records/create/{patient}` and record edits) uses the same Back link above the form, falling back to that patient's history page. The form's Cancel action still goes directly to patient history.

The prescription form (`resources/views/doctor/prescriptions/create.blade.php`, for example `/doctor/prescriptions/create?patient=16`) also has this Back link above the form. Its fallback is the patient's history page when `patient` is present, otherwise the prescriptions list. The action bar's Cancel still goes to the prescriptions list.

Run `php artisan test --filter=AppointmentPrescriptionHistoryTest` and `php artisan view:cache`. Manually open an owned appointment, check full-prescription links and pagination, and check account-holder/family-member separation and the empty state. Never put patient data or credentials in this document.
