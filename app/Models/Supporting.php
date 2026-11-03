<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// ─── Payment ──────────────────────────────────────────────────────────────────

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'appointment_id', 'razorpay_order_id',
        'razorpay_payment_id', 'razorpay_signature',
        'payment_method', 'amount', 'currency', 'status',
        'purpose', 'notes', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'  => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user()        { return $this->belongsTo(User::class); }
    public function appointment() { return $this->belongsTo(Appointment::class); }

    public function isPaid(): bool { return $this->status === 'paid'; }

    public function scopePaid($query)    { return $query->where('status', 'paid'); }
    public function scopePending($query) { return $query->where('status', 'created'); }
}


// ─── DoctorSubscription ───────────────────────────────────────────────────────

class DoctorSubscription extends Model
{
    protected $fillable = [
        'doctor_user_id', 'payment_id', 'plan', 'amount_paid',
        'starts_at', 'expires_at', 'is_active', 'features_unlocked',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'         => 'datetime',
            'expires_at'        => 'datetime',
            'is_active'         => 'boolean',
            'amount_paid'       => 'decimal:2',
            'features_unlocked' => 'array',
        ];
    }

    public function doctor()  { return $this->belongsTo(User::class, 'doctor_user_id'); }
    public function payment() { return $this->belongsTo(Payment::class); }

    public function isExpired(): bool { return now()->isAfter($this->expires_at); }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('expires_at', '>', now());
    }
}


// ─── HealthLog ────────────────────────────────────────────────────────────────

class HealthLog extends Model
{
    protected $fillable = [
        'patient_user_id', 'family_member_id', 'log_type',
        'value_1', 'value_2', 'unit', 'context', 'notes', 'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'value_1'   => 'decimal:2',
            'value_2'   => 'decimal:2',
            'logged_at' => 'datetime',
        ];
    }

    public function patient()      { return $this->belongsTo(User::class, 'patient_user_id'); }
    public function familyMember() { return $this->belongsTo(FamilyMember::class); }

    /** Formatted reading, e.g. "120/80 mmHg" for BP */
    public function getFormattedValueAttribute(): string
    {
        if ($this->log_type === 'bp' && $this->value_2) {
            return "{$this->value_1}/{$this->value_2} {$this->unit}";
        }
        return "{$this->value_1} {$this->unit}";
    }

    public function scopeOfType($query, string $type) { return $query->where('log_type', $type); }
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('logged_at', '>=', now()->subDays($days));
    }
}


// ─── MedicationReminder ───────────────────────────────────────────────────────

class MedicationReminder extends Model
{
    protected $fillable = [
        'patient_user_id', 'family_member_id', 'prescription_id',
        'medicine_name', 'dosage', 'reminder_times',
        'start_date', 'end_date', 'is_active', 'channel',
        'prescription_medicine_id', 'interval_days', 'schedule_note', 'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'reminder_times' => 'array',
            'last_notified_at' => 'datetime',
            'interval_days' => 'integer',
            'start_date'     => 'date',
            'end_date'       => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function patient()      { return $this->belongsTo(User::class, 'patient_user_id'); }
    public function familyMember() { return $this->belongsTo(FamilyMember::class); }
    public function prescription() { return $this->belongsTo(Prescription::class); }
    public function prescriptionMedicine() { return $this->belongsTo(PrescriptionMedicine::class); }

    public function isActive(): bool
    {
        return $this->is_active
            && !$this->schedule_note
            && (!$this->start_date || $this->start_date->lte(today()))
            && (!$this->end_date || $this->end_date->gte(today()))
            && (!$this->prescription_id || $this->prescription?->status === 'issued');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->whereNull('schedule_note')
                     ->where(fn($q) => $q->whereNull('prescription_id')
                         ->orWhereHas('prescription', fn($rx) => $rx->where('status', 'issued')))
                     ->where(fn($q) => $q->whereNull('end_date')
                                         ->orWhere('end_date', '>=', today()));
    }
}


// ─── Notification ─────────────────────────────────────────────────────────────

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'body',
        'data', 'channel', 'is_read', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true, 'read_at' => now()]);
    }

    public function scopeUnread($query) { return $query->where('is_read', false); }
    public function scopeForUser($query, int $userId) { return $query->where('user_id', $userId); }
}
