# Billing & Payment Heads

Status: implemented (Phase 0–3 + printable receipt + service catalogue + revenue-by-head)
Last updated: 4 Nov 2026

This document describes how a doctor completes a visit, records payment, and
splits the amount across payment heads (OPD fee, medicines, vaccination, lab
tests, ultrasound, IVF, stem cell, other).

Read this before changing appointment completion, prescription saving, or
anything that touches `payments`.

---

## 1. Mental model

| Concept | Table | Meaning |
|---|---|---|
| Visit | `appointments` | The clinical encounter. Has `fee` (consultation fee snapshot) and `payment_status`. |
| Bill | `bills` | What the patient was charged for this visit. One bill per appointment. |
| Bill lines | `bill_items` | The payment heads: OPD fee, medicines, lab tests, etc. |
| Payment | `payments` | Money actually received (cash, UPI, card, Razorpay). One bill can have several payments. |

The bill is the invoice; a payment is the money movement. This separation is
what makes partial payments, several heads, and the Razorpay flow all work.

```
appointments ──1:1── bills ──1:N── bill_items   (heads)
                       └────1:N── payments      (cash / upi_qr / razorpay / other)
```

`payments.bill_id` is the link. `payments.appointment_id` is kept for backward
compatibility with the original online-payment flow.

---

## 2. Payment heads

Heads live in `config/medtech.php` under `billing_heads`, not in a database
enum. Adding "Physiotherapy" later is a config change, not a migration.

```php
'billing_heads' => [
    'opd_fee'     => ['label' => 'OPD Fee'],
    'medicines'   => ['label' => 'Medicines'],
    'vaccination' => ['label' => 'Vaccination'],
    'lab_tests'   => ['label' => 'Lab Tests'],
    'ultrasound'  => ['label' => 'Ultrasound'],
    'ivf'         => ['label' => 'IVF'],
    'stem_cell'   => ['label' => 'Stem Cell'],
    'other'       => ['label' => 'Other'],
],
```

Each line also has a free-text `label` (e.g. "CBC + LFT", "MMR dose 2") and
`quantity` / `unit_price`. The same head can appear more than once.

The head key is stored as `varchar(30)` in `bill_items.head`, validated against
the config array on the server.

---

## 3. Bill lifecycle

| Status | When |
|---|---|
| `draft` | Lines exist, nothing paid yet, visit still open |
| `partial` | Some money received, balance > 0 |
| `paid` | `amount_paid >= total` |
| `waived` | Doctor completed the visit without charging |
| `refunded` | Reserved for a future refund flow |

`bills.total = subtotal - discount`. `amount_paid` is the sum of `payments`
with `status = paid`. `balance = total - amount_paid`.

When the balance reaches zero the appointment is marked
`payment_status = paid`; a partial payment leaves it `pending`.

---

## 4. Doctor UX

### Appointment page

The "Mark Complete" button no longer opens a modal. It scrolls to the
**Complete Visit & Payment** panel rendered on the page. The panel contains:

- one editable row per charge (head, label, qty, rate, amount, remove),
- **+ Add payment head** dropdown driven by the config array,
- discount (₹ or %),
- totals,
- payment method (Cash / UPI QR / Card / Other),
- amount received, reference, note,
- **Collect & complete** and **Complete without payment**.

If the visit is already paid the panel becomes a read-only receipt with a
print link.

The editable panel also shows **+ Add saved service** when the doctor has
services in their catalogue. Choosing one adds a line pre-filled with the
service name, payment head, default price and default quantity — everything
stays editable before collecting. A *Manage services* link opens
`GET /doctor/services`.

### Prescription page

The same panel is included at the bottom of the prescription detail page.
The prescription must be linked to an appointment. The "New Prescription"
form from a visit already carries `appointment_id`.

The create form has an extra button, **Save & Complete with Payment**, shown
only when the form was opened from an appointment. It:

1. saves the prescription (same validation as Save),
2. links it to the appointment (`prescriptions.appointment_id`),
3. redirects to the prescription page with the billing panel focused.

