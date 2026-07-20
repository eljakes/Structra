<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetricSnapshot extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'snapshot_number', 'period_label', 'snapshot_date',
        'metrics', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'metrics' => 'array',
        ];
    }
}
