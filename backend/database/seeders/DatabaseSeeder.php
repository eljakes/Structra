<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BudgetLine;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\Drawing;
use App\Models\DrawingRevision;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionLine;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::query()->firstOrCreate(
            ['name' => 'Navkwa Construction Group'],
            [
                'registration_number' => 'NCG-2026-001',
                'tax_id' => 'GH-TAX-452090',
                'default_currency' => 'GHS',
                'country' => 'GH',
                'base_timezone' => 'Africa/Accra',
                'settings' => [
                    'approval_thresholds' => [
                        'procurement_manager' => 50000,
                        'project_director' => 250000,
                    ],
                ],
            ],
        );

        $headOffice = Branch::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'HQ'],
            [
                'name' => 'Accra Head Office',
                'city' => 'Accra',
                'country' => 'GH',
                'phone' => '+233 30 000 1000',
                'email' => 'operations@structra.local',
                'address' => 'Airport City, Accra',
            ],
        );

        $kumasi = Branch::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'KSI'],
            [
                'name' => 'Kumasi Branch',
                'city' => 'Kumasi',
                'country' => 'GH',
                'phone' => '+233 32 000 2000',
                'email' => 'kumasi@structra.local',
                'address' => 'Adum, Kumasi',
            ],
        );

        $ownerRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'owner'],
            ['name' => 'Owner', 'permissions' => ['*'], 'is_system' => true],
        );

        $projectRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'project-director'],
            ['name' => 'Project Director', 'permissions' => ['projects.manage', 'procurement.approve', 'documents.manage', 'reports.view'], 'is_system' => true],
        );

        $procurementRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'procurement-manager'],
            ['name' => 'Procurement Manager', 'permissions' => ['procurement.manage', 'documents.manage', 'reports.view'], 'is_system' => true],
        );

        $owner = User::query()->firstOrCreate(
            ['email' => 'owner@structra.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $headOffice->id,
                'role_id' => $ownerRole->id,
                'name' => 'Ama Mensah',
                'phone' => '+233 24 000 0001',
                'job_title' => 'Managing Director',
                'password' => 'Structra2026',
            ],
        );

        $projectDirector = User::query()->firstOrCreate(
            ['email' => 'pm@structra.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $headOffice->id,
                'role_id' => $projectRole->id,
                'name' => 'Kwame Boateng',
                'phone' => '+233 24 000 0002',
                'job_title' => 'Project Director',
                'password' => 'Structra2026',
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'procurement@structra.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $headOffice->id,
                'role_id' => $procurementRole->id,
                'name' => 'Esi Appiah',
                'phone' => '+233 24 000 0003',
                'job_title' => 'Procurement Manager',
                'password' => 'Structra2026',
            ],
        );

        $client = Client::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Golden Coast Developments'],
            [
                'branch_id' => $headOffice->id,
                'type' => 'corporate',
                'contact_name' => 'Nana Owusu',
                'email' => 'client@example.com',
                'phone' => '+233 55 000 1111',
                'address' => 'Osu, Accra',
                'currency' => 'GHS',
            ],
        );

        $cementSupplier = Supplier::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Prime Cement & Aggregates Ltd'],
            [
                'branch_id' => $headOffice->id,
                'contact_name' => 'Joseph Larbi',
                'email' => 'sales@primecement.example',
                'phone' => '+233 55 000 2222',
                'currency' => 'GHS',
                'rating' => 4,
                'lead_time_days' => 5,
            ],
        );

        $steelSupplier = Supplier::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Asante Steel Works'],
            [
                'branch_id' => $kumasi->id,
                'contact_name' => 'Akua Frimpong',
                'email' => 'orders@asantesteel.example',
                'phone' => '+233 55 000 3333',
                'currency' => 'GHS',
                'rating' => 5,
                'lead_time_days' => 10,
            ],
        );

        $project = Project::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'PRJ-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'client_id' => $client->id,
                'name' => 'Golden Coast Mixed-Use Tower',
                'description' => '12-storey mixed-use development with retail podium and office floors.',
                'status' => 'active',
                'health_status' => 'at_risk',
                'risk_level' => 'high',
                'site_address' => 'Cantonments, Accra',
                'country' => 'GH',
                'currency' => 'GHS',
                'contract_value' => 18500000,
                'progress_percent' => 38,
                'start_date' => now()->subMonths(4)->toDateString(),
                'target_end_date' => now()->addMonths(8)->toDateString(),
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ],
        );

        $budgetLines = [
            ['C01', 'Concrete and aggregates', 'materials', 1850000, 420000, 265000],
            ['S01', 'Reinforcement steel', 'materials', 2450000, 870000, 315000],
            ['L01', 'Site labour', 'labour', 1280000, 0, 410000],
            ['E01', 'Tower crane and equipment hire', 'equipment', 960000, 210000, 180000],
            ['O01', 'Site overheads and preliminaries', 'overheads', 720000, 0, 190000],
        ];

        foreach ($budgetLines as [$code, $description, $category, $budget, $committed, $actual]) {
            BudgetLine::query()->updateOrCreate(
                ['project_id' => $project->id, 'cost_code' => $code],
                [
                    'company_id' => $company->id,
                    'branch_id' => $headOffice->id,
                    'description' => $description,
                    'category' => $category,
                    'budget_amount' => $budget,
                    'committed_amount' => $committed,
                    'actual_amount' => $actual,
                    'forecast_amount' => max($budget, $actual + $committed),
                ],
            );
        }

        $tasks = [
            ['Complete level 4 slab pour', 'in_progress', 'urgent', 70, now()->subWeek(), now()->addDays(3)],
            ['Approve revised MEP coordination drawings', 'blocked', 'high', 40, now()->subWeeks(2), now()->subDay()],
            ['Procure structural steel batch B', 'in_progress', 'high', 55, now()->subDays(8), now()->addWeek()],
            ['Client progress meeting', 'todo', 'normal', 0, now()->addDays(2), now()->addDays(2)],
        ];

        foreach ($tasks as [$title, $status, $priority, $progress, $startDate, $dueDate]) {
            ProjectTask::query()->firstOrCreate(
                ['project_id' => $project->id, 'title' => $title],
                [
                    'company_id' => $company->id,
                    'branch_id' => $headOffice->id,
                    'assigned_to' => $projectDirector->id,
                    'status' => $status,
                    'priority' => $priority,
                    'progress_percent' => $progress,
                    'start_date' => $startDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );
        }

        $requisition = PurchaseRequisition::query()->firstOrCreate(
            ['company_id' => $company->id, 'requisition_number' => 'REQ-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'title' => 'Concrete pour materials for level 4',
                'status' => 'approved',
                'priority' => 'urgent',
                'required_by' => now()->addDays(5)->toDateString(),
                'total_estimated' => 142000,
                'justification' => 'Required to maintain the structural works programme.',
                'requested_by' => $projectDirector->id,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now()->subDay(),
            ],
        );

        PurchaseRequisitionLine::query()->firstOrCreate(
            ['purchase_requisition_id' => $requisition->id, 'description' => 'Ready-mix concrete C30'],
            [
                'company_id' => $company->id,
                'supplier_id' => $cementSupplier->id,
                'cost_code' => 'C01',
                'quantity' => 180,
                'unit' => 'm3',
                'estimated_unit_cost' => 650,
                'estimated_total' => 117000,
            ],
        );

        PurchaseRequisitionLine::query()->firstOrCreate(
            ['purchase_requisition_id' => $requisition->id, 'description' => 'Crushed aggregate'],
            [
                'company_id' => $company->id,
                'supplier_id' => $cementSupplier->id,
                'cost_code' => 'C01',
                'quantity' => 50,
                'unit' => 'tonnes',
                'estimated_unit_cost' => 500,
                'estimated_total' => 25000,
            ],
        );

        $purchaseOrder = PurchaseOrder::query()->firstOrCreate(
            ['company_id' => $company->id, 'po_number' => 'PO-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'supplier_id' => $cementSupplier->id,
                'purchase_requisition_id' => $requisition->id,
                'status' => 'approved',
                'currency' => 'GHS',
                'issue_date' => now()->subDay()->toDateString(),
                'expected_delivery_date' => now()->addDays(4)->toDateString(),
                'subtotal' => 142000,
                'tax_amount' => 0,
                'total_amount' => 142000,
                'delivery_status' => 'pending',
                'payment_status' => 'unpaid',
                'created_by' => $owner->id,
                'approved_by' => $owner->id,
                'approved_at' => now()->subDay(),
            ],
        );

        PurchaseOrderLine::query()->firstOrCreate(
            ['purchase_order_id' => $purchaseOrder->id, 'description' => 'Ready-mix concrete C30'],
            [
                'company_id' => $company->id,
                'cost_code' => 'C01',
                'quantity' => 180,
                'unit' => 'm3',
                'unit_cost' => 650,
                'line_total' => 117000,
            ],
        );

        PurchaseOrderLine::query()->firstOrCreate(
            ['purchase_order_id' => $purchaseOrder->id, 'description' => 'Crushed aggregate'],
            [
                'company_id' => $company->id,
                'cost_code' => 'C01',
                'quantity' => 50,
                'unit' => 'tonnes',
                'unit_cost' => 500,
                'line_total' => 25000,
            ],
        );

        Document::query()->firstOrCreate(
            ['company_id' => $company->id, 'document_number' => 'DOC-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'uploaded_by' => $owner->id,
                'title' => 'Main works contract',
                'document_type' => 'contract',
                'repository_scope' => 'project',
                'folder' => '/Contracts',
                'status' => 'approved',
                'tags' => ['contract', 'client'],
                'description' => 'Signed contract package and appendices.',
            ],
        );

        Document::query()->firstOrCreate(
            ['company_id' => $company->id, 'document_number' => 'DOC-2607-00002'],
            [
                'branch_id' => $headOffice->id,
                'uploaded_by' => $owner->id,
                'title' => 'Accra branch business permit',
                'document_type' => 'policy',
                'repository_scope' => 'branch',
                'folder' => '/Branch Compliance',
                'status' => 'active',
                'tags' => ['permit', 'compliance'],
            ],
        );

        $drawing = Drawing::query()->firstOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'drawing_number' => 'A-201'],
            [
                'branch_id' => $headOffice->id,
                'uploaded_by' => $owner->id,
                'title' => 'Level 4 architectural general arrangement',
                'discipline' => 'architectural',
                'status' => 'issued_for_review',
                'current_revision' => 'P02',
                'description' => 'Architectural arrangement drawing for level 4.',
                'tags' => ['level-4', 'architectural'],
                'linked_records' => ['tasks' => ['Approve revised MEP coordination drawings']],
            ],
        );

        DrawingRevision::query()->firstOrCreate(
            ['drawing_id' => $drawing->id, 'revision_code' => 'P01'],
            [
                'company_id' => $company->id,
                'uploaded_by' => $owner->id,
                'status' => 'superseded',
                'notes' => 'Initial issue.',
                'issued_at' => now()->subWeeks(3),
                'superseded_at' => now()->subWeek(),
            ],
        );

        DrawingRevision::query()->firstOrCreate(
            ['drawing_id' => $drawing->id, 'revision_code' => 'P02'],
            [
                'company_id' => $company->id,
                'uploaded_by' => $owner->id,
                'status' => 'issued_for_review',
                'notes' => 'Revised lobby and riser coordination.',
                'issued_at' => now()->subWeek(),
            ],
        );

        $project->forceFill([
            'budget_total' => BudgetLine::query()->where('project_id', $project->id)->sum('budget_amount'),
            'committed_total' => BudgetLine::query()->where('project_id', $project->id)->sum('committed_amount'),
            'actual_cost' => BudgetLine::query()->where('project_id', $project->id)->sum('actual_amount'),
            'forecast_to_complete' => BudgetLine::query()->where('project_id', $project->id)->sum('forecast_amount'),
            'progress_percent' => (int) round(ProjectTask::query()->where('project_id', $project->id)->avg('progress_percent')),
        ])->save();
    }
}
