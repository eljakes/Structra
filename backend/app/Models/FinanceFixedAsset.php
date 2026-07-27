<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceFixedAsset extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'equipment_asset_id', 'branch_id', 'asset_number', 'name',
        'category', 'purchase_date', 'purchase_cost', 'current_value',
        'depreciation_method', 'useful_life_months', 'accumulated_depreciation',
        'status', 'disposal_date',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'current_value' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'disposal_date' => 'date',
        ];
    }

    public function equipmentAsset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
