<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'inspection_id', 'checklist_item', 'requirement', 'result',
        'severity', 'notes', 'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'corrected_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
}
