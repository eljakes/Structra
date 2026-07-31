<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderRfi extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'tender_id', 'asked_by', 'responded_by', 'rfi_number', 'category',
        'question', 'submitted_to', 'submitted_at', 'response', 'status', 'due_at',
        'responded_at', 'related_drawing', 'related_boq_item', 'related_specification',
        'internal_impact', 'cost_impact', 'schedule_impact_days', 'supporting_documents',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'responded_at' => 'datetime',
            'cost_impact' => 'decimal:2',
            'supporting_documents' => 'array',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }
}
