<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BudgetLine;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructraPhaseOneApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_tenant_and_api_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'company_name' => 'Atlas Builders Ltd',
            'branch_name' => 'Head Office',
            'country' => 'GH',
            'currency' => 'GHS',
            'name' => 'Adjoa Admin',
            'email' => 'adjoa@example.com',
            'password' => 'Structra2026',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.company.name', 'Atlas Builders Ltd')
            ->assertJsonPath('user.role.slug', 'owner')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('companies', ['name' => 'Atlas Builders Ltd']);
        $this->assertDatabaseHas('users', ['email' => 'adjoa@example.com']);
    }

    public function test_projects_tasks_budgets_and_dashboard_rollups_work(): void
    {
        [$user, $branch] = $this->tenantUser();

        Sanctum::actingAs($user);

        $projectId = $this->postJson('/api/v1/projects', [
            'branch_id' => $branch->id,
            'client_name' => 'Cedar Developments',
            'name' => 'Cedar Office Park',
            'status' => 'active',
            'contract_value' => 2500000,
            'start_date' => now()->toDateString(),
            'target_end_date' => now()->addMonths(10)->toDateString(),
        ])
            ->assertCreated()
            ->json('project.id');

        $this->postJson("/api/v1/projects/{$projectId}/budget-lines", [
            'cost_code' => 'C01',
            'description' => 'Concrete works',
            'category' => 'materials',
            'budget_amount' => 500000,
        ])->assertCreated();

        $this->postJson("/api/v1/projects/{$projectId}/tasks", [
            'title' => 'Mobilize site team',
            'status' => 'done',
            'priority' => 'high',
            'progress_percent' => 100,
            'due_date' => now()->addDay()->toDateString(),
        ])->assertCreated();

        $this->getJson("/api/v1/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('project.budget_total', '500000.00')
            ->assertJsonPath('project.progress_percent', 100);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('kpis.total_projects', 1)
            ->assertJsonPath('kpis.active_projects', 1)
            ->assertJsonPath('kpis.budget_total', 500000);
    }

    public function test_procurement_flow_updates_project_commitments_and_actuals(): void
    {
        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'name' => 'Harbour Estates',
        ]);

        $project = Project::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'code' => 'PRJ-TST-001',
            'name' => 'Harbour Residences',
            'status' => 'active',
        ]);

        BudgetLine::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'cost_code' => 'S01',
            'description' => 'Rebar',
            'category' => 'materials',
            'budget_amount' => 120000,
            'forecast_amount' => 120000,
        ]);

        $supplier = Supplier::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'name' => 'Reliable Steel Ltd',
        ]);

        $requisitionId = $this->postJson("/api/v1/projects/{$project->id}/requisitions", [
            'title' => 'Batch A reinforcement steel',
            'priority' => 'high',
            'lines' => [
                [
                    'supplier_id' => $supplier->id,
                    'description' => 'Y16 reinforcement bars',
                    'cost_code' => 'S01',
                    'quantity' => 30,
                    'unit' => 'tonnes',
                    'estimated_unit_cost' => 1500,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('requisition.total_estimated', '45000.00')
            ->json('requisition.id');

        $this->postJson("/api/v1/procurement/requisitions/{$requisitionId}/submit")->assertOk();

        $this->postJson("/api/v1/procurement/requisitions/{$requisitionId}/review", [
            'decision' => 'approved',
        ])->assertOk();

        $purchaseOrderId = $this->postJson("/api/v1/procurement/requisitions/{$requisitionId}/convert-to-po", [
            'supplier_id' => $supplier->id,
        ])
            ->assertCreated()
            ->assertJsonPath('purchase_order.total_amount', '45000.00')
            ->json('purchase_order.id');

        $this->postJson("/api/v1/procurement/purchase-orders/{$purchaseOrderId}/transition", ['status' => 'issued'])->assertOk();
        $this->postJson("/api/v1/procurement/purchase-orders/{$purchaseOrderId}/transition", ['status' => 'approved'])->assertOk();

        $this->assertSame('45000.00', $project->fresh()->committed_total);
        $this->assertSame('0.00', $project->fresh()->actual_cost);

        $this->postJson("/api/v1/procurement/purchase-orders/{$purchaseOrderId}/transition", ['status' => 'delivered'])->assertOk();

        $this->assertSame('45000.00', $project->fresh()->actual_cost);
    }

    public function test_document_uploads_and_drawing_revision_workflows_store_files(): void
    {
        Storage::fake('local');

        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $project = Project::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'code' => 'PRJ-DOC-001',
            'name' => 'Documentation Test Project',
        ]);

        $documentPath = $this->post('/api/v1/documents', [
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'title' => 'Signed contract',
            'document_type' => 'contract',
            'file' => UploadedFile::fake()->create('contract.pdf', 128, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('document.version', 1)
            ->json('document.file_path');

        Storage::disk('local')->assertExists($documentPath);

        $drawingId = $this->post('/api/v1/drawings', [
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'drawing_number' => 'A-101',
            'title' => 'Ground floor plan',
            'discipline' => 'architectural',
            'status' => 'issued_for_review',
            'revision_code' => 'P01',
            'file' => UploadedFile::fake()->create('A-101-P01.pdf', 256, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('drawing.current_revision', 'P01')
            ->json('drawing.id');

        $this->post("/api/v1/drawings/{$drawingId}/revisions", [
            'revision_code' => 'P02',
            'notes' => 'Updated room tags.',
            'file' => UploadedFile::fake()->create('A-101-P02.pdf', 256, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('drawing.current_revision', 'P02');

        $this->postJson("/api/v1/drawings/{$drawingId}/transition", [
            'status' => 'approved_for_construction',
        ])
            ->assertOk()
            ->assertJsonPath('drawing.status', 'approved_for_construction');
    }

    private function tenantUser(): array
    {
        $company = Company::query()->create([
            'name' => 'Test Build Co',
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

        return [$user, $branch, $company];
    }
}
