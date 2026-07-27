<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleVersion extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'automation_rule_id', 'version', 'snapshot',
        'changed_by', 'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
