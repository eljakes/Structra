<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NonConformanceReport extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'project_id', 'inspection_id', 'ncr_number',
        'title', 'description', 'department', 'category', 'location',
        'contractor', 'subcontractor', 'reference_documents', 'evidence',
        'severity', 'status', 'root_cause', 'corrective_action',
        'preventive_action', 'verification_notes', 'due_date', 'raised_by',
        'assigned_to', 'closed_by', 'closed_at', 'verified_at', 'reopened_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_documents' => 'array',
            'evidence' => 'array',
            'due_date' => 'date',
            'closed_at' => 'datetime',
            'verified_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
