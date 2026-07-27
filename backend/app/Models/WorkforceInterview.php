<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforceInterview extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'application_id', 'interview_number', 'scheduled_at',
        'stage', 'interviewers', 'result', 'score', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'interviewers' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(WorkforceApplication::class, 'application_id');
    }
}
