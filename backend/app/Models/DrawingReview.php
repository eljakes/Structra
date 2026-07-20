<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawingReview extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'drawing_id', 'drawing_revision_id', 'reviewer_id', 'decision',
        'comments', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function drawing(): BelongsTo
    {
        return $this->belongsTo(Drawing::class);
    }
}
