<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforceTrainingRecord extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'employee_profile_id', 'training_course_id', 'status',
        'scheduled_on', 'completed_on', 'score', 'certificate_number',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'completed_on' => 'date',
        ];
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(WorkforceTrainingCourse::class, 'training_course_id');
    }
}
