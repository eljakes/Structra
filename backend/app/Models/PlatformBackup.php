<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformBackup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'backup_number',
        'backup_type',
        'status',
        'storage_path',
        'size_mb',
        'started_at',
        'completed_at',
        'verified_at',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size_mb' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
