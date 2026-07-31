<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'in_app_enabled',
        'email_enabled',
        'email_from_name',
        'email_from_address',
        'reply_to_email',
        'minimum_email_severity',
        'digest_frequency',
        'default_channels',
        'module_preferences',
        'retry_policy',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'default_channels' => 'array',
            'module_preferences' => 'array',
            'retry_policy' => 'array',
        ];
    }
}
