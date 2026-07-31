<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformDeployment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'deployment_number',
        'title',
        'release_version',
        'target_scope',
        'target_filter',
        'status',
        'scheduled_at',
        'deployed_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_filter' => 'array',
            'scheduled_at' => 'datetime',
            'deployed_at' => 'datetime',
        ];
    }
}
