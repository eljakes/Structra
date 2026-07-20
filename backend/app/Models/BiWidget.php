<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiWidget extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'bi_dashboard_id', 'title', 'widget_type', 'metric_key',
        'configuration', 'position',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
        ];
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(BiDashboard::class, 'bi_dashboard_id');
    }
}
