<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'client_id',
        'code',
        'name',
        'description',
        'status',
        'health_status',
        'risk_level',
        'site_address',
        'country',
        'currency',
        'contract_value',
        'budget_total',
        'committed_total',
        'actual_cost',
        'forecast_to_complete',
        'progress_percent',
        'start_date',
        'target_end_date',
        'actual_end_date',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'budget_total' => 'decimal:2',
            'committed_total' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'forecast_to_complete' => 'decimal:2',
            'start_date' => 'date',
            'target_end_date' => 'date',
            'actual_end_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function drawings(): HasMany
    {
        return $this->hasMany(Drawing::class);
    }
}
