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
        'company_id', 'tender_id', 'asked_by', 'responded_by', 'question', 'response',
        'status', 'due_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }
}
