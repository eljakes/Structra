<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'setting_value' => 'array',
        ];
    }
}
