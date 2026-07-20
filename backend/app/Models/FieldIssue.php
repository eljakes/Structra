<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldIssue extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'project_id', 'field_daily_report_id', 'reported_by',
        'assigned_to', 'title', 'description', 'category', 'severity', 'status',
        'gps_latitude', 'gps_longitude', 'photo_path', 'due_date', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'gps_latitude' => 'decimal:7',
            'gps_longitude' => 'decimal:7',
            'due_date' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
