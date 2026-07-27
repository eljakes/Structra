<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforceTrainingCourse extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'course_code', 'title', 'category', 'provider',
        'duration_hours', 'status',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'decimal:2',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(WorkforceTrainingRecord::class, 'training_course_id');
    }
}
