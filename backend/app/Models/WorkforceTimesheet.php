<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforceTimesheet extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'employee_profile_id', 'project_id', 'shift_id',
        'timesheet_number', 'work_date', 'hours_worked', 'overtime_hours',
        'cost_rate', 'cost_amount', 'status', 'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours_worked' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'cost_rate' => 'decimal:2',
            'cost_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkforceShift::class, 'shift_id');
    }
}
