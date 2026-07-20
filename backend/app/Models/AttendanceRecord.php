<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'branch_id', 'project_id', 'user_id', 'clock_in_at', 'clock_out_at',
        'clock_in_latitude', 'clock_in_longitude', 'clock_out_latitude', 'clock_out_longitude',
        'face_in_path', 'face_out_path', 'status', 'total_minutes', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'clock_in_latitude' => 'decimal:7',
            'clock_in_longitude' => 'decimal:7',
            'clock_out_latitude' => 'decimal:7',
            'clock_out_longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
