<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceProgressBilling extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'invoice_id', 'milestone_number',
        'milestone_name', 'progress_percent', 'billable_amount', 'retention_percent',
        'status', 'due_date', 'certified_at', 'invoiced_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'decimal:2',
            'billable_amount' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'due_date' => 'date',
            'certified_at' => 'datetime',
            'invoiced_at' => 'datetime',
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
}
