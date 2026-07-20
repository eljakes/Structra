<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegrationConnector extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'provider', 'name', 'category', 'status', 'settings',
        'encrypted_credentials', 'last_tested_at', 'connected_at', 'last_error',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'encrypted_credentials' => 'encrypted:array',
            'last_tested_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }
}
