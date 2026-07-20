<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingItem extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'cost_code', 'description', 'category', 'unit',
        'unit_cost', 'currency', 'source', 'active',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
