<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforceExitRecord extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'employee_profile_id', 'exit_number', 'exit_type',
        'notice_date', 'exit_date', 'reason', 'clearance_items',
        'clearance_status', 'status',
    ];

    protected function casts(): array
    {
        return [
            'notice_date' => 'date',
            'exit_date' => 'date',
            'clearance_items' => 'array',
        ];
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }
}
