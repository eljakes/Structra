<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentAssignment extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'equipment_asset_id', 'project_id', 'assigned_to',
        'assignment_number', 'status', 'starts_at', 'ends_at', 'meter_start',
        'meter_end', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'meter_start' => 'decimal:2',
            'meter_end' => 'decimal:2',
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
