<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationTemplate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'name', 'module', 'category', 'description',
        'workflow_definition', 'conditions', 'actions', 'approval_config',
        'schedule_config', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'workflow_definition' => 'array',
            'conditions' => 'array',
            'actions' => 'array',
            'approval_config' => 'array',
            'schedule_config' => 'array',
            'is_system' => 'boolean',
        ];
    }
}
