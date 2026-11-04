<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'appointment_number', 'doctor_user_id', 'patient_user_id',
        'family_member_id', 'slot_datetime', 'duration_minutes', 'type',
        'status', 'reason', 'cancellation_reason',
        'reminder_24h_sent', 'reminder_1h_sent', 'follow_up_reminder_sent',
        'fee', 'payment_status', 'payment_preference', 'completion_note',
        'rescheduled_from',
        'payment_requested_at', 'payment_requested_by',
    ];

    protected function casts(): array
    {
        return [
            'slot_datetime'            => 'datetime',
            'reminder_24h_sent'        => 'boolean',
            'reminder_1h_sent'         => 'boolean',
            'follow_up_reminder_sent'  => 'boolean',
            'fee'                      => 'decimal:2',
            'payment_requested_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($appointment) {
            if (empty($appointment->appointment_number)) {
                $appointment->appointment_number = 'APT-' . date('Y') . '-' .
                    str_pad(static::withTrashed()->count() + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isUpcoming(): bool
    {
        return in_array($this->status, ['booked', 'confirmed'])
            && $this->slot_datetime->isFuture();
    }

    public function isPast(): bool
    {
        return $this->slot_datetime->isPast();
    }

    public function hasPaymentRequest(): bool
    {
        return $this->payment_requested_at !== null;
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function familyMember()
    {
        return $this->belongsTo(FamilyMember::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function bill()
    {
        return $this->hasOne(Bill::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function rescheduledFrom()
    {
        return $this->belongsTo(Appointment::class, 'rescheduled_from');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'payment_requested_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', ['booked', 'confirmed'])
                     ->where('slot_datetime', '>', now());
    }

    public function scopeToday($query)
    {
        return $query->whereDate('slot_datetime', today());
    }

    public function scopeForDoctor($query, int $doctorId)
    {
        return $query->where('doctor_user_id', $doctorId);
    }

    /**
     * The doctor's unfinished visit for a patient today (optionally scoped to a
     * family member). Used to keep "Start Visit" idempotent.
     */
    public function scopeOpenVisitToday($query, int $doctorId, int $patientId, ?int $familyMemberId = null)
    {
        return $query->where('doctor_user_id', $doctorId)
                     ->where('patient_user_id', $patientId)
                     ->whereIn('status', ['booked', 'confirmed'])
                     ->whereDate('slot_datetime', today())
                     ->when(
                         $familyMemberId === null,
                         fn ($q) => $q->whereNull('family_member_id'),
                         fn ($q) => $q->where('family_member_id', $familyMemberId)
                     )
                     ->latest('id');
    }
}
