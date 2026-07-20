<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToolboxTalk extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'project_id', 'talk_number', 'topic',
        'talk_date', 'presenter_id', 'attendee_count', 'summary',
        'hazards_discussed', 'status',
    ];

    protected function casts(): array
    {
        return [
            'talk_date' => 'date',
            'hazards_discussed' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
