<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyLocalizationSetting extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'base_country', 'base_currency', 'enabled_countries',
        'enabled_currencies', 'tax_rounding_mode', 'date_format',
    ];

    protected function casts(): array
    {
        return [
            'enabled_countries' => 'array',
            'enabled_currencies' => 'array',
        ];
    }
}
