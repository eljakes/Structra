<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforceContractor extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'supplier_id', 'contractor_number', 'name', 'contact_name',
        'email', 'phone', 'trade', 'worker_count', 'contract_expires_on',
        'insurance_expires_on', 'compliance_status', 'status',
    ];

    protected function casts(): array
    {
        return [
            'contract_expires_on' => 'date',
            'insurance_expires_on' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
