<?php

namespace App\Http\Controllers\Api;

use App\Models\AiInsight;
use App\Models\IntegrationConnector;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class IntegrationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        return response()->json([
            'connectors' => IntegrationConnector::query()->forCompany($companyId)->latest()->get()->map(fn (IntegrationConnector $connector) => [
                'id' => $connector->id,
                'provider' => $connector->provider,
                'name' => $connector->name,
                'category' => $connector->category,
                'status' => $connector->status,
                'settings' => $connector->settings,
                'last_tested_at' => $connector->last_tested_at,
                'connected_at' => $connector->connected_at,
                'last_error' => $connector->last_error,
            ]),
            'webhook_subscriptions' => WebhookSubscription::query()->forCompany($companyId)->withCount('deliveries')->latest()->get(),
            'webhook_deliveries' => WebhookDelivery::query()->forCompany($companyId)->with('subscription:id,name,event_type')->latest()->limit(100)->get(),
            'supported_providers' => $this->supportedProviders(),
            'api' => [
                'rest_base' => url('/api/v1'),
                'graphql_endpoint' => url('/api/v1/ecosystem/graphql'),
                'openapi_endpoint' => url('/api/v1/ecosystem/openapi'),
            ],
        ]);
    }

    public function storeConnector(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys($this->supportedProviders()))],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(['accounting', 'payments', 'sms', 'email', 'storage', 'analytics'])],
            'settings' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array'],
        ]);

        $connector = IntegrationConnector::query()->create([
            'company_id' => $companyId,
            'provider' => $data['provider'],
            'name' => $data['name'],
            'category' => $data['category'] ?? $this->supportedProviders()[$data['provider']]['category'],
            'status' => 'configured',
            'settings' => $data['settings'] ?? [],
            'encrypted_credentials' => $data['credentials'] ?? [],
            'created_by' => $this->user($request)->id,
        ]);

        return response()->json(['connector' => $this->connectorPayload($connector)], 201);
    }

    public function testConnector(Request $request, IntegrationConnector $connector): JsonResponse
    {
        $this->assertConnectorTenant($request, $connector);

        $credentials = $connector->encrypted_credentials ?? [];
        $hasCredentials = count($credentials) > 0;

        $connector->update([
            'status' => $hasCredentials ? 'connected' : 'configured',
            'last_tested_at' => now(),
            'connected_at' => $hasCredentials ? now() : null,
            'last_error' => $hasCredentials ? null : 'Connector has no credentials configured.',
        ]);

        return response()->json([
            'connector' => $this->connectorPayload($connector->fresh()),
            'test' => [
                'ok' => $hasCredentials,
                'message' => $hasCredentials ? 'Connector credentials are present and ready for API exchange.' : 'Add credentials before enabling live exchange.',
            ],
        ]);
    }

    public function storeWebhookSubscription(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', Rule::in($this->supportedEvents())],
            'target_url' => ['required', 'url', 'max:500'],
            'secret' => ['nullable', 'string', 'min:12', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $subscription = WebhookSubscription::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'event_type' => $data['event_type'],
            'target_url' => $data['target_url'],
            'secret' => $data['secret'] ?? bin2hex(random_bytes(16)),
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $this->user($request)->id,
        ]);

        return response()->json(['webhook_subscription' => $subscription], 201);
    }

    public function dispatchWebhook(Request $request, WebhookSubscription $subscription): JsonResponse
    {
        $this->assertWebhookTenant($request, $subscription);
        abort_if(! $subscription->is_active, 422, 'Webhook subscription is not active.');

        $data = $request->validate([
            'payload' => ['nullable', 'array'],
            'deliver_now' => ['nullable', 'boolean'],
            'simulate' => ['nullable', 'boolean'],
        ]);

        $payload = $data['payload'] ?? [
            'event_type' => $subscription->event_type,
            'company_id' => $subscription->company_id,
            'dispatched_at' => now()->toISOString(),
            'data' => ['message' => 'Structra webhook test event'],
        ];
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $encoded, $subscription->secret);

        $delivery = WebhookDelivery::query()->create([
            'company_id' => $subscription->company_id,
            'webhook_subscription_id' => $subscription->id,
            'event_type' => $subscription->event_type,
            'payload' => $payload,
            'signature' => $signature,
            'status' => 'queued',
            'attempts' => 0,
        ]);

        if ($data['deliver_now'] ?? false) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'X-Structra-Event' => $subscription->event_type,
                        'X-Structra-Signature' => $signature,
                    ])
                    ->post($subscription->target_url, $payload);

                $delivery->update([
                    'status' => $response->successful() ? 'delivered' : 'failed',
                    'response_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 2000),
                    'attempts' => 1,
                    'delivered_at' => $response->successful() ? now() : null,
                ]);
            } catch (\Throwable $exception) {
                $delivery->update([
                    'status' => 'failed',
                    'response_body' => $exception->getMessage(),
                    'attempts' => 1,
                ]);
            }
        } elseif ($data['simulate'] ?? true) {
            $delivery->update([
                'status' => 'delivered',
                'response_code' => 202,
                'response_body' => 'Simulated delivery accepted.',
                'attempts' => 1,
                'delivered_at' => now(),
            ]);
        }

        $subscription->update(['last_dispatched_at' => now()]);

        return response()->json(['webhook_delivery' => $delivery->fresh('subscription')], 201);
    }

    public function openApi(Request $request): JsonResponse
    {
        return response()->json([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Structra API',
                'version' => 'v1',
                'description' => 'Tenant-aware construction ERP API covering phases 1-4.',
            ],
            'servers' => [['url' => url('/api/v1')]],
            'security' => [['sanctumBearer' => []]],
            'paths' => [
                '/projects' => ['get' => ['summary' => 'List projects'], 'post' => ['summary' => 'Create project']],
                '/finance' => ['get' => ['summary' => 'Finance workspace']],
                '/intelligence' => ['get' => ['summary' => 'AI insights and forecasts']],
                '/intelligence/assistant' => ['post' => ['summary' => 'Ask natural-language operational questions']],
                '/bi' => ['get' => ['summary' => 'BI dashboards and datasets']],
                '/automation/rules/{rule}/run' => ['post' => ['summary' => 'Run an automation rule']],
                '/integrations/webhooks/{subscription}/dispatch' => ['post' => ['summary' => 'Dispatch or simulate webhook delivery']],
                '/localization/convert' => ['post' => ['summary' => 'Convert currencies using configured rates']],
                '/ecosystem/graphql' => ['post' => ['summary' => 'GraphQL read endpoint for primary datasets']],
            ],
            'components' => [
                'securitySchemes' => [
                    'sanctumBearer' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
            ],
        ]);
    }

    public function graphql(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'query' => ['required', 'string', 'max:4000'],
        ]);

        $query = strtolower($data['query']);
        $response = [];

        if (str_contains($query, 'projects')) {
            $response['projects'] = Project::query()
                ->forCompany($companyId)
                ->limit(50)
                ->get(['id', 'code', 'name', 'status', 'health_status', 'progress_percent', 'budget_total', 'actual_cost']);
        }

        if (str_contains($query, 'invoices')) {
            $response['invoices'] = Invoice::query()
                ->forCompany($companyId)
                ->limit(50)
                ->get(['id', 'invoice_number', 'title', 'status', 'payment_status', 'total_amount', 'balance_due']);
        }

        if (str_contains($query, 'insights')) {
            $response['insights'] = AiInsight::query()
                ->forCompany($companyId)
                ->limit(50)
                ->get(['id', 'category', 'severity', 'title', 'status', 'confidence_score']);
        }

        if (str_contains($query, 'inventoryitems') || str_contains($query, 'inventory_items')) {
            $response['inventoryItems'] = InventoryItem::query()
                ->forCompany($companyId)
                ->limit(50)
                ->get(['id', 'sku', 'name', 'quantity_on_hand', 'reorder_level', 'unit']);
        }

        abort_if($response === [], 422, 'Query did not request a supported root field.');

        return response()->json(['data' => $response]);
    }

    private function supportedProviders(): array
    {
        return [
            'quickbooks' => ['category' => 'accounting', 'name' => 'QuickBooks'],
            'xero' => ['category' => 'accounting', 'name' => 'Xero'],
            'paystack' => ['category' => 'payments', 'name' => 'Paystack'],
            'flutterwave' => ['category' => 'payments', 'name' => 'Flutterwave'],
            'africas_talking' => ['category' => 'sms', 'name' => "Africa's Talking"],
            's3' => ['category' => 'storage', 'name' => 'S3-compatible storage'],
        ];
    }

    private function supportedEvents(): array
    {
        return [
            'project.created',
            'project.updated',
            'invoice.issued',
            'payment.received',
            'ncr.created',
            'safety.incident.created',
            'automation.insight.created',
        ];
    }

    private function connectorPayload(IntegrationConnector $connector): array
    {
        return [
            'id' => $connector->id,
            'provider' => $connector->provider,
            'name' => $connector->name,
            'category' => $connector->category,
            'status' => $connector->status,
            'settings' => $connector->settings,
            'last_tested_at' => $connector->last_tested_at,
            'connected_at' => $connector->connected_at,
            'last_error' => $connector->last_error,
        ];
    }

    private function assertConnectorTenant(Request $request, IntegrationConnector $connector): void
    {
        abort_if((int) $connector->company_id !== $this->companyId($request), 404);
    }

    private function assertWebhookTenant(Request $request, WebhookSubscription $subscription): void
    {
        abort_if((int) $subscription->company_id !== $this->companyId($request), 404);
    }
}
