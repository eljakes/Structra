<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformFeatureFlag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'module',
        'category',
        'description',
        'default_enabled',
        'rollout_status',
        'rollout_percentage',
        'pricing_tier',
        'requires_subscription',
        'configuration',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'default_enabled' => 'boolean',
            'requires_subscription' => 'boolean',
            'configuration' => 'array',
        ];
    }

    public function companyFlags(): HasMany
    {
        return $this->hasMany(CompanyFeatureFlag::class);
    }
}