If a prescription is not linked to a visit, the detail page shows a
**Start visit & bill** action that creates (or reuses today's) walk-in
appointment for that doctor + patient + family member and links it.

### Receipt

`GET /doctor/bills/{bill}/receipt` renders a print-ready receipt (browser
print / save as PDF). It lists every head, discount, total, payments, and the
outstanding balance.

The patient-side payment receipt
(`GET /patient/payments/{payment}/receipt`) uses the same standalone
invoice layout — clinic header, "Received from" the paying account, doctor
block, charges table (bill lines when the payment has a `bill_id`, otherwise
a single consultation line), totals and the payment row. It does not render
inside the patient app layout, so print/PDF output is the document only.

The patient's payments page (`GET /patient/payments`) follows the same
model: payment-history rows print a head-level charges summary of the
linked bill (e.g. "OPD Fee ₹500 · Lab Tests ₹500") plus the outstanding
balance when the bill is still open, pending-payment cards list the visit's
charged heads with paid/balance context, and visits whose bill was waived
never offer "Pay Now" (there is nothing due). The checkout still pays the
appointment fee — paying the full bill balance from the patient side
remains future work.

---

## 5. Server flow

`App\Services\BillService` owns all money math:

| Method | Purpose |
|---|---|
| `forAppointment()` | Find or create the bill for a visit, pre-filled with the OPD fee line |
| `syncItems()` | Validate heads and replace the bill lines |
| `collect()` | Save lines + discount, record a payment, update totals, complete the visit |
| `waive()` | Save lines, mark the bill waived, complete with a required note |
| `totals()` | Recompute subtotal / discount / total / paid / balance |

Endpoints (doctor middleware):

```
POST /doctor/appointments/{appointment}/billing/collect
POST /doctor/appointments/{appointment}/billing/waive
GET  /doctor/bills/{bill}/receipt
POST /doctor/prescriptions/{prescription}/start-visit
```

Service catalogue endpoints (doctor middleware):

```
GET    /doctor/services
POST   /doctor/services
PUT    /doctor/services/{service}
DELETE /doctor/services/{service}
```

All of them verify that the doctor owns the appointment/prescription
(`BillingController::gate()`, same rule as `AppointmentController::gate()`).

`collect()` runs in a transaction and locks the appointment row first, so a
double click cannot create two payments for the same balance.

Validation rules:

- at least one line when collecting,
- head must exist in `billing_heads`,
- `quantity` 1–999, `unit_price` 0–10,000,000,
- discount cannot exceed subtotal,
- `amount_received` cannot exceed the outstanding balance,
- a note is required when completing without payment.

---

## 6. Edge cases

- **Walk-ins without a prescription** — the bill is created from the
  appointment page; a prescription is optional.
- **Two prescriptions in one visit** — the bill belongs to the visit, so the
  second prescription does not add a second OPD fee. `bills.prescription_id`
  records the latest prescription that triggered it.
- **Partial payment** — bill `partial`, appointment `pending`, balance can be
  collected later from the same panel.
- **Complete without payment** — bill `waived`, `appointments.completion_note`
  holds the reason, `payment_status` is left untouched.
- **Already paid** — collecting again with a zero amount is a no-op; trying to
  collect more than the balance returns 422.
- **Duplicate clicks** — the panel disables the button while submitting and the
  server lock prevents double payments.
- **Online Razorpay payment** — still writes a `payments` row with
  `bill_id = null` unless attached later; `amount_paid` only counts rows with
  `status = paid`, so the bill panel reflects online payments once attached.

---

## 7. Schema

### `bills`

`bill_number`, `appointment_id` (unique, nullable), `prescription_id`,
`doctor_user_id`, `patient_user_id`, `clinic_id`, `subtotal`, `discount`,
`total`, `amount_paid`, `balance`, `status`, `notes`, `paid_at`, timestamps,
soft deletes.

### `bill_items`

`bill_id`, `head`, `label`, `quantity`, `unit_price`, `amount`, `sort_order`,
timestamps.

### `payments` (changed)

New nullable `bill_id` foreign key.

### `prescriptions` (changed)

`appointment_id` and `clinic_id` were present in the production database but
missing from the migrations. A corrective migration adds them idempotently, and
`appointment_id` is now in `Prescription::$fillable` with an `appointment()`
relation. Before this fix the hidden `appointment_id` field was silently
dropped, so **no prescription was linked to a visit**.

### `doctor_services`

Per-doctor service catalogue: `doctor_user_id`, `head` (validated against
`billing_heads`), `name`, `default_price`, `default_quantity`, `is_active`,
`sort_order`. Services only pre-fill editable lines in the billing panel;
they never touch money math themselves. `ServiceCatalogController` keeps
entries unique per doctor + name + head (`updateOrCreate`).

---

## 8. Service catalogue

Doctors bill the same charges repeatedly (CBC, dressing, ultrasound). The
service list saves each as **payment head + name + default price + default
quantity** and is offered as a **+ Add saved service** dropdown inside the
billing panel. Adding a line never changes the bill server-side until
Collect is pressed.

- UI: `GET /doctor/services` (`doctor.services.index`,
  `resources/views/doctor/services/index.blade.php`), grouped by head,
  with activate/deactivate, inline edit and delete.
- Navigation: "Service List" item in the doctor sidebar.
- Validation must accept only heads that exist in
  `config('medtech.billing_heads')` — adding a category is a config change,
  not a migration.
- The linked service row is not stored on the bill; `bill_items` keeps only
  the resulting head/label/qty/price data.

---

## 9. Revenue by payment head

The analytics dashboard shows a **Revenue by Payment Head** card
(`AnalyticsController::revenueByHead`, premium middleware). It aggregates
`bill_items.amount` per head over this doctor's bills.

Scope rules:

- Only bills that actually received money count — `status` in
  (`partial`, `paid`) **and** `amount_paid > 0`. Waived and draft bills
  are excluded.
- `billed` = `SUM(bill_items.amount)` — the raw line value.
- `collected` = pro-rata estimate: each bill's payments spread across its
  lines by `amount_paid / total`. Payments are not allocated to individual
  lines, so this apportionment is an estimate (labelled in the UI); a
  partially-paid bill's outstanding balance reduces it.
- Head labels resolve from `config('medtech.billing_heads')`.

The doctor's payments list (`GET /doctor/payments/all`) mirrors the same
model: each appointment row shows its linked bill (status badge, bill
number → receipt, head:charges summary), outstanding balances, and the KPI
strip distinguishes collected vs outstanding vs waived so appointment-fee
numbers (`payment_status`) are not confused with bill money. The
`GET /doctor/payments/all/export` endpoint downloads the (filtered) list as
a 3-sheet Excel workbook via `ExcelExportService::exportPayments` —
Payments (visits + bill summary), Charges (every bill line with head label
and particulars), and Revenue by Head (per-head billed/collected est./
outstanding est. over the same filtered set).

---

## Future work (not implemented)

- Receipt PDF generation and WhatsApp delivery (receipt HTML is printable now).
- Patient-facing bill page and "Pay balance" Razorpay order for the bill total.
- Backfill bills for historical `payments` rows (they remain bill-less).
- Refund flow from a paid bill.
