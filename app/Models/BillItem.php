<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id', 'head', 'label', 'quantity', 'unit_price', 'amount', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'integer',
            'unit_price' => 'decimal:2',
            'amount'     => 'decimal:2',
        ];
    }

    public function bill() { return $this->belongsTo(Bill::class); }

    public function getHeadLabelAttribute(): string
    {
        return config('medtech.billing_heads.' . $this->head . '.label', ucfirst(str_replace('_', ' ', $this->head)));
    }
}
