<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'country', 'name', 'tax_type', 'rate_percent',
        'effective_from', 'effective_to', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:3',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_default' => 'boolean',
        ];
    }
}
