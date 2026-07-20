<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BiDashboard extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'slug', 'audience', 'refresh_interval', 'filters',
        'is_default', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(BiWidget::class);
    }
}
