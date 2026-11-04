<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'prescription_number', 'medical_record_id', 'appointment_id', 'clinic_id',
        'doctor_user_id', 'patient_user_id', 'family_member_id', 'prescribed_date',
        'diagnosis_summary', 'general_instructions', 'diet_advice',
        'follow_up_instructions', 'follow_up_date', 'pdf_path',
        'is_sent_whatsapp', 'whatsapp_sent_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'prescribed_date'    => 'date',
            'follow_up_date'     => 'date',
            'is_sent_whatsapp'   => 'boolean',
            'whatsapp_sent_at'   => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($prescription) {
            if (empty($prescription->prescription_number)) {
                $prescription->prescription_number = 'RX-' . date('Y') . '-' .
                    str_pad(static::withTrashed()->count() + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function medicines()
    {
        return $this->hasMany(PrescriptionMedicine::class)->orderBy('sort_order');
    }

    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function bill()
    {
        return $this->hasOne(Bill::class);
    }

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

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeIssued($query) { return $query->where('status', 'issued'); }

    public function scopeForAppointmentPatient($query, Appointment $appointment)
    {
        return $query->issued()
            ->where('doctor_user_id', $appointment->doctor_user_id)
            ->where('patient_user_id', $appointment->patient_user_id)
            ->where('family_member_id', $appointment->family_member_id)
            ->whereDate('prescribed_date', '<=', min(today()->toDateString(), $appointment->slot_datetime->toDateString()));
    }
}
