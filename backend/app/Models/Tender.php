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
        'tender_manager_id', 'business_development_officer_id', 'title', 'tender_type',
        'procurement_method', 'project_sector', 'project_category', 'project_location',
        'status', 'deadline_at', 'submitted_at', 'won_at', 'expected_award_at', 'lost_reason',
        'value', 'tender_fee', 'currency', 'description', 'scope_summary', 'funding_source',
        'tender_authority', 'priority', 'confidentiality_level', 'bid_decision',
        'bid_decision_score', 'checklist', 'deadline_schedule', 'settings', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'won_at' => 'datetime',
            'expected_award_at' => 'datetime',
            'value' => 'decimal:2',
            'tender_fee' => 'decimal:2',
            'bid_decision_score' => 'integer',
            'checklist' => 'array',
            'deadline_schedule' => 'array',
            'settings' => 'array',
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

    public function records(): HasMany
    {
        return $this->hasMany(TenderRecord::class);
    }
}
