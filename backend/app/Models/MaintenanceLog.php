<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceLog extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'equipment_asset_id', 'maintenance_number', 'type', 'status',
        'service_date', 'completed_at', 'meter_reading', 'cost_amount', 'vendor',
        'description', 'next_service_due_on', 'performed_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'completed_at' => 'datetime',
            'meter_reading' => 'decimal:2',
            'cost_amount' => 'decimal:2',
            'next_service_due_on' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class, 'equipment_asset_id');
    }
}
