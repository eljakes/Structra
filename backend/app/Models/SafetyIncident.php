<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SafetyIncident extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'project_id', 'incident_number', 'incident_type',
        'severity', 'status', 'occurred_at', 'location', 'injured_person',
        'description', 'immediate_action', 'root_cause', 'corrective_action',
        'reported_by', 'assigned_to', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
