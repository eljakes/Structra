<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetLine extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'project_id',
        'cost_code',
        'description',
        'category',
        'budget_amount',
        'committed_amount',
        'actual_amount',
        'forecast_amount',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'committed_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'forecast_amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
