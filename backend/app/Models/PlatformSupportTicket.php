<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformSupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'ticket_number',
        'title',
        'category',
        'priority',
        'status',
        'assigned_to',
        'description',
        'resolution_notes',
        'sla_due_at',
        'closed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
