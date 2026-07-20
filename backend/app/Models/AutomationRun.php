<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'automation_rule_id', 'run_number', 'status', 'matched_count',
        'actions_executed', 'matched_records', 'action_results', 'error_message',
        'started_at', 'finished_at', 'run_by',
    ];

    protected function casts(): array
    {
        return [
            'matched_records' => 'array',
            'action_results' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
