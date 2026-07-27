<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeProfile extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'user_id', 'manager_id', 'current_project_id',
        'employee_number', 'employment_type', 'department', 'position', 'gender',
        'date_of_birth', 'nationality', 'marital_status', 'national_id',
        'tax_number', 'ssnit_number', 'base_salary', 'hourly_rate', 'allowances',
        'bonuses', 'deductions', 'currency', 'hire_date', 'status',
        'emergency_contact', 'bank_name', 'bank_account', 'skills', 'licenses',
        'medical_notes', 'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'allowances' => 'decimal:2',
            'bonuses' => 'decimal:2',
            'deductions' => 'decimal:2',
            'hire_date' => 'date',
            'date_of_birth' => 'date',
            'skills' => 'array',
            'licenses' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function currentProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'current_project_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(WorkforceAllocation::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(WorkforceTimesheet::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(WorkforceCertification::class);
    }
}
