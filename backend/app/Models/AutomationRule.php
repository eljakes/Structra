<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutomationRule extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'description', 'module', 'status', 'version',
        'rule_type', 'trigger_event', 'conditions', 'actions',
        'workflow_definition', 'schedule_config', 'approval_config',
        'notification_config', 'settings', 'execution_mode',
        'severity', 'is_active', 'last_run_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'workflow_definition' => 'array',
            'schedule_config' => 'array',
            'approval_config' => 'array',
            'notification_config' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AutomationRuleVersion::class);
    }
}
