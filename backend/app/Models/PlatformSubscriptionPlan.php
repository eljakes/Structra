<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformSubscriptionPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'status',
        'currency',
        'monthly_price',
        'yearly_price',
        'maximum_users',
        'maximum_projects',
        'maximum_storage_mb',
        'portal_users',
        'automation_limit',
        'ai_credits',
        'support_level',
        'api_access',
        'custom_branding',
        'sso_available',
        'modules',
        'features',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'api_access' => 'boolean',
            'custom_branding' => 'boolean',
            'sso_available' => 'boolean',
            'modules' => 'array',
            'features' => 'array',
            'settings' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }
}
