<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforceJobVacancy extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'project_id', 'vacancy_number', 'title',
        'department', 'employment_type', 'openings', 'priority', 'status',
        'description', 'required_skills', 'opened_on', 'closes_on', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'required_skills' => 'array',
            'opened_on' => 'date',
            'closes_on' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(WorkforceApplication::class, 'job_vacancy_id');
    }
}
