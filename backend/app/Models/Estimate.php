<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estimate extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'tender_id', 'project_id', 'client_id', 'estimate_number',
        'title', 'status', 'scenario_name', 'currency', 'subtotal', 'overhead_percent',
        'profit_percent', 'tax_percent', 'overhead_amount', 'profit_amount', 'tax_amount',
        'total_amount', 'valid_until', 'prepared_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'overhead_percent' => 'decimal:2',
            'profit_percent' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'overhead_amount' => 'decimal:2',
            'profit_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'valid_until' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EstimateLine::class);
    }
}
