<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Drawing;
use App\Models\DrawingRevision;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructraPhaseTwoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_pipeline_tender_estimate_and_win_creates_project_budget(): void
    {
        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $leadId = $this->postJson('/api/v1/sales/leads', [
            'branch_id' => $branch->id,
            'company_name' => 'Atlantic Schools Trust',
            'contact_name' => 'Client Lead',
            'email' => 'lead@example.com',
            'estimated_value' => 900000,
        ])
            ->assertCreated()
            ->assertJsonPath('lead.stage', 'new')
            ->json('lead.id');

        $opportunityId = $this->postJson("/api/v1/sales/leads/{$leadId}/qualify", [
            'name' => 'Atlantic Classroom Block',
            'scope' => 'Two-storey classroom block and external works.',
        ])
            ->assertCreated()
            ->assertJsonPath('opportunity.stage', 'qualified')
            ->json('opportunity.id');

        $tenderId = $this->postJson("/api/v1/sales/opportunities/{$opportunityId}/tenders", [
            'deadline_at' => now()->addWeeks(2)->toISOString(),
        ])
            ->assertCreated()
            ->assertJsonPath('tender.status', 'draft')
            ->json('tender.id');

        $estimateId = $this->postJson('/api/v1/sales/estimates', [
            'tender_id' => $tenderId,
            'title' => 'Atlantic base estimate',
            'overhead_percent' => 5,
            'profit_percent' => 10,
            'lines' => [
                [
                    'cost_code' => 'C01',
                    'description' => 'Concrete works',
                    'category' => 'materials',
                    'quantity' => 100,
                    'unit' => 'm3',
                    'unit_cost' => 500,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('estimate.subtotal', '50000.00')
            ->json('estimate.id');

        $this->postJson("/api/v1/sales/estimates/{$estimateId}/approve")->assertOk();
        $this->postJson("/api/v1/sales/tenders/{$tenderId}/submit")->assertOk();

        $projectId = $this->postJson("/api/v1/sales/tenders/{$tenderId}/win", [
            'estimate_id' => $estimateId,
            'project_name' => 'Atlantic Classroom Delivery',
        ])
            ->assertCreated()
            ->assertJsonPath('project.name', 'Atlantic Classroom Delivery')
            ->json('project.id');

        $this->assertDatabaseHas('budget_lines', [
            'project_id' => $projectId,
            'cost_code' => 'C01',
            'budget_amount' => 50000,
        ]);
    }

    public function test_inventory_stock_movements_and_supplier_reviews_work(): void
    {
        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $warehouseId = $this->postJson('/api/v1/inventory/warehouses', [
            'branch_id' => $branch->id,
            'name' => 'Main Store',
            'code' => 'MAIN',
        ])
            ->assertCreated()
            ->json('warehouse.id');

        $itemId = $this->postJson('/api/v1/inventory/items', [
            'sku' => 'CEM-TEST',
            'name' => 'Cement Bag',
            'unit' => 'bag',
            'reorder_level' => 20,
            'average_cost' => 100,
        ])
            ->assertCreated()
            ->json('item.id');

        $this->postJson('/api/v1/inventory/movements', [
            'warehouse_id' => $warehouseId,
            'inventory_item_id' => $itemId,
            'type' => 'receipt',
            'quantity' => 100,
            'unit_cost' => 95,
            'reason' => 'Opening stock',
        ])->assertCreated();

        $this->postJson('/api/v1/inventory/movements', [
            'warehouse_id' => $warehouseId,
            'inventory_item_id' => $itemId,
            'type' => 'issue',
            'quantity' => 30,
            'unit_cost' => 95,
            'reason' => 'Issued to site',
        ])->assertCreated();

        $this->getJson('/api/v1/inventory')
            ->assertOk()
            ->assertJsonPath('items.0.quantity_on_hand', '70.00');

        $supplier = Supplier::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'name' => 'Supply Partner',
        ]);

        $this->postJson("/api/v1/suppliers/{$supplier->id}/prices", [
            'inventory_item_id' => $itemId,
            'description' => 'Cement Bag',
            'unit_price' => 93,
        ])->assertCreated();

        $this->postJson("/api/v1/suppliers/{$supplier->id}/reviews", [
            'rating' => 5,
            'quality_score' => 5,
            'delivery_score' => 4,
            'cost_score' => 5,
        ])->assertCreated();

        $this->assertSame(5, $supplier->fresh()->rating);
    }

    public function test_field_daily_reports_issues_and_attendance_work(): void
    {
        Storage::fake('local');

        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $project = Project::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'code' => 'PRJ-FLD-001',
            'name' => 'Field Test Project',
        ]);

        $reportId = $this->postJson("/api/v1/projects/{$project->id}/daily-reports", [
            'report_date' => now()->toDateString(),
            'weather' => 'Sunny',
            'labour_count' => 24,
            'progress_notes' => 'Excavation complete.',
        ])
            ->assertCreated()
            ->assertJsonPath('daily_report.status', 'draft')
            ->json('daily_report.id');

        $this->postJson("/api/v1/field/daily-reports/{$reportId}/transition", ['status' => 'submitted'])->assertOk();
        $this->postJson("/api/v1/field/daily-reports/{$reportId}/transition", ['status' => 'approved'])->assertOk();

        $issueId = $this->post("/api/v1/projects/{$project->id}/field-issues", [
            'title' => 'Unsafe edge protection',
            'category' => 'safety',
            'severity' => 'high',
            'photo' => UploadedFile::fake()->image('issue.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('issue.id');

        $this->patchJson("/api/v1/field/issues/{$issueId}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('issue.status', 'resolved');

        $attendanceId = $this->post('/api/v1/attendance/clock-in', [
            'project_id' => $project->id,
            'clock_in_latitude' => 5.5,
            'clock_in_longitude' => -0.1,
            'face' => UploadedFile::fake()->image('face-in.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('attendance.status', 'open')
            ->json('attendance.id');

        $this->post("/api/v1/attendance/{$attendanceId}/clock-out", [
            'clock_out_latitude' => 5.5,
            'clock_out_longitude' => -0.1,
            'face' => UploadedFile::fake()->image('face-out.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('attendance.status', 'closed');
    }

    public function test_drawing_markups_and_reviews_update_approval_state(): void
    {
        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $drawing = Drawing::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'drawing_number' => 'A-200',
            'title' => 'Elevation',
            'discipline' => 'architectural',
            'status' => 'issued_for_review',
            'current_revision' => 'P01',
        ]);

        DrawingRevision::query()->create([
            'company_id' => $user->company_id,
            'drawing_id' => $drawing->id,
            'revision_code' => 'P01',
            'status' => 'issued_for_review',
            'issued_at' => now(),
        ]);

        $markupId = $this->postJson("/api/v1/drawings/{$drawing->id}/markups", [
            'markup_type' => 'pin',
            'x' => 0.25,
            'y' => 0.4,
            'comment' => 'Revise window head detail.',
        ])
            ->assertCreated()
            ->json('markup.id');

        $this->postJson("/api/v1/drawing-markups/{$markupId}/resolve")
            ->assertOk()
            ->assertJsonPath('markup.status', 'resolved');

        $this->postJson("/api/v1/drawings/{$drawing->id}/reviews", [
            'decision' => 'approved',
            'comments' => 'Approved for construction.',
        ])
            ->assertCreated()
            ->assertJsonPath('drawing.status', 'approved_for_construction');
    }

    private function tenantUser(): array
    {
        $company = Company::query()->create([
            'name' => 'Phase Two Build Co',
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
