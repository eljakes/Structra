<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortalUser extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'user_type', 'name', 'email', 'phone',
        'organization', 'status', 'last_login_at', 'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(PortalAccess::class);
    }

    public function clientApprovals(): HasMany
    {
        return $this->hasMany(ClientApproval::class);
    }

    public function consultantSubmittals(): HasMany
    {
        return $this->hasMany(ConsultantSubmittal::class);
    }
}
