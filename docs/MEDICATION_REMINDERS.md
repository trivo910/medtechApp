# Prescription medication reminders

## Behavior

Issuing a new prescription creates one medication entry per prescription medicine in the same database transaction. A unique prescription-medicine key prevents duplicate creation. Existing prescriptions are not backfilled. Manual reminders remain available.

The prescription date is day one. Each medicine ends on `prescribed_date + duration_days - 1`, inclusive, in the application timezone (`APP_TIMEZONE`, default Asia/Kolkata). One-day courses are valid. Future courses appear with their start date but cannot send early. Expired courses appear under Inactive / Completed and are excluded from the dashboard. Cancellation, soft deletion, pause, or removal stops subsequent alerts. Reissuing a separate prescription represents a separate course; medicines are not merged by name.

The patient account receives alerts for its own prescriptions and those of its family members. Family names are included in alerts. No name matching or merging of stored self/family medical records occurs.

## Suggested notification times

| Frequency | Times | Recurrence |
| --- | --- | --- |
| Once daily / OD | 08:00 (22:00 with bedtime instruction) | Daily |
| Twice daily / BD / 1-0-1 | 08:00, 20:00 | Daily |
| Three times daily / TDS / 1-1-1 | 08:00, 14:00, 20:00 | Daily |
| Four times daily | 08:00, 12:00, 16:00, 20:00 | Daily |
| Every 6 hours | 00:00, 06:00, 12:00, 18:00 | Daily |
| Every 8 hours | 00:00, 08:00, 16:00 | Daily |
| Every 12 hours | 08:00, 20:00 | Daily |
| Once weekly | 08:00 | Every seven days from prescription date |
| At bedtime | 22:00 | Daily |

These are editable notification defaults. Dosage, food instructions, and special instructions remain visible and authoritative. Changing alert times preserves the prescribed daily alert count; hourly schedules must retain their spacing, including across midnight. Patients cannot change a generated course's dosage or end date via reminder updates.

As-needed medicines have no repeating alerts. Missing duration or unsupported/missing frequency creates an inactive entry with an explanation; these cannot be activated until the doctor clarifies the prescription. No indefinite course is inferred. Paused courses retain their original end date.

## Delivery and operations

Automatic prescription alerts use **in-app notifications**, visible in the existing notification bell. They do not automatically send SMS or WhatsApp. Existing manual reminder channels and older WhatsApp jobs are separate.

Apply the additive migration:

```sh
php artisan migrate --path=database/migrations/2026_11_02_000001_add_prescribed_medication_schedules.php
```

Run Laravel's scheduler continuously (`php artisan schedule:work` locally), or configure the host to execute `php artisan schedule:run` every minute. The named schedule `prescription-medication-reminders` checks current-minute alerts. Each reminder is locked and its notification and last-delivery minute are saved atomically, preventing duplicate alerts on retries in that minute. A stopped scheduler does not replay missed doses or send catch-up alerts. No queue worker is needed for these in-app alerts.

No background process is installed by this change. Deployment must keep the scheduler running. Test via `php artisan test --filter=PrescriptionMedicationReminderTest` and `php artisan view:cache`.

## Implementation

- `PrescriptionController::store` creates courses after saving medicine rows and before commit.
- `PrescriptionReminderService` maps frequencies, creates courses idempotently, and processes due alerts.
- `MedicationReminder` owns inclusive date and issued-prescription checks.
- `MedicationReminderController` preserves doctor instructions on generated reminders, and exposes active, completed, paused, and review entries.
- `bootstrap/app.php` registers the every-minute schedule.
