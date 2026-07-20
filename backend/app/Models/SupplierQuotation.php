<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierQuotation extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'procurement_rfq_id', 'purchase_requisition_id', 'supplier_id',
        'quotation_number', 'supplier_reference', 'status', 'quote_date', 'valid_until',
        'currency', 'subtotal_amount', 'tax_amount', 'discount_amount', 'total_amount',
        'lead_time_days', 'payment_terms', 'warranty_included', 'recommendation_score',
        'notes', 'submitted_by', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'accepted_at' => 'datetime',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'warranty_included' => 'boolean',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfq::class, 'procurement_rfq_id');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierQuotationLine::class);
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }
}
