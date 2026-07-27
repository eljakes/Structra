<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceRetention extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'invoice_id', 'supplier_invoice_id',
        'retention_number', 'party_type', 'base_amount', 'retention_percent',
        'retention_amount', 'released_amount', 'balance_amount', 'status',
        'due_date', 'released_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'retention_amount' => 'decimal:2',
            'released_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'due_date' => 'date',
            'released_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }
}
