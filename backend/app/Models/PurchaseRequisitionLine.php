<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'purchase_requisition_id',
        'supplier_id',
        'item_name',
        'description',
        'cost_code',
        'quantity',
        'unit',
        'estimated_unit_cost',
        'estimated_total',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'estimated_unit_cost' => 'decimal:2',
            'estimated_total' => 'decimal:2',
            'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }
}
