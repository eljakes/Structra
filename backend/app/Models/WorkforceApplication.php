<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforceApplication extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'job_vacancy_id', 'candidate_id', 'hired_employee_profile_id',
        'application_number', 'status', 'applied_on', 'expected_salary',
        'screening_score', 'background_check_status', 'offer_status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'applied_on' => 'date',
            'expected_salary' => 'decimal:2',
        ];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(WorkforceJobVacancy::class, 'job_vacancy_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(WorkforceCandidate::class, 'candidate_id');
    }

    public function hiredEmployee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'hired_employee_profile_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(WorkforceInterview::class, 'application_id');
    }
}
