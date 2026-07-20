<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'client_id', 'assigned_to', 'lead_number', 'company_name',
        'contact_name', 'email', 'phone', 'source', 'stage', 'estimated_value', 'currency',
        'next_follow_up_at', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function opportunity(): HasOne
    {
        return $this->hasOne(Opportunity::class);
    }
}
