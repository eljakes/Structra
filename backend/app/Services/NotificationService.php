<?php

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\IntegrationConnector;
use App\Models\NotificationEvent;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    private const SEVERITY_RANK = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4,
    ];

    public function settingsForCompany(int $companyId): NotificationSetting
    {
        return NotificationSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            $this->defaultSettings(),
        );
    }

    public function updateSettings(int $companyId, array $data, ?int $userId = null): NotificationSetting
    {
        $setting = $this->settingsForCompany($companyId);
        $setting->update([
            ...collect($data)->only([
                'in_app_enabled',
                'email_enabled',
                'email_from_name',
                'email_from_address',
                'reply_to_email',
                'minimum_email_severity',
                'digest_frequency',
                'default_channels',
                'module_preferences',
                'retry_policy',
            ])->all(),
            'updated_by' => $userId,
        ]);

        return $setting->fresh();
    }

    public function sendAutomationNotification(User $actor, AutomationRule $rule, array $record, array $action): array
    {
        $settings = $this->settingsForCompany($rule->company_id);
        $channels = $this->notificationChannels($action, $rule, $settings);
        $recipient = $this->recipient($actor, $action);
        $title = (string) ($action['subject'] ?? $action['title'] ?? $rule->name.': '.$record['label']);
        $message = (string) ($action['message'] ?? 'Automation workflow matched '.$record['label'].'.');
        $severity = (string) ($action['severity'] ?? $rule->severity ?? 'medium');
        $delivery = [];

        if (in_array('in_app', $channels, true)) {
            $delivery['in_app'] = $settings->in_app_enabled ? 'delivered' : 'disabled';
        }

        $event = NotificationEvent::query()->create([
            'company_id' => $rule->company_id,
            'user_id' => $recipient['user_id'],
            'automation_rule_id' => $rule->id,
            'notification_number' => $this->nextNotificationNumber($rule->company_id),
            'source_type' => $record['type'] ?? null,
            'source_id' => $record['id'] ?? null,
            'module' => $rule->module ?: 'general',
            'event_type' => $action['type'] ?? 'send_in_app_notification',
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'status' => ($settings->in_app_enabled && in_array('in_app', $channels, true)) ? 'unread' : 'sent',
            'channels' => $channels,
            'delivery_status' => $delivery,
            'recipient_name' => $recipient['name'],
            'recipient_email' => $recipient['email'],
            'email_subject' => in_array('email', $channels, true) ? $title : null,
            'metadata' => [
                'automation_rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'trigger_event' => $rule->trigger_event,
                ],
                'record' => $record,
                'action' => $action,
            ],
            'created_by' => $actor->id,
        ]);

        if (in_array('email', $channels, true)) {
            $emailResult = $this->sendEmail($settings, $event, $title, $message, $recipient['email'], $recipient['name'], $severity);
            $delivery = [...($event->delivery_status ?? []), 'email' => $emailResult['status']];
            $event->forceFill([
                'delivery_status' => $delivery,
                'email_sent_at' => $emailResult['sent_at'],
                'email_error' => $emailResult['error'],
                'status' => $emailResult['status'] === 'failed' ? 'failed' : $event->status,
            ])->save();
        }

        return [
            'type' => $action['type'] ?? 'send_in_app_notification',
            'status' => collect($event->fresh()->delivery_status ?? [])->contains('failed') ? 'failed' : 'executed',
            'notification_id' => $event->id,
            'notification_number' => $event->notification_number,
            'channels' => $event->channels,
            'delivery_status' => $event->fresh()->delivery_status,
            'record' => $record,
        ];
    }

    public function queueConnectorNotification(User $actor, AutomationRule $rule, array $record, array $action): array
    {
        $provider = match ($action['type'] ?? '') {
            'send_sms' => 'sms',
            'send_whatsapp' => 'whatsapp',
            'teams_notification' => 'teams',
            'slack_notification' => 'slack',
            default => null,
        };

        if (! $provider) {
            return ['type' => $action['type'] ?? 'connector_notification', 'status' => 'skipped', 'message' => 'Unsupported notification channel.', 'record' => $record];
        }

        $connector = IntegrationConnector::query()
            ->forCompany($rule->company_id)
            ->where('provider', $provider)
            ->whereIn('status', ['configured', 'connected', 'active'])
            ->first();

        if (! $connector) {
            return [
                'type' => $action['type'],
                'status' => 'skipped',
                'message' => ucfirst($provider).' connector is not configured.',
                'record' => $record,
            ];
        }

        $event = NotificationEvent::query()->create([
            'company_id' => $rule->company_id,
            'user_id' => $actor->id,
            'automation_rule_id' => $rule->id,
            'notification_number' => $this->nextNotificationNumber($rule->company_id),
            'source_type' => $record['type'] ?? null,
            'source_id' => $record['id'] ?? null,
            'module' => $rule->module ?: 'general',
            'event_type' => $action['type'],
            'title' => (string) ($action['subject'] ?? $action['title'] ?? $rule->name.': '.$record['label']),
            'message' => (string) ($action['message'] ?? 'Automation workflow matched '.$record['label'].'.'),
            'severity' => (string) ($action['severity'] ?? $rule->severity ?? 'medium'),
            'status' => 'queued',
            'channels' => [$provider],
            'delivery_status' => [$provider => 'queued'],
            'metadata' => [
                'connector_id' => $connector->id,
                'connector_name' => $connector->name,
                'record' => $record,
                'action' => $action,
            ],
            'created_by' => $actor->id,
        ]);

        return [
            'type' => $action['type'],
            'status' => 'queued',
            'notification_id' => $event->id,
            'notification_number' => $event->notification_number,
            'message' => ucfirst($provider).' notification queued for configured connector.',
            'record' => $record,
        ];
    }

    public function upsertSystemAlert(
        int $companyId,
        string $sourceKey,
        string $module,
        string $title,
        string $message,
        string $severity = 'medium',
        ?string $sourceType = null,
        ?int $sourceId = null,
        array $metadata = [],
        ?int $userId = null,
    ): NotificationEvent {
        return NotificationEvent::query()->updateOrCreate(
            ['company_id' => $companyId, 'source_key' => $sourceKey],
            [
                'user_id' => $userId,
                'notification_number' => NotificationEvent::query()->forCompany($companyId)->where('source_key', $sourceKey)->value('notification_number')
                    ?: $this->nextNotificationNumber($companyId),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'module' => $module,
                'event_type' => 'system_alert',
                'title' => $title,
                'message' => $message,
                'severity' => $severity,
                'status' => 'unread',
                'channels' => ['in_app'],
                'delivery_status' => ['in_app' => 'delivered'],
                'metadata' => $metadata,
            ],
        );
    }

    public function nextNotificationNumber(int $companyId): string
    {
        $prefix = 'NTF-'.now()->format('ym');
        $next = DB::table('notification_events')
            ->where('company_id', $companyId)
            ->where('notification_number', 'like', "{$prefix}-%")
            ->count() + 1;

        do {
            $candidate = sprintf("%s-%05d", $prefix, $next);
            $exists = DB::table('notification_events')
                ->where('company_id', $companyId)
                ->where('notification_number', $candidate)
                ->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    private function defaultSettings(): array
    {
        return [
            'in_app_enabled' => true,
            'email_enabled' => true,
            'minimum_email_severity' => 'medium',
            'digest_frequency' => 'immediate',
            'default_channels' => ['in_app', 'email'],
            'module_preferences' => [],
            'retry_policy' => ['max_retries' => 2, 'on_failure' => 'notify_admin'],
        ];
    }

    private function notificationChannels(array $action, AutomationRule $rule, NotificationSetting $settings): array
    {
        $channels = $action['channels'] ?? $rule->notification_config['channels'] ?? $settings->default_channels ?? ['in_app'];

        if (! is_array($channels)) {
            $channels = [$channels];
        }

        if (($action['type'] ?? null) === 'send_email' && ! in_array('email', $channels, true)) {
            $channels[] = 'email';
        }

        if (($action['type'] ?? null) === 'send_in_app_notification' && ! in_array('in_app', $channels, true)) {
            $channels[] = 'in_app';
        }

        return collect($channels)
            ->map(fn ($channel): string => trim((string) $channel))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function recipient(User $actor, array $action): array
    {
        $email = $action['recipient_email'] ?? $action['email_to'] ?? $actor->email;
        $name = $action['recipient_name'] ?? $actor->name;

        return [
            'user_id' => $actor->id,
            'name' => $name,
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
        ];
    }

    private function sendEmail(NotificationSetting $settings, NotificationEvent $event, string $subject, string $message, ?string $recipientEmail, ?string $recipientName, string $severity): array
    {
        if (! $settings->email_enabled) {
            return ['status' => 'disabled', 'sent_at' => null, 'error' => 'Email alerts are disabled in notification settings.'];
        }

        if (! $recipientEmail) {
            return ['status' => 'skipped', 'sent_at' => null, 'error' => 'No valid recipient email address is available.'];
        }

        if (! $this->passesEmailSeverity($settings, $severity)) {
            return ['status' => 'skipped', 'sent_at' => null, 'error' => 'Notification severity is below the configured email threshold.'];
        }

        try {
            Mail::raw($this->emailBody($event, $message), function ($mail) use ($settings, $subject, $recipientEmail, $recipientName): void {
                $mail->to($recipientEmail, $recipientName)->subject($subject);

                if (filter_var($settings->email_from_address, FILTER_VALIDATE_EMAIL)) {
                    $mail->from($settings->email_from_address, $settings->email_from_name ?: null);
                }

                if (filter_var($settings->reply_to_email, FILTER_VALIDATE_EMAIL)) {
                    $mail->replyTo($settings->reply_to_email);
                }
            });

            return ['status' => 'sent', 'sent_at' => now(), 'error' => null];
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'failed', 'sent_at' => null, 'error' => $exception->getMessage()];
        }
    }

    private function passesEmailSeverity(NotificationSetting $settings, string $severity): bool
    {
        $minimum = $settings->minimum_email_severity ?: 'medium';

        return (self::SEVERITY_RANK[$severity] ?? 2) >= (self::SEVERITY_RANK[$minimum] ?? 2);
    }

    private function emailBody(NotificationEvent $event, string $message): string
    {
        return implode("\n\n", [
            $message,
            'Notification: '.$event->notification_number,
            'Module: '.str_replace('_', ' ', $event->module),
            'Severity: '.strtoupper($event->severity),
            'Generated by Structra on '.now()->toDayDateTimeString().'.',
        ]);
    }
}
