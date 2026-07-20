<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BudgetLine;
use App\Models\Client;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LocalizationCountry;
use App\Models\NonConformanceReport;
use App\Models\Project;
use App\Models\Role;
use App\Models\SafetyIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructraPhaseFourApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_analysis_assistant_and_insight_resolution_work(): void
    {
        [$user, , , $project] = $this->tenantScenario();
        Sanctum::actingAs($user);

        $analysis = $this->postJson('/api/v1/intelligence/analyze')
            ->assertOk()
            ->assertJsonPath('message', 'Analysis completed.');

        $this->assertGreaterThanOrEqual(4, $analysis->json('insights_created_or_updated'));
        $this->assertGreaterThanOrEqual(3, $analysis->json('forecasts_created_or_updated'));
        $this->assertDatabaseHas('ai_insights', [
            'company_id' => $user->company_id,
            'project_id' => $project->id,
            'source_key' => "project-risk-{$project->id}",
        ]);

        $queryId = $this->postJson('/api/v1/intelligence/assistant', [
            'question' => 'What is our cash and receivables exposure?',
        ])
            ->assertOk()
            ->assertJsonPath('assistant_query.intent', 'cash_flow')
            ->json('assistant_query.id');

        $this->assertDatabaseHas('assistant_queries', [
            'id' => $queryId,
            'company_id' => $user->company_id,
            'intent' => 'cash_flow',
        ]);

        $insightId = $analysis->json('insights.0.id');

        $this->postJson("/api/v1/intelligence/insights/{$insightId}/resolve")
            ->assertOk()
            ->assertJsonPath('insight.status', 'resolved');
    }

    public function test_business_intelligence_dashboards_and_metric_snapshots_work(): void
    {
        [$user] = $this->tenantScenario();
        Sanctum::actingAs($user);

        $dashboardId = $this->postJson('/api/v1/bi/dashboards', [
            'name' => 'Operations Intelligence',
            'audience' => 'operations',
            'refresh_interval' => 'hourly',
            'is_default' => true,
            'widgets' => [
                ['title' => 'Active Projects', 'widget_type' => 'metric', 'metric_key' => 'active_projects'],
                ['title' => 'Receivables', 'widget_type' => 'metric', 'metric_key' => 'accounts_receivable'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('dashboard.audience', 'operations')
            ->assertJsonCount(2, 'dashboard.widgets')
            ->json('dashboard.id');

        $this->postJson('/api/v1/bi/snapshots', [
            'period_label' => 'Phase 4 Test',
            'snapshot_date' => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('snapshot.period_label', 'Phase 4 Test')
            ->assertJsonPath('snapshot.metrics.active_projects', 1);

        $this->getJson('/api/v1/bi')
            ->assertOk()
            ->assertJsonPath('metrics.active_projects', 1)
            ->assertJsonPath('meta.module_name', 'Structra Intelligence')
            ->assertJsonPath('dashboards.0.id', $dashboardId)
            ->assertJsonCount(1, 'datasets.cost_by_category')
            ->assertJsonStructure([
                'executive' => ['headline_scorecards', 'health_matrix', 'executive_actions'],
                'portfolio' => ['comparison', 'rankings'],
                'project_controls' => ['earned_value', 'cost_code_performance', 'delayed_activities'],
                'procurement' => ['kpis', 'funnel', 'supplier_scorecards'],
                'alerts' => ['items'],
            ]);
    }

    public function test_automation_rules_create_operational_insights(): void
    {
        [$user] = $this->tenantScenario();
        Sanctum::actingAs($user);

        $ruleId = $this->postJson('/api/v1/automation/rules', [
            'name' => 'Low stock action test',
            'rule_type' => 'low_stock',
            'trigger_event' => 'manual',
            'severity' => 'high',
            'actions' => [
                'type' => 'create_insight',
                'recommendation' => 'Expedite replenishment from approved supplier.',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('rule.rule_type', 'low_stock')
            ->json('rule.id');

        $this->postJson("/api/v1/automation/rules/{$ruleId}/run")
            ->assertOk()
            ->assertJsonPath('run.status', 'completed')
            ->assertJsonPath('run.matched_count', 1)
            ->assertJsonPath('run.actions_executed', 1);

        $this->assertDatabaseHas('ai_insights', [
            'company_id' => $user->company_id,
            'category' => 'automation',
            'severity' => 'high',
            'source' => 'workflow_automation',
        ]);

        $this->postJson('/api/v1/automation/run-active')
            ->assertOk()
            ->assertJsonCount(1, 'runs');
    }

    public function test_integration_connectors_webhooks_openapi_and_graphql_work(): void
    {
        [$user] = $this->tenantScenario();
        Sanctum::actingAs($user);

        $connectorId = $this->postJson('/api/v1/integrations/connectors', [
            'provider' => 'xero',
            'name' => 'Xero Test Tenant',
            'settings' => ['sync_invoices' => true],
            'credentials' => ['tenant_id' => 'tenant-123', 'client_id' => 'client-123'],
        ])
            ->assertCreated()
            ->assertJsonPath('connector.status', 'configured')
            ->json('connector.id');

        $this->postJson("/api/v1/integrations/connectors/{$connectorId}/test")
            ->assertOk()
            ->assertJsonPath('test.ok', true)
            ->assertJsonPath('connector.status', 'connected');

        $subscriptionId = $this->postJson('/api/v1/integrations/webhooks', [
            'name' => 'Invoice Webhook',
            'event_type' => 'invoice.issued',
            'target_url' => 'https://example.com/structra/webhook',
            'secret' => 'phase-four-secret',
        ])
            ->assertCreated()
            ->assertJsonPath('webhook_subscription.event_type', 'invoice.issued')
            ->json('webhook_subscription.id');

        $this->postJson("/api/v1/integrations/webhooks/{$subscriptionId}/dispatch", [
            'payload' => ['invoice_number' => 'INV-TEST-001'],
            'simulate' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('webhook_delivery.status', 'delivered')
            ->assertJsonPath('webhook_delivery.response_code', 202);

        $this->getJson('/api/v1/ecosystem/openapi')
            ->assertOk()
            ->assertJsonPath('info.title', 'Structra API');

        $this->postJson('/api/v1/ecosystem/graphql', [
            'query' => '{ projects { id name } invoices { id balance_due } inventoryItems { id sku } }',
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonCount(1, 'data.invoices')
            ->assertJsonCount(1, 'data.inventoryItems');
    }

    public function test_multi_country_tax_and_currency_operations_work(): void
    {
        [$user] = $this->tenantScenario();
        Sanctum::actingAs($user);

        $this->seedCountries();

        $this->patchJson('/api/v1/localization/settings', [
            'base_country' => 'GH',
            'base_currency' => 'GHS',
            'enabled_countries' => ['GH', 'NG', 'KE'],
            'enabled_currencies' => ['GHS', 'USD', 'NGN'],
            'tax_rounding_mode' => 'document',
            'date_format' => 'd/m/Y',
        ])
            ->assertOk()
            ->assertJsonPath('settings.base_currency', 'GHS');

        $this->postJson('/api/v1/localization/exchange-rates', [
            'base_currency' => 'GHS',
            'quote_currency' => 'USD',
            'rate' => 0.08,
            'rate_date' => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('exchange_rate.quote_currency', 'USD');

        $this->postJson('/api/v1/localization/tax-rates', [
            'country' => 'GH',
            'name' => 'Ghana VAT Test',
            'rate_percent' => 15,
            'is_default' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('tax_rate.country', 'GH');

        $this->postJson('/api/v1/localization/convert', [
            'amount' => 1000,
            'from_currency' => 'GHS',
            'to_currency' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('conversion.converted_amount', 80);

        $this->postJson('/api/v1/localization/calculate-tax', [
            'amount' => 2000,
            'country' => 'GH',
        ])
            ->assertOk()
            ->assertJsonPath('tax.tax_amount', 300)
            ->assertJsonPath('tax.total_amount', 2300);
    }

    private function tenantScenario(): array
    {
        $company = Company::query()->create([
            'name' => 'Phase Four Build Co',
            'default_currency' => 'GHS',
            'country' => 'GH',
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Head Office',
            'code' => 'HQ',
        ]);

        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $user = User::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role_id' => $role->id,
            'name' => 'Owner User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Structra2026',
        ]);

        $client = Client::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Phase Four Client',
        ]);

        $project = Project::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'code' => 'PRJ-P4-001',
            'name' => 'Phase Four Risk Project',
            'status' => 'active',
            'health_status' => 'at_risk',
            'contract_value' => 900000,
            'budget_total' => 500000,
            'committed_total' => 210000,
            'actual_cost' => 260000,
            'forecast_to_complete' => 575000,
            'progress_percent' => 55,
        ]);

        BudgetLine::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'cost_code' => 'C01',
            'description' => 'Concrete works',
            'category' => 'materials',
            'budget_amount' => 500000,
            'committed_amount' => 210000,
            'actual_amount' => 260000,
            'forecast_amount' => 575000,
        ]);

        Invoice::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-P4-001',
            'title' => 'Overdue interim certificate',
            'status' => 'issued',
            'issue_date' => now()->subMonth()->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'currency' => 'GHS',
            'subtotal' => 125000,
            'tax_amount' => 0,
            'total_amount' => 125000,
            'amount_paid' => 25000,
            'balance_due' => 100000,
            'payment_status' => 'partial',
            'created_by' => $user->id,
        ]);

        InventoryItem::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'sku' => 'P4-CEM',
            'name' => 'Portland Cement',
            'category' => 'materials',
            'unit' => 'bag',
            'reorder_level' => 20,
            'average_cost' => 95,
            'quantity_on_hand' => 5,
            'status' => 'active',
        ]);

        NonConformanceReport::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'ncr_number' => 'NCR-P4-001',
            'title' => 'Honeycombing in shear wall',
            'severity' => 'high',
            'status' => 'open',
            'raised_by' => $user->id,
        ]);

        SafetyIncident::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'incident_number' => 'HSE-P4-001',
            'incident_type' => 'near_miss',
            'severity' => 'medium',
            'status' => 'reported',
            'occurred_at' => now()->subDay(),
            'description' => 'Near miss during scaffold material handling.',
            'reported_by' => $user->id,
        ]);

        return [$user, $branch, $company, $project, $client];
    }

    private function seedCountries(): void
    {
        foreach ([['GH', 'Ghana', 'GHS'], ['NG', 'Nigeria', 'NGN'], ['KE', 'Kenya', 'KES']] as [$iso2, $name, $currency]) {
            LocalizationCountry::query()->firstOrCreate(
                ['iso2' => $iso2],
                [
                    'name' => $name,
                    'currency' => $currency,
                    'timezone' => 'UTC',
                    'tax_label' => 'VAT',
                    'is_active' => true,
                ],
            );
        }
    }
}
