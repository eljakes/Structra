<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalizationCountry extends Model
{
    use HasFactory;

    protected $fillable = [
        'iso2', 'name', 'currency', 'timezone', 'tax_label', 'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
