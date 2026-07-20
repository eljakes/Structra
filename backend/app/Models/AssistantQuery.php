<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantQuery extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'intent', 'question', 'answer', 'filters',
        'data_sources', 'result_payload', 'confidence_score', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'data_sources' => 'array',
            'result_payload' => 'array',
            'answered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
