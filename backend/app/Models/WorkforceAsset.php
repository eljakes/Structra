<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforceAsset extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'employee_profile_id', 'equipment_asset_id', 'asset_number',
        'item_name', 'category', 'serial_number', 'assigned_on', 'return_due_on',
        'returned_on', 'status',
    ];

    protected function casts(): array
    {
        return [
            'assigned_on' => 'date',
            'return_due_on' => 'date',
            'returned_on' => 'date',
        ];
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function equipmentAsset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class);
    }
}
