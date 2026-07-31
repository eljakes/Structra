<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'tenant_key',
        'registration_number',
        'tax_id',
        'industry',
        'default_currency',
        'country',
        'city',
        'address',
        'phone',
        'email',
        'website',
        'base_timezone',
        'language',
        'date_format',
        'fiscal_year_start',
        'status',
        'trial_ends_at',
        'storage_limit_mb',
        'employee_limit',
        'project_limit',
        'branch_limit',
        'provisioned_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function featureFlags(): HasMany
    {
        return $this->hasMany(CompanyFeatureFlag::class);
    }

    public function brandingProfile(): HasOne
    {
        return $this->hasOne(CompanyBrandingProfile::class);
    }
}
