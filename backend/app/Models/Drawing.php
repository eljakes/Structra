<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Drawing extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'project_id',
        'uploaded_by',
        'drawing_number',
        'title',
        'discipline',
        'status',
        'current_revision',
        'description',
        'tags',
        'linked_records',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'linked_records' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DrawingRevision::class);
    }

    public function markups(): HasMany
    {
        return $this->hasMany(DrawingMarkup::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DrawingReview::class);
    }
}
