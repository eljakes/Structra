<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimateLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'estimate_id', 'pricing_item_id', 'cost_code', 'description', 'category',
        'quantity', 'unit', 'unit_cost', 'markup_percent', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }
}
