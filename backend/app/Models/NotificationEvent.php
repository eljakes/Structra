<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationEvent extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'user_id',
        'automation_rule_id',
        'notification_number',
        'source_key',
        'source_type',
        'source_id',
        'module',
        'event_type',
        'title',
        'message',
        'severity',
        'status',
        'channels',
        'delivery_status',
        'recipient_name',
        'recipient_email',
        'email_subject',
        'email_sent_at',
        'email_error',
        'metadata',
        'read_at',
        'acknowledged_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'delivery_status' => 'array',
            'metadata' => 'array',
            'email_sent_at' => 'datetime',
            'read_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function automationRule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class);
    }
}
