<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyFeatureFlag extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'platform_feature_flag_id',
        'is_enabled',
        'limit_value',
        'configuration',
        'enabled_at',
        'disabled_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'configuration' => 'array',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function flag(): BelongsTo
    {
        return $this->belongsTo(PlatformFeatureFlag::class, 'platform_feature_flag_id');
    }
}
