<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'equipment_asset_id', 'project_id', 'fuel_number', 'fuel_date',
        'quantity', 'unit', 'unit_cost', 'total_cost', 'meter_reading', 'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fuel_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'meter_reading' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class, 'equipment_asset_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
