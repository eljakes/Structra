<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'base_currency', 'quote_currency', 'rate', 'rate_date',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'rate_date' => 'date',
        ];
    }
}
