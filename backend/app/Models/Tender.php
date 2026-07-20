<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tender extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'client_id', 'opportunity_id', 'project_id', 'tender_number',
        'title', 'status', 'deadline_at', 'submitted_at', 'won_at', 'lost_reason',
        'value', 'currency', 'checklist', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'won_at' => 'datetime',
            'value' => 'decimal:2',
            'checklist' => 'array',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TenderDocument::class);
    }

    public function rfis(): HasMany
    {
        return $this->hasMany(TenderRfi::class);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }
}
