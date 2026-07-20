<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPerformanceReview extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'supplier_id', 'project_id', 'reviewed_by', 'rating', 'quality_score',
        'delivery_score', 'cost_score', 'notes', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
