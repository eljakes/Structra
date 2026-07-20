<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'client_id', 'lead_id', 'assigned_to', 'opportunity_number',
        'name', 'stage', 'scope', 'probability', 'estimated_value', 'currency',
        'expected_close_date', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'expected_close_date' => 'date',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class);
    }
}
