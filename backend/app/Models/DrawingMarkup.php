<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawingMarkup extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'drawing_id', 'drawing_revision_id', 'author_id', 'markup_type',
        'x', 'y', 'width', 'height', 'comment', 'status', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'decimal:4',
            'y' => 'decimal:4',
            'width' => 'decimal:4',
            'height' => 'decimal:4',
            'resolved_at' => 'datetime',
        ];
    }

    public function drawing(): BelongsTo
    {
        return $this->belongsTo(Drawing::class);
    }
}
