<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'payroll_run_id', 'employee_profile_id', 'user_id', 'gross_pay',
        'overtime_pay', 'allowances', 'deductions', 'tax_amount', 'net_pay',
        'status', 'paid_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gross_pay' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'allowances' => 'decimal:2',
            'deductions' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
