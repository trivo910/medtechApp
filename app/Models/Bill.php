<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bill_number', 'appointment_id', 'prescription_id',
        'doctor_user_id', 'patient_user_id', 'clinic_id',
        'subtotal', 'discount', 'total', 'amount_paid', 'balance',
        'status', 'notes', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'    => 'decimal:2',
            'discount'    => 'decimal:2',
            'total'       => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance'     => 'decimal:2',
            'paid_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($bill) {
            if (empty($bill->bill_number)) {
                $bill->bill_number = 'BILL-' . date('Y') . '-' .
                    str_pad(static::withTrashed()->count() + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function appointment()  { return $this->belongsTo(Appointment::class); }
    public function prescription() { return $this->belongsTo(Prescription::class); }
    public function doctor()       { return $this->belongsTo(User::class, 'doctor_user_id'); }
    public function patient()      { return $this->belongsTo(User::class, 'patient_user_id'); }

    public function items()
    {
        return $this->hasMany(BillItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function paidPayments()
    {
        return $this->payments()->where('status', 'paid');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isWaived(): bool
    {
        return $this->status === 'waived';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['draft', 'unpaid', 'partial'], true);
    }

    public function scopeForDoctor($query, int $doctorId)
    {
        return $query->where('doctor_user_id', $doctorId);
    }
}
