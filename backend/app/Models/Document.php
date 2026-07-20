<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'project_id',
        'uploaded_by',
        'document_number',
        'title',
        'document_type',
        'repository_scope',
        'folder',
        'status',
        'version',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'tags',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
