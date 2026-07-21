<?php

namespace Database\Seeders;

use App\Models\AiInsight;
use App\Models\AssistantQuery;
use App\Models\AttendanceRecord;
use App\Models\AutomationRule;
use App\Models\BiDashboard;
use App\Models\BiWidget;
use App\Models\Branch;
use App\Models\BudgetLine;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\Company;
use App\Models\CompanyLocalizationSetting;
use App\Models\ConsultantSubmittal;
use App\Models\Document;
use App\Models\Drawing;
use App\Models\DrawingMarkup;
use App\Models\DrawingReview;
use App\Models\DrawingRevision;
use App\Models\EmployeeProfile;
use App\Models\EquipmentAsset;
use App\Models\EquipmentAssignment;
use App\Models\Estimate;
use App\Models\EstimateLine;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\FieldDailyReport;
use App\Models\FieldIssue;
use App\Models\FuelLog;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\IntegrationConnector;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\LocalizationCountry;
use App\Models\MaintenanceLog;
use App\Models\MetricSnapshot;
use App\Models\NonConformanceReport;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PortalAccess;
use App\Models\PortalUser;
use App\Models\PredictiveForecast;
use App\Models\PricingItem;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionLine;
use App\Models\Role;
use App\Models\SafetyIncident;
use App\Models\SafetyObservation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPerformanceReview;
use App\Models\SupplierPriceCatalog;
use App\Models\TaxRate;
use App\Models\Tender;
use App\Models\TenderRfi;
use App\Models\ToolboxTalk;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WebhookSubscription;
use App\Models\WorkPermit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! (bool) env('STRUCTRA_SEED_DEMO', false)) {
            $this->command?->warn('Structra demo seed data skipped. Set STRUCTRA_SEED_DEMO=true only in disposable development databases.');

            return;
        }

        $company = Company::query()->updateOrCreate(
            ['registration_number' => 'NCG-2026-001'],
            [
                'name' => 'Structra Workspace',
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
            ['name' => 'CEO', 'permissions' => ['*'], 'is_system' => true],
        );
        $ownerRole->forceFill(['name' => 'CEO'])->save();

        $projectRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'project-director'],
            ['name' => 'Project Director', 'permissions' => ['projects.manage', 'procurement.approve', 'documents.manage', 'field.manage', 'attendance.manage', 'equipment.manage', 'quality.manage', 'safety.manage', 'portals.manage', 'bi.manage', 'automation.manage', 'reports.view'], 'is_system' => true],
        );

        $procurementRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'procurement-manager'],
            ['name' => 'Procurement Manager', 'permissions' => ['procurement.manage', 'inventory.manage', 'suppliers.manage', 'equipment.manage', 'documents.manage', 'reports.view'], 'is_system' => true],
        );

        $salesRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'sales-estimating'],
            ['name' => 'Sales & Estimating', 'permissions' => ['crm.manage', 'tenders.manage', 'estimating.manage', 'reports.view'], 'is_system' => true],
        );

        $siteRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'site-engineer'],
            ['name' => 'Site Engineer', 'permissions' => ['projects.manage', 'documents.manage', 'field.manage', 'attendance.manage', 'inventory.manage', 'equipment.manage', 'quality.manage', 'safety.manage', 'reports.view'], 'is_system' => true],
        );

        $financeRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'finance'],
            ['name' => 'Finance', 'permissions' => ['finance.manage', 'payroll.manage', 'bi.manage', 'reports.view', 'procurement.approve'], 'is_system' => true],
        );

        $hrRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'hr'],
            ['name' => 'HR', 'permissions' => ['payroll.manage', 'reports.view'], 'is_system' => true],
        );

        $architectRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'architect'],
            ['name' => 'Architect', 'permissions' => ['documents.manage', 'reports.view'], 'is_system' => true],
        );

        $qhseRole = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'qhse-manager'],
            ['name' => 'QHSE Manager', 'permissions' => ['quality.manage', 'safety.manage', 'field.manage', 'documents.manage', 'bi.manage', 'automation.manage', 'reports.view'], 'is_system' => true],
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

        $procurementUser = User::query()->firstOrCreate(
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

        $salesUser = User::query()->firstOrCreate(
            ['email' => 'sales@structra.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $headOffice->id,
                'role_id' => $salesRole->id,
                'name' => 'Yaw Asiedu',
                'phone' => '+233 24 000 0004',
                'job_title' => 'Estimating Lead',
                'password' => 'Structra2026',
            ],
        );

        $siteUser = User::query()->firstOrCreate(
            ['email' => 'site@structra.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $headOffice->id,
                'role_id' => $siteRole->id,
                'name' => 'Akosua Danquah',
                'phone' => '+233 24 000 0005',
                'job_title' => 'Site Engineer',
                'password' => 'Structra2026',
            ],
        );

        $financeUser = User::query()->firstOrCreate(
            ['email' => 'finance@structra.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $headOffice->id,
                'role_id' => $financeRole->id,
                'name' => 'Abena Nkrumah',
                'phone' => '+233 24 000 0006',
                'job_title' => 'Finance Manager',
                'password' => 'Structra2026',
            ],
        );

        $qhseUser = User::query()->firstOrCreate(
            ['email' => 'qhse@structra.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $headOffice->id,
                'role_id' => $qhseRole->id,
                'name' => 'Kofi Agyeman',
                'phone' => '+233 24 000 0007',
                'job_title' => 'QHSE Manager',
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

        DrawingMarkup::query()->firstOrCreate(
            ['drawing_id' => $drawing->id, 'comment' => 'Coordinate riser opening with MEP sleeve schedule before AFC issue.'],
            [
                'company_id' => $company->id,
                'drawing_revision_id' => $drawing->revisions()->where('revision_code', 'P02')->value('id'),
                'author_id' => $projectDirector->id,
                'markup_type' => 'pin',
                'x' => 0.42,
                'y' => 0.36,
                'width' => 0.08,
                'height' => 0.06,
                'status' => 'open',
            ],
        );

        DrawingReview::query()->firstOrCreate(
            ['drawing_id' => $drawing->id, 'decision' => 'changes_required'],
            [
                'company_id' => $company->id,
                'drawing_revision_id' => $drawing->revisions()->where('revision_code', 'P02')->value('id'),
                'reviewer_id' => $projectDirector->id,
                'comments' => 'Resolve marked riser coordination note, then reissue for approval.',
                'reviewed_at' => now()->subDays(2),
            ],
        );

        $lead = Lead::query()->firstOrCreate(
            ['company_id' => $company->id, 'lead_number' => 'LEAD-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'assigned_to' => $salesUser->id,
                'company_name' => 'Volta Education Trust',
                'contact_name' => 'Dr. Selorm Mensah',
                'email' => 'projects@voltaeducation.example',
                'phone' => '+233 55 000 4444',
                'source' => 'referral',
                'stage' => 'qualified',
                'estimated_value' => 6200000,
                'currency' => 'GHS',
                'next_follow_up_at' => now()->addDays(3),
                'notes' => 'Prospective 3-storey school block and admin wing.',
                'created_by' => $salesUser->id,
                'updated_by' => $salesUser->id,
            ],
        );

        $leadClient = Client::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Volta Education Trust'],
            [
                'branch_id' => $headOffice->id,
                'type' => 'ngo',
                'contact_name' => 'Dr. Selorm Mensah',
                'email' => 'projects@voltaeducation.example',
                'phone' => '+233 55 000 4444',
                'currency' => 'GHS',
            ],
        );
        $lead->update(['client_id' => $leadClient->id]);

        $opportunity = Opportunity::query()->firstOrCreate(
            ['company_id' => $company->id, 'opportunity_number' => 'OPP-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'client_id' => $leadClient->id,
                'lead_id' => $lead->id,
                'assigned_to' => $salesUser->id,
                'name' => 'Volta School Expansion',
                'stage' => 'tender',
                'scope' => 'Classroom block, admin office, drainage, and external works.',
                'probability' => 60,
                'estimated_value' => 6200000,
                'currency' => 'GHS',
                'expected_close_date' => now()->addWeeks(6)->toDateString(),
                'created_by' => $salesUser->id,
                'updated_by' => $salesUser->id,
            ],
        );

        $tender = Tender::query()->firstOrCreate(
            ['company_id' => $company->id, 'tender_number' => 'TND-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'client_id' => $leadClient->id,
                'opportunity_id' => $opportunity->id,
                'title' => 'Volta School Expansion Tender',
                'status' => 'submitted',
                'deadline_at' => now()->addDays(14),
                'submitted_at' => now()->subDays(2),
                'value' => 6107500,
                'currency' => 'GHS',
                'checklist' => ['BOQ', 'Architectural drawings', 'Structural drawings', 'Method statement', 'Commercial return'],
                'created_by' => $salesUser->id,
                'updated_by' => $salesUser->id,
            ],
        );

        TenderRfi::query()->firstOrCreate(
            ['tender_id' => $tender->id, 'question' => 'Please confirm whether furniture supply is included in the base scope.'],
            [
                'company_id' => $company->id,
                'asked_by' => $salesUser->id,
                'responded_by' => $salesUser->id,
                'response' => 'Furniture is excluded and should be priced as an alternate.',
                'status' => 'answered',
                'due_at' => now()->addDays(4),
                'responded_at' => now()->subDay(),
            ],
        );

        $pricingItems = [
            ['C01', 'Ready-mix concrete C30', 'materials', 'm3', 650],
            ['S01', 'High yield reinforcement steel', 'materials', 'tonne', 7800],
            ['L02', 'Skilled mason labour', 'labour', 'day', 180],
            ['E02', 'Concrete mixer hire', 'equipment', 'day', 420],
        ];

        foreach ($pricingItems as [$costCode, $description, $category, $unit, $unitCost]) {
            PricingItem::query()->firstOrCreate(
                ['company_id' => $company->id, 'description' => $description],
                [
                    'branch_id' => $headOffice->id,
                    'cost_code' => $costCode,
                    'category' => $category,
                    'unit' => $unit,
                    'unit_cost' => $unitCost,
                    'currency' => 'GHS',
                    'source' => 'internal',
                ],
            );
        }

        $estimate = Estimate::query()->firstOrCreate(
            ['company_id' => $company->id, 'estimate_number' => 'EST-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'tender_id' => $tender->id,
                'client_id' => $leadClient->id,
                'title' => 'Volta School Expansion Base Estimate',
                'status' => 'approved',
                'scenario_name' => 'Base',
                'currency' => 'GHS',
                'overhead_percent' => 8,
                'profit_percent' => 12,
                'tax_percent' => 0,
                'valid_until' => now()->addMonth()->toDateString(),
                'prepared_by' => $salesUser->id,
                'approved_by' => $owner->id,
                'approved_at' => now()->subDay(),
            ],
        );

        $estimateLines = [
            ['C01', 'Concrete frame works', 'materials', 2800, 'm3', 650, 4],
            ['S01', 'Reinforcement steel supply', 'materials', 420, 'tonne', 7800, 3],
            ['L02', 'Blockwork and finishing labour', 'labour', 4800, 'day', 180, 0],
            ['E02', 'Small plant and mixer hire', 'equipment', 300, 'day', 420, 0],
        ];

        foreach ($estimateLines as [$costCode, $description, $category, $quantity, $unit, $unitCost, $markup]) {
            EstimateLine::query()->firstOrCreate(
                ['estimate_id' => $estimate->id, 'description' => $description],
                [
                    'company_id' => $company->id,
                    'cost_code' => $costCode,
                    'category' => $category,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'unit_cost' => $unitCost,
                    'markup_percent' => $markup,
                    'line_total' => round($quantity * $unitCost * (1 + ($markup / 100)), 2),
                ],
            );
        }

        $estimateSubtotal = EstimateLine::query()->where('estimate_id', $estimate->id)->sum('line_total');
        $estimateOverhead = round($estimateSubtotal * 0.08, 2);
        $estimateProfit = round(($estimateSubtotal + $estimateOverhead) * 0.12, 2);
        $estimate->forceFill([
            'subtotal' => $estimateSubtotal,
            'overhead_amount' => $estimateOverhead,
            'profit_amount' => $estimateProfit,
            'tax_amount' => 0,
            'total_amount' => $estimateSubtotal + $estimateOverhead + $estimateProfit,
        ])->save();

        $mainStore = Warehouse::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'ACC-MAIN'],
            [
                'branch_id' => $headOffice->id,
                'manager_id' => $procurementUser->id,
                'name' => 'Accra Main Store',
                'location' => 'Cantonments site yard',
            ],
        );

        $steelItem = InventoryItem::query()->firstOrCreate(
            ['company_id' => $company->id, 'sku' => 'STL-Y16'],
            [
                'branch_id' => $headOffice->id,
                'name' => 'Y16 reinforcement bar',
                'category' => 'materials',
                'unit' => 'tonne',
                'reorder_level' => 15,
                'currency' => 'GHS',
                'average_cost' => 7800,
                'quantity_on_hand' => 38,
            ],
        );

        $cementItem = InventoryItem::query()->firstOrCreate(
            ['company_id' => $company->id, 'sku' => 'CEM-50KG'],
            [
                'branch_id' => $headOffice->id,
                'name' => 'Cement 50kg bag',
                'category' => 'materials',
                'unit' => 'bag',
                'reorder_level' => 600,
                'currency' => 'GHS',
                'average_cost' => 95,
                'quantity_on_hand' => 480,
            ],
        );

        foreach ([[$steelItem, 38, 7800], [$cementItem, 480, 95]] as [$item, $quantity, $cost]) {
            InventoryStock::query()->firstOrCreate(
                ['warehouse_id' => $mainStore->id, 'inventory_item_id' => $item->id],
                [
                    'company_id' => $company->id,
                    'quantity_on_hand' => $quantity,
                    'average_cost' => $cost,
                ],
            );
        }

        StockMovement::query()->firstOrCreate(
            ['company_id' => $company->id, 'movement_number' => 'STK-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'warehouse_id' => $mainStore->id,
                'inventory_item_id' => $steelItem->id,
                'project_id' => $project->id,
                'type' => 'issue',
                'quantity' => 6,
                'unit_cost' => 7800,
                'total_cost' => 46800,
                'balance_after' => 38,
                'reason' => 'Issued to level 4 slab reinforcement team.',
                'moved_at' => now()->subDays(2),
                'created_by' => $procurementUser->id,
            ],
        );

        SupplierPriceCatalog::query()->firstOrCreate(
            ['supplier_id' => $steelSupplier->id, 'description' => 'Y16 reinforcement bar'],
            [
                'company_id' => $company->id,
                'inventory_item_id' => $steelItem->id,
                'cost_code' => 'S01',
                'unit' => 'tonne',
                'unit_price' => 7750,
                'currency' => 'GHS',
                'lead_time_days' => 10,
                'valid_from' => now()->subMonth()->toDateString(),
                'valid_to' => now()->addMonths(2)->toDateString(),
            ],
        );

        SupplierPerformanceReview::query()->firstOrCreate(
            ['supplier_id' => $cementSupplier->id, 'project_id' => $project->id],
            [
                'company_id' => $company->id,
                'reviewed_by' => $procurementUser->id,
                'rating' => 4,
                'quality_score' => 4,
                'delivery_score' => 5,
                'cost_score' => 4,
                'notes' => 'Reliable delivery windows; pricing remains competitive.',
                'reviewed_at' => now()->subDays(5),
            ],
        );

        $dailyReport = FieldDailyReport::query()->firstOrCreate(
            ['project_id' => $project->id, 'report_date' => now()->subDay()->toDateString(), 'shift' => 'day'],
            [
                'company_id' => $company->id,
                'branch_id' => $headOffice->id,
                'report_number' => 'DR-2607-00001',
                'weather' => 'Humid, light afternoon rain',
                'labour_count' => 72,
                'equipment_notes' => 'Tower crane operational. Mixer serviced before noon.',
                'progress_notes' => 'Level 4 slab reinforcement 70% complete. Blockwork continued on level 2.',
                'safety_notes' => 'Toolbox talk completed. No lost-time incidents.',
                'status' => 'submitted',
                'submitted_by' => $siteUser->id,
                'submitted_at' => now()->subHours(16),
            ],
        );

        FieldIssue::query()->firstOrCreate(
            ['project_id' => $project->id, 'title' => 'MEP sleeve locations pending confirmation'],
            [
                'company_id' => $company->id,
                'branch_id' => $headOffice->id,
                'field_daily_report_id' => $dailyReport->id,
                'reported_by' => $siteUser->id,
                'assigned_to' => $projectDirector->id,
                'description' => 'Field team requires confirmed sleeve setting out before final concrete pour.',
                'category' => 'design',
                'severity' => 'high',
                'status' => 'open',
                'gps_latitude' => 5.5834321,
                'gps_longitude' => -0.1672345,
                'due_date' => now()->addDays(2)->toDateString(),
            ],
        );

        AttendanceRecord::query()->firstOrCreate(
            ['company_id' => $company->id, 'user_id' => $siteUser->id, 'clock_in_at' => now()->startOfDay()->addHours(7)],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'clock_in_latitude' => 5.5834321,
                'clock_in_longitude' => -0.1672345,
                'status' => 'open',
                'notes' => 'Browser clock-in from site tablet.',
            ],
        );

        $employees = collect([$projectDirector, $siteUser, $financeUser, $qhseUser])->map(function (User $staff) use ($company, $headOffice) {
            return EmployeeProfile::query()->firstOrCreate(
                ['company_id' => $company->id, 'user_id' => $staff->id],
                [
                    'branch_id' => $headOffice->id,
                    'employee_number' => 'EMP-2607-'.str_pad((string) $staff->id, 5, '0', STR_PAD_LEFT),
                    'employment_type' => 'full_time',
                    'department' => str_contains(strtolower((string) $staff->job_title), 'finance') ? 'finance' : 'operations',
                    'position' => $staff->job_title,
                    'base_salary' => match ($staff->email) {
                        'pm@structra.test' => 18500,
                        'finance@structra.test' => 14500,
                        'qhse@structra.test' => 13200,
                        default => 9200,
                    },
                    'hourly_rate' => 0,
                    'currency' => 'GHS',
                    'hire_date' => now()->subYear()->toDateString(),
                    'status' => 'active',
                    'emergency_contact' => '+233 24 999 0000',
                    'bank_name' => 'GCB Bank',
                    'bank_account' => '0123456789',
                ],
            );
        });

        LeaveRequest::query()->firstOrCreate(
            ['company_id' => $company->id, 'employee_profile_id' => $employees[1]->id, 'starts_on' => now()->addWeeks(3)->toDateString()],
            [
                'user_id' => $siteUser->id,
                'leave_type' => 'annual',
                'status' => 'pending',
                'ends_on' => now()->addWeeks(3)->addDays(4)->toDateString(),
                'days' => 5,
                'reason' => 'Planned annual leave after slab pour milestone.',
            ],
        );

        $payrollRun = PayrollRun::query()->firstOrCreate(
            ['company_id' => $company->id, 'run_number' => 'PAYRUN-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'status' => 'approved',
                'currency' => 'GHS',
                'approved_by' => $financeUser->id,
                'approved_at' => now()->subDay(),
                'created_by' => $financeUser->id,
            ],
        );

        foreach ($employees as $employee) {
            $gross = (float) $employee->base_salary;
            $tax = round($gross * 0.075, 2);
            $deductions = round($gross * 0.055, 2);

            Payslip::query()->firstOrCreate(
                ['payroll_run_id' => $payrollRun->id, 'employee_profile_id' => $employee->id],
                [
                    'company_id' => $company->id,
                    'user_id' => $employee->user_id,
                    'gross_pay' => $gross,
                    'overtime_pay' => 0,
                    'allowances' => 450,
                    'deductions' => $deductions,
                    'tax_amount' => $tax,
                    'net_pay' => max(0, $gross + 450 - $deductions - $tax),
                    'status' => 'approved',
                    'metadata' => ['source' => 'seeded monthly payroll'],
                ],
            );
        }

        $payrollRun->forceFill([
            'gross_pay' => Payslip::query()->where('payroll_run_id', $payrollRun->id)->sum(DB::raw('gross_pay + overtime_pay + allowances')),
            'total_deductions' => Payslip::query()->where('payroll_run_id', $payrollRun->id)->sum(DB::raw('deductions + tax_amount')),
            'net_pay' => Payslip::query()->where('payroll_run_id', $payrollRun->id)->sum('net_pay'),
        ])->save();

        $invoice = Invoice::query()->firstOrCreate(
            ['company_id' => $company->id, 'invoice_number' => 'INV-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'client_id' => $client->id,
                'title' => 'Interim payment certificate 04',
                'status' => 'issued',
                'issue_date' => now()->subDays(7)->toDateString(),
                'due_date' => now()->addDays(21)->toDateString(),
                'currency' => 'GHS',
                'payment_status' => 'partial',
                'notes' => 'Certified progress claim for concrete frame and preliminaries.',
                'issued_by' => $financeUser->id,
                'issued_at' => now()->subDays(7),
                'created_by' => $financeUser->id,
                'updated_by' => $financeUser->id,
            ],
        );

        foreach ([['Concrete frame progress claim', 'C01', 1, 'lot', 950000, 0], ['Site preliminaries and overheads', 'O01', 1, 'lot', 180000, 0]] as [$description, $costCode, $quantity, $unit, $unitPrice, $taxRate]) {
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);

            InvoiceLine::query()->firstOrCreate(
                ['invoice_id' => $invoice->id, 'description' => $description],
                [
                    'company_id' => $company->id,
                    'cost_code' => $costCode,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'line_subtotal' => $lineSubtotal,
                    'tax_amount' => $taxAmount,
                    'line_total' => $lineSubtotal + $taxAmount,
                ],
            );
        }

        Payment::query()->firstOrCreate(
            ['company_id' => $company->id, 'payment_number' => 'PAY-2607-00001'],
            [
                'invoice_id' => $invoice->id,
                'client_id' => $client->id,
                'amount' => 400000,
                'currency' => 'GHS',
                'method' => 'bank_transfer',
                'reference' => 'GCD-IP04-001',
                'received_at' => now()->subDays(2),
                'received_by' => $financeUser->id,
                'notes' => 'Part payment received against IPC 04.',
            ],
        );

        $invoiceSubtotal = InvoiceLine::query()->where('invoice_id', $invoice->id)->sum('line_subtotal');
        $invoiceTax = InvoiceLine::query()->where('invoice_id', $invoice->id)->sum('tax_amount');
        $invoicePaid = Payment::query()->where('invoice_id', $invoice->id)->sum('amount');
        $invoiceTotal = $invoiceSubtotal + $invoiceTax;
        $invoice->forceFill([
            'subtotal' => $invoiceSubtotal,
            'tax_amount' => $invoiceTax,
            'total_amount' => $invoiceTotal,
            'amount_paid' => $invoicePaid,
            'balance_due' => max(0, $invoiceTotal - $invoicePaid),
            'payment_status' => $invoicePaid <= 0 ? 'unpaid' : ($invoicePaid >= $invoiceTotal ? 'paid' : 'partial'),
            'paid_at' => $invoicePaid >= $invoiceTotal ? now()->subDays(2) : null,
        ])->save();

        Expense::query()->firstOrCreate(
            ['company_id' => $company->id, 'expense_number' => 'EXP-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'supplier_id' => $cementSupplier->id,
                'category' => 'site_cost',
                'description' => 'Concrete pump standby charges',
                'cost_code' => 'C01',
                'amount' => 18500,
                'tax_amount' => 0,
                'currency' => 'GHS',
                'status' => 'approved',
                'incurred_on' => now()->subDays(5)->toDateString(),
                'submitted_by' => $siteUser->id,
                'approved_by' => $financeUser->id,
                'approved_at' => now()->subDays(4),
            ],
        );

        $journalEntry = JournalEntry::query()->firstOrCreate(
            ['company_id' => $company->id, 'entry_number' => 'JE-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'entry_date' => now()->subDays(7)->toDateString(),
                'reference' => $invoice->invoice_number,
                'description' => 'Recognize IPC 04 receivable.',
                'status' => 'posted',
                'total_debit' => $invoiceTotal,
                'total_credit' => $invoiceTotal,
                'posted_by' => $financeUser->id,
                'posted_at' => now()->subDays(7),
                'created_by' => $financeUser->id,
            ],
        );

        foreach ([['1200', 'Accounts receivable', $invoiceTotal, 0], ['4100', 'Construction revenue', 0, $invoiceTotal]] as [$accountCode, $accountName, $debit, $credit]) {
            JournalLine::query()->firstOrCreate(
                ['journal_entry_id' => $journalEntry->id, 'account_code' => $accountCode],
                [
                    'company_id' => $company->id,
                    'project_id' => $project->id,
                    'account_name' => $accountName,
                    'description' => $invoice->title,
                    'debit' => $debit,
                    'credit' => $credit,
                ],
            );
        }

        $towerCrane = EquipmentAsset::query()->firstOrCreate(
            ['company_id' => $company->id, 'equipment_number' => 'EQ-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'current_project_id' => $project->id,
                'name' => 'Potain tower crane MCT 85',
                'category' => 'crane',
                'make' => 'Potain',
                'model' => 'MCT 85',
                'serial_number' => 'PT-MCT85-2607',
                'ownership_type' => 'leased',
                'status' => 'assigned',
                'hourly_rate' => 680,
                'currency' => 'GHS',
                'meter_reading' => 1240,
                'next_service_due_on' => now()->addWeeks(3)->toDateString(),
                'notes' => 'Primary vertical transport for mixed-use tower.',
            ],
        );

        EquipmentAssignment::query()->firstOrCreate(
            ['company_id' => $company->id, 'assignment_number' => 'EQA-2607-00001'],
            [
                'equipment_asset_id' => $towerCrane->id,
                'project_id' => $project->id,
                'assigned_to' => $siteUser->id,
                'status' => 'active',
                'starts_at' => now()->subMonths(3),
                'meter_start' => 820,
                'notes' => 'Assigned for structural frame phase.',
                'created_by' => $projectDirector->id,
            ],
        );

        MaintenanceLog::query()->firstOrCreate(
            ['company_id' => $company->id, 'maintenance_number' => 'MTN-2607-00001'],
            [
                'equipment_asset_id' => $towerCrane->id,
                'type' => 'preventive',
                'status' => 'completed',
                'service_date' => now()->subWeek()->toDateString(),
                'completed_at' => now()->subWeek(),
                'meter_reading' => 1180,
                'cost_amount' => 7200,
                'vendor' => 'LiftSafe Ghana',
                'description' => 'Monthly crane inspection and slew ring lubrication.',
                'next_service_due_on' => now()->addWeeks(3)->toDateString(),
                'performed_by' => $siteUser->id,
                'created_by' => $siteUser->id,
            ],
        );

        FuelLog::query()->firstOrCreate(
            ['company_id' => $company->id, 'fuel_number' => 'FUEL-2607-00001'],
            [
                'equipment_asset_id' => $towerCrane->id,
                'project_id' => $project->id,
                'fuel_date' => now()->subDays(3)->toDateString(),
                'quantity' => 220,
                'unit' => 'litre',
                'unit_cost' => 15.5,
                'total_cost' => 3410,
                'meter_reading' => 1234,
                'recorded_by' => $siteUser->id,
                'notes' => 'Diesel refill for crane generator pack.',
            ],
        );

        $inspection = Inspection::query()->firstOrCreate(
            ['company_id' => $company->id, 'inspection_number' => 'INS-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'type' => 'workmanship',
                'area' => 'Level 4 slab reinforcement',
                'status' => 'failed',
                'scheduled_on' => now()->subDays(2)->toDateString(),
                'completed_at' => now()->subDay(),
                'inspector_id' => $qhseUser->id,
                'score' => 67,
                'notes' => 'Two checklist items passed; one required corrective action.',
            ],
        );

        foreach ([['Bar spacing confirmed', 'Spacing matches structural drawing S-410', 'pass'], ['Cover blocks installed', 'Minimum cover maintained', 'pass'], ['MEP sleeves coordinated', 'Sleeves must match approved MEP layout', 'fail']] as [$item, $requirement, $result]) {
            InspectionItem::query()->firstOrCreate(
                ['inspection_id' => $inspection->id, 'checklist_item' => $item],
                [
                    'company_id' => $company->id,
                    'requirement' => $requirement,
                    'result' => $result,
                    'severity' => $result === 'fail' ? 'high' : 'medium',
                    'notes' => $result === 'fail' ? 'Riser opening requires confirmation before pour.' : null,
                ],
            );
        }

        NonConformanceReport::query()->firstOrCreate(
            ['company_id' => $company->id, 'ncr_number' => 'NCR-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'inspection_id' => $inspection->id,
                'title' => 'Unconfirmed MEP sleeve location',
                'description' => 'Sleeve layout differs from the latest architectural markup.',
                'severity' => 'high',
                'status' => 'corrective_action',
                'root_cause' => 'Coordination drawing was not distributed to the rebar foreman.',
                'corrective_action' => 'Reissue coordinated sleeve setting-out and verify before concrete pour.',
                'due_date' => now()->addDays(2)->toDateString(),
                'raised_by' => $qhseUser->id,
                'assigned_to' => $projectDirector->id,
            ],
        );

        SafetyIncident::query()->firstOrCreate(
            ['company_id' => $company->id, 'incident_number' => 'HSE-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'incident_type' => 'near_miss',
                'severity' => 'medium',
                'status' => 'corrective_action',
                'occurred_at' => now()->subDays(4),
                'location' => 'Level 3 stair core',
                'description' => 'Material bundle placed close to open edge before guardrail was reinstated.',
                'immediate_action' => 'Area cordoned off and materials relocated.',
                'root_cause' => 'Temporary storage zone not marked after housekeeping shift.',
                'corrective_action' => 'Paint laydown zones and include edge storage in daily supervisor checklist.',
                'reported_by' => $siteUser->id,
                'assigned_to' => $qhseUser->id,
            ],
        );

        ToolboxTalk::query()->firstOrCreate(
            ['company_id' => $company->id, 'talk_number' => 'TBT-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'topic' => 'Working near open edges',
                'talk_date' => now()->subDay()->toDateString(),
                'presenter_id' => $qhseUser->id,
                'attendee_count' => 68,
                'summary' => 'Discussed exclusion zones, harness checks, and supervisor escalation.',
                'hazards_discussed' => ['falls from height', 'material storage', 'temporary guardrails'],
                'status' => 'completed',
            ],
        );

        SafetyObservation::query()->firstOrCreate(
            ['company_id' => $company->id, 'observation_number' => 'OBS-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'observation_type' => 'unsafe',
                'severity' => 'medium',
                'status' => 'open',
                'location' => 'Site access gate',
                'description' => 'Delivery trucks reversing without a banksman during peak hour.',
                'corrective_action' => 'Assign traffic marshal for all aggregate deliveries.',
                'observed_at' => now()->subHours(20),
                'observed_by' => $qhseUser->id,
            ],
        );

        WorkPermit::query()->firstOrCreate(
            ['company_id' => $company->id, 'permit_number' => 'PTW-2607-00001'],
            [
                'branch_id' => $headOffice->id,
                'project_id' => $project->id,
                'permit_type' => 'hot_work',
                'status' => 'approved',
                'requested_by' => $siteUser->id,
                'approved_by' => $qhseUser->id,
                'valid_from' => now()->startOfDay()->addHours(9),
                'valid_until' => now()->startOfDay()->addHours(17),
                'location' => 'Basement plant room',
                'hazards' => 'Sparks, smoke, confined access.',
                'controls' => 'Fire watcher, extinguisher, gas test, forced ventilation.',
            ],
        );

        $clientPortalUser = PortalUser::query()->firstOrCreate(
            ['company_id' => $company->id, 'email' => 'nana.owusu@goldencoast.example'],
            [
                'client_id' => $client->id,
                'user_type' => 'client',
                'name' => 'Nana Owusu',
                'phone' => '+233 55 000 1111',
                'organization' => $client->name,
                'status' => 'active',
                'invited_by' => $projectDirector->id,
            ],
        );

        $consultantPortalUser = PortalUser::query()->firstOrCreate(
            ['company_id' => $company->id, 'email' => 'designer@atelier.example'],
            [
                'user_type' => 'consultant',
                'name' => 'Efua Hammond',
                'phone' => '+233 55 000 5555',
                'organization' => 'Atelier Design Studio',
                'status' => 'active',
                'invited_by' => $projectDirector->id,
            ],
        );

        foreach ([[$clientPortalUser, 'approve', ['architectural', 'commercial']], [$consultantPortalUser, 'comment', ['architectural', 'interiors']]] as [$portalUser, $accessLevel, $disciplines]) {
            PortalAccess::query()->updateOrCreate(
                ['portal_user_id' => $portalUser->id, 'project_id' => $project->id],
                [
                    'company_id' => $company->id,
                    'access_level' => $accessLevel,
                    'disciplines' => $disciplines,
                    'expires_at' => now()->addMonths(6),
                    'granted_by' => $projectDirector->id,
                ],
            );
        }

        $contractDocument = Document::query()->where('company_id', $company->id)->where('document_number', 'DOC-2607-00001')->first();

        ClientApproval::query()->firstOrCreate(
            ['company_id' => $company->id, 'approval_number' => 'CAP-2607-00001'],
            [
                'portal_user_id' => $clientPortalUser->id,
                'project_id' => $project->id,
                'drawing_id' => $drawing->id,
                'document_id' => $contractDocument?->id,
                'title' => 'Approve level 4 architectural layout',
                'status' => 'submitted',
                'due_date' => now()->addDays(5)->toDateString(),
                'submitted_at' => now()->subDay(),
                'created_by' => $projectDirector->id,
            ],
        );

        ConsultantSubmittal::query()->firstOrCreate(
            ['company_id' => $company->id, 'submittal_number' => 'SUB-2607-00001'],
            [
                'portal_user_id' => $consultantPortalUser->id,
                'project_id' => $project->id,
                'drawing_id' => $drawing->id,
                'document_id' => null,
                'title' => 'Interior lobby finish board',
                'discipline' => 'interiors',
                'status' => 'in_review',
                'due_date' => now()->addDays(7)->toDateString(),
                'submitted_at' => now()->subDays(2),
                'comments' => 'Review material availability and fire rating certificates.',
                'created_by' => $projectDirector->id,
            ],
        );

        foreach ([
            ['GH', 'Ghana', 'GHS', 'Africa/Accra', 'VAT'],
            ['NG', 'Nigeria', 'NGN', 'Africa/Lagos', 'VAT'],
            ['KE', 'Kenya', 'KES', 'Africa/Nairobi', 'VAT'],
            ['ZA', 'South Africa', 'ZAR', 'Africa/Johannesburg', 'VAT'],
            ['GB', 'United Kingdom', 'GBP', 'Europe/London', 'VAT'],
            ['US', 'United States', 'USD', 'America/New_York', 'Sales Tax'],
        ] as [$iso2, $name, $currency, $timezone, $taxLabel]) {
            LocalizationCountry::query()->firstOrCreate(
                ['iso2' => $iso2],
                [
                    'name' => $name,
                    'currency' => $currency,
                    'timezone' => $timezone,
                    'tax_label' => $taxLabel,
                    'is_active' => true,
                    'metadata' => ['construction_market' => true],
                ],
            );
        }

        CompanyLocalizationSetting::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'base_country' => 'GH',
                'base_currency' => 'GHS',
                'enabled_countries' => ['GH', 'NG', 'KE'],
                'enabled_currencies' => ['GHS', 'NGN', 'KES', 'USD'],
                'tax_rounding_mode' => 'line',
                'date_format' => 'Y-m-d',
            ],
        );

        foreach ([['GH', 'Ghana VAT Standard', 15], ['NG', 'Nigeria VAT Standard', 7.5], ['KE', 'Kenya VAT Standard', 16]] as [$country, $name, $rate]) {
            TaxRate::query()->firstOrCreate(
                ['company_id' => $company->id, 'country' => $country, 'name' => $name],
                [
                    'tax_type' => 'vat',
                    'rate_percent' => $rate,
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'is_default' => $country === 'GH',
                ],
            );
        }

        foreach ([['GHS', 'USD', 0.073], ['GHS', 'NGN', 116.5], ['GHS', 'KES', 9.4], ['USD', 'GHS', 13.7]] as [$base, $quote, $rate]) {
            ExchangeRate::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'base_currency' => $base,
                    'quote_currency' => $quote,
                    'rate_date' => now()->toDateString(),
                ],
                [
                    'rate' => $rate,
                    'source' => 'manual_seed',
                ],
            );
        }

        AiInsight::query()->updateOrCreate(
            ['company_id' => $company->id, 'source_key' => 'project-risk-'.$project->id],
            [
                'project_id' => $project->id,
                'category' => 'project_risk',
                'severity' => 'high',
                'title' => 'Golden Coast delivery risk trend',
                'narrative' => 'Late design coordination and open field issues are increasing the probability of schedule slippage.',
                'recommendation' => 'Resolve MEP sleeve coordination and rebaseline the next two-week lookahead.',
                'signals' => [
                    'late_tasks' => 1,
                    'open_field_issues' => 1,
                    'health_status' => $project->health_status,
                    'progress_percent' => $project->progress_percent,
                ],
                'confidence_score' => 84,
                'status' => 'open',
                'source' => 'structra_ai',
                'detected_at' => now()->subHours(3),
                'created_by' => $projectDirector->id,
            ],
        );

        AiInsight::query()->updateOrCreate(
            ['company_id' => $company->id, 'source_key' => 'low-stock-'.$cementItem->id],
            [
                'category' => 'inventory',
                'severity' => 'medium',
                'title' => 'Cement reorder exposure',
                'narrative' => 'Cement stock is below reorder level while concrete works remain active.',
                'recommendation' => 'Convert the pending concrete material requisition or confirm inbound supplier delivery.',
                'signals' => [
                    'sku' => $cementItem->sku,
                    'quantity_on_hand' => (float) $cementItem->quantity_on_hand,
                    'reorder_level' => (float) $cementItem->reorder_level,
                ],
                'confidence_score' => 91,
                'status' => 'open',
                'source' => 'structra_ai',
                'detected_at' => now()->subHours(2),
                'created_by' => $procurementUser->id,
            ],
        );

        PredictiveForecast::query()->updateOrCreate(
            ['company_id' => $company->id, 'source_key' => 'cost-forecast-'.$project->id],
            [
                'project_id' => $project->id,
                'forecast_number' => 'FCST-2607-00001',
                'forecast_type' => 'cost',
                'period_label' => now()->format('Y-m'),
                'baseline_value' => BudgetLine::query()->where('project_id', $project->id)->sum('budget_amount'),
                'forecast_value' => BudgetLine::query()->where('project_id', $project->id)->sum('forecast_amount'),
                'variance_value' => BudgetLine::query()->where('project_id', $project->id)->sum('forecast_amount') - BudgetLine::query()->where('project_id', $project->id)->sum('budget_amount'),
                'confidence_score' => 82,
                'drivers' => ['committed_total' => $project->committed_total, 'actual_cost' => $project->actual_cost],
                'status' => 'current',
                'generated_at' => now()->subHours(3),
                'created_by' => $projectDirector->id,
            ],
        );

        PredictiveForecast::query()->updateOrCreate(
            ['company_id' => $company->id, 'source_key' => 'cash-flow-30-day'],
            [
                'forecast_number' => 'FCST-2607-00002',
                'forecast_type' => 'cash_flow',
                'period_label' => 'next_30_days',
                'baseline_value' => (float) $invoice->balance_due,
                'forecast_value' => (float) $invoice->balance_due - 18500,
                'variance_value' => -18500,
                'confidence_score' => 76,
                'drivers' => ['open_receivables' => (float) $invoice->balance_due, 'approved_expenses' => 18500],
                'status' => 'current',
                'generated_at' => now()->subHours(3),
                'created_by' => $financeUser->id,
            ],
        );

        AssistantQuery::query()->firstOrCreate(
            ['company_id' => $company->id, 'question' => 'Which projects are at risk?'],
            [
                'user_id' => $owner->id,
                'intent' => 'risk',
                'answer' => 'Golden Coast Mixed-Use Tower is currently at risk because of late design coordination and open field issues.',
                'filters' => [],
                'data_sources' => ['projects', 'ai_insights'],
                'result_payload' => ['project_id' => $project->id, 'health_status' => $project->health_status],
                'confidence_score' => 88,
                'answered_at' => now()->subHours(2),
            ],
        );

        $biDashboard = BiDashboard::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'executive-command-centre'],
            [
                'name' => 'Executive Command Centre',
                'audience' => 'executive',
                'refresh_interval' => 'daily',
                'filters' => ['branch_id' => $headOffice->id],
                'is_default' => true,
                'created_by' => $owner->id,
            ],
        );

        foreach ([['Portfolio Value', 'metric', 'contract_value'], ['Accounts Receivable', 'metric', 'accounts_receivable'], ['Cost By Category', 'bar', 'cost_by_category'], ['Insight Severity', 'pie', 'insights_by_severity']] as $index => [$title, $type, $metricKey]) {
            BiWidget::query()->firstOrCreate(
                ['bi_dashboard_id' => $biDashboard->id, 'metric_key' => $metricKey],
                [
                    'company_id' => $company->id,
                    'title' => $title,
                    'widget_type' => $type,
                    'configuration' => ['color' => $index % 2 === 0 ? 'blue' : 'green'],
                    'position' => $index + 1,
                ],
            );
        }

        MetricSnapshot::query()->firstOrCreate(
            ['company_id' => $company->id, 'snapshot_number' => 'BI-2607-00001'],
            [
                'period_label' => now()->format('Y-m'),
                'snapshot_date' => now()->toDateString(),
                'metrics' => [
                    'active_projects' => 1,
                    'contract_value' => (float) $project->contract_value,
                    'budget_total' => BudgetLine::query()->where('project_id', $project->id)->sum('budget_amount'),
                    'actual_cost' => BudgetLine::query()->where('project_id', $project->id)->sum('actual_amount'),
                    'accounts_receivable' => (float) $invoice->balance_due,
                    'open_ai_insights' => 2,
                ],
                'created_by' => $owner->id,
            ],
        );

        foreach ([['Project overrun monitor', 'project_overrun', ['threshold_percent' => 0], 'high'], ['Low stock monitor', 'low_stock', ['compare' => 'quantity_on_hand <= reorder_level'], 'medium'], ['Open QHSE monitor', 'hse_open', ['include_ncrs' => true, 'include_incidents' => true], 'high']] as [$name, $ruleType, $conditions, $severity]) {
            AutomationRule::query()->firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                [
                    'rule_type' => $ruleType,
                    'trigger_event' => 'daily',
                    'conditions' => $conditions,
                    'actions' => ['type' => 'create_insight', 'recommendation' => 'Assign owner and close the matched action.'],
                    'severity' => $severity,
                    'is_active' => true,
                    'last_run_at' => now()->subDay(),
                    'created_by' => $projectDirector->id,
                ],
            );
        }

        IntegrationConnector::query()->firstOrCreate(
            ['company_id' => $company->id, 'provider' => 'xero', 'name' => 'Xero Finance Sandbox'],
            [
                'category' => 'accounting',
                'status' => 'connected',
                'settings' => ['sync_invoices' => true, 'sync_payments' => true],
                'encrypted_credentials' => ['tenant_id' => 'xero-demo-tenant', 'client_id' => 'demo-client'],
                'last_tested_at' => now()->subDay(),
                'connected_at' => now()->subDay(),
                'created_by' => $financeUser->id,
            ],
        );

        WebhookSubscription::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Finance Events Webhook'],
            [
                'event_type' => 'invoice.issued',
                'target_url' => 'https://example.com/structra/webhooks',
                'secret' => 'structra-phase4-secret',
                'is_active' => true,
                'last_dispatched_at' => now()->subDay(),
                'created_by' => $financeUser->id,
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
