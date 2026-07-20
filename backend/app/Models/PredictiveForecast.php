<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PredictiveForecast extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'forecast_number', 'source_key', 'forecast_type',
        'period_label', 'baseline_value', 'forecast_value', 'variance_value',
        'confidence_score', 'drivers', 'status', 'generated_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'baseline_value' => 'decimal:2',
            'forecast_value' => 'decimal:2',
            'variance_value' => 'decimal:2',
            'drivers' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
