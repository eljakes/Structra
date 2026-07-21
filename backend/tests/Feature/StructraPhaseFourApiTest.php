<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BudgetLine;
use App\Models\Client;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\Invoice;
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

    public function test_removed_module_api_surfaces_are_not_exposed(): void
    {
        [$user] = $this->tenantScenario();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/intelligence/analyze')->assertNotFound();
        $this->getJson('/api/v1/integrations')->assertNotFound();
        $this->getJson('/api/v1/localization')->assertNotFound();
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

}
