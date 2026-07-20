<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawingRevision extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'drawing_id',
        'uploaded_by',
        'revision_code',
        'status',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'notes',
        'issued_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function drawing(): BelongsTo
    {
        return $this->belongsTo(Drawing::class);
    }
}
