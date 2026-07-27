<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkforceCandidate extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'candidate_number', 'full_name', 'email', 'phone', 'trade',
        'location', 'source', 'status', 'rating', 'notes',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(WorkforceApplication::class, 'candidate_id');
    }
}
