<?php

namespace App\Http\Controllers\Api;

use App\Models\NotificationEvent;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends ApiController
{
    public function index(Request $request, NotificationService $notifications): JsonResponse
    {
        $companyId = $this->companyId($request);
        $query = NotificationEvent::query()
            ->forCompany($companyId)
            ->with(['user:id,name,email', 'automationRule:id,name,module,trigger_event'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('module')) {
            $query->where('module', $request->query('module'));
        }

        return response()->json([
            'settings' => $notifications->settingsForCompany($companyId),
            'events' => $query->limit((int) $request->query('limit', 100))->get(),
            'summary' => [
                'unread' => NotificationEvent::query()->forCompany($companyId)->where('status', 'unread')->count(),
                'failed_email' => NotificationEvent::query()->forCompany($companyId)->where('delivery_status->email', 'failed')->count(),
                'sent_today' => NotificationEvent::query()->forCompany($companyId)->whereDate('created_at', now()->toDateString())->count(),
            ],
        ]);
    }

    public function updateSettings(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'in_app_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'email_from_name' => ['nullable', 'string', 'max:120'],
            'email_from_address' => ['nullable', 'email', 'max:255'],
            'reply_to_email' => ['nullable', 'email', 'max:255'],
            'minimum_email_severity' => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'digest_frequency' => ['sometimes', Rule::in(['immediate', 'hourly', 'daily', 'weekly'])],
            'default_channels' => ['sometimes', 'array'],
            'default_channels.*' => [Rule::in(['in_app', 'email'])],
            'module_preferences' => ['nullable', 'array'],
            'retry_policy' => ['nullable', 'array'],
            'retry_policy.max_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
            'retry_policy.on_failure' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'settings' => $notifications->updateSettings($this->companyId($request), $data, $this->user($request)->id),
        ]);
    }

    public function markRead(Request $request, NotificationEvent $notification): JsonResponse
    {
        $this->assertTenant($request, $notification);

        $notification->update([
            'status' => 'read',
            'read_at' => now(),
        ]);

        return response()->json(['notification' => $notification->fresh()]);
    }

    public function acknowledge(Request $request, NotificationEvent $notification): JsonResponse
    {
        $this->assertTenant($request, $notification);

        $notification->update([
            'status' => 'acknowledged',
            'read_at' => $notification->read_at ?? now(),
            'acknowledged_at' => now(),
        ]);

        return response()->json(['notification' => $notification->fresh()]);
    }

    private function assertTenant(Request $request, NotificationEvent $notification): void
    {
        abort_if((int) $notification->company_id !== $this->companyId($request), 404);
    }
}
