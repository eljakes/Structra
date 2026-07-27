<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceTaxRule extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'tax_name', 'tax_type', 'rate', 'applies_to', 'is_active',
        'effective_from', 'effective_to', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:3',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'metadata' => 'array',
        ];
    }
}
