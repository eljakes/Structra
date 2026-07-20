<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inspection extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'project_id', 'inspection_number', 'type',
        'area', 'status', 'scheduled_on', 'completed_at', 'inspector_id',
        'score', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class);
    }

    public function ncrs(): HasMany
    {
        return $this->hasMany(NonConformanceReport::class);
    }
}
