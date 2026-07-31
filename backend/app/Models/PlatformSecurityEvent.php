<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSecurityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'event_type',
        'severity',
        'status',
        'ip_address',
        'user_agent',
        'description',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
