<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorService extends Model
{
    protected $fillable = [
        'doctor_user_id',
        'head',
        'name',
        'default_price',
        'default_quantity',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_price'    => 'decimal:2',
            'default_quantity' => 'integer',
            'is_active'        => 'boolean',
            'sort_order'       => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /* Label of the configured payment head (always exists after validation). */
    public function headLabel(): string
    {
        return (string) config("medtech.billing_heads.{$this->head}.label",
            ucfirst(str_replace('_', ' ', $this->head)));
    }
}
