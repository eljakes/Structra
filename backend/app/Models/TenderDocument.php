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
        'file_path', 'original_filename', 'mime_type', 'size_bytes',
    ];

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }
}
