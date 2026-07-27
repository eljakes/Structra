<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkforceSetting extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'setting_key', 'setting_value',
    ];

    protected function casts(): array
    {
        return [
            'setting_value' => 'array',
        ];
    }
}
