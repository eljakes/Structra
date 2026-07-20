<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalAccess extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'portal_user_id', 'project_id', 'access_level',
        'disciplines', 'expires_at', 'granted_by',
    ];

    protected function casts(): array
    {
        return [
            'disciplines' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
