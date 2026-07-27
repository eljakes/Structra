<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforcePpeIssue extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'employee_profile_id', 'project_id', 'ppe_number',
        'item_name', 'size', 'quantity', 'issued_on', 'replacement_due_on',
        'condition', 'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'issued_on' => 'date',
            'replacement_due_on' => 'date',
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
}
