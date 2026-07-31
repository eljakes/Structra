<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderDocument extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'tender_id', 'document_id', 'uploaded_by', 'title', 'document_type',
        'version', 'status', 'is_mandatory', 'is_confidential', 'expires_at',
        'file_path', 'original_filename', 'mime_type', 'size_bytes', 'comments',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_confidential' => 'boolean',
            'expires_at' => 'date',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }
}
