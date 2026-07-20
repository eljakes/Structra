<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\Drawing;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructraPhaseThreeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_invoices_payments_expenses_and_journals_work(): void
    {
        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'name' => 'Finance Client',
        ]);

        $project = Project::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'code' => 'PRJ-FIN-001',
            'name' => 'Finance Test Project',
        ]);

        $invoiceId = $this->postJson('/api/v1/finance/invoices', [
            'project_id' => $project->id,
            'client_id' => $client->id,
            'title' => 'Interim claim',
            'due_date' => now()->addDays(30)->toDateString(),
            'lines' => [
                [
                    'description' => 'Certified works',
                    'cost_code' => 'C01',
                    'quantity' => 10,
                    'unit' => 'm3',
                    'unit_price' => 100,
                    'tax_rate' => 5,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('invoice.total_amount', '1050.00')
            ->json('invoice.id');

        $this->postJson("/api/v1/finance/invoices/{$invoiceId}/issue")
            ->assertOk()
            ->assertJsonPath('invoice.status', 'issued');

        $this->postJson("/api/v1/finance/invoices/{$invoiceId}/payments", [
            'amount' => 500,
            'method' => 'bank_transfer',
            'reference' => 'TEST-PAY-001',
        ])
            ->assertCreated()
            ->assertJsonPath('invoice.payment_status', 'partial')
            ->assertJsonPath('invoice.balance_due', '550.00');

        $this->postJson("/api/v1/finance/invoices/{$invoiceId}/payments", [
            'amount' => 550,
            'method' => 'bank_transfer',
            'reference' => 'TEST-PAY-002',
        ])
            ->assertCreated()
            ->assertJsonPath('invoice.payment_status', 'paid')
            ->assertJsonPath('invoice.balance_due', '0.00');

        $expenseId = $this->postJson('/api/v1/finance/expenses', [
            'project_id' => $project->id,
            'description' => 'Site petty cash',
            'amount' => 250,
            'tax_amount' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('expense.status', 'submitted')
            ->json('expense.id');

        $this->postJson("/api/v1/finance/expenses/{$expenseId}/review", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('expense.status', 'approved');

        $this->postJson('/api/v1/finance/journal-entries', [
            'entry_date' => now()->toDateString(),
            'reference' => 'JE-TEST',
            'status' => 'posted',
            'lines' => [
                ['account_code' => '1200', 'account_name' => 'Accounts receivable', 'debit' => 1050, 'credit' => 0],
                ['account_code' => '4100', 'account_name' => 'Construction revenue', 'debit' => 0, 'credit' => 1050],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('journal_entry.status', 'posted')
            ->assertJsonCount(2, 'journal_entry.lines');
    }

    public function test_people_payroll_and_leave_workflows_work(): void
    {
        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $worker = User::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'role_id' => $user->role_id,
            'name' => 'Payroll Worker',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Structra2026',
        ]);

        $employeeId = $this->postJson('/api/v1/people/employees', [
            'user_id' => $worker->id,
            'branch_id' => $branch->id,
            'position' => 'Site Supervisor',
            'base_salary' => 6000,
        ])
            ->assertCreated()
            ->assertJsonPath('employee.status', 'active')
            ->json('employee.id');

        $leaveId = $this->postJson('/api/v1/people/leave-requests', [
            'employee_profile_id' => $employeeId,
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeek()->addDays(2)->toDateString(),
            'reason' => 'Family event',
        ])
            ->assertCreated()
            ->assertJsonPath('leave_request.status', 'pending')
            ->json('leave_request.id');

        $this->postJson("/api/v1/people/leave-requests/{$leaveId}/review", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('leave_request.status', 'approved');

        $runId = $this->postJson('/api/v1/people/payroll-runs', [
            'branch_id' => $branch->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'payslips' => [
                [
                    'employee_profile_id' => $employeeId,
                    'gross_pay' => 6000,
                    'allowances' => 500,
                    'deductions' => 100,
                    'tax_amount' => 400,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('payroll_run.net_pay', '6000.00')
            ->json('payroll_run.id');

        $this->postJson("/api/v1/people/payroll-runs/{$runId}/approve")
            ->assertOk()
            ->assertJsonPath('payroll_run.status', 'approved');

        $this->postJson("/api/v1/people/payroll-runs/{$runId}/approve", ['status' => 'paid'])
            ->assertOk()
            ->assertJsonPath('payroll_run.status', 'paid')
            ->assertJsonPath('payroll_run.payslips.0.status', 'paid');
    }

    public function test_equipment_assignment_maintenance_and_fuel_work(): void
    {
        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $project = Project::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'code' => 'PRJ-EQ-001',
            'name' => 'Equipment Test Project',
        ]);

        $assetId = $this->postJson('/api/v1/equipment/assets', [
            'branch_id' => $branch->id,
            'name' => 'Excavator CAT 320',
            'category' => 'earthworks',
            'meter_reading' => 100,
            'hourly_rate' => 350,
        ])
            ->assertCreated()
            ->assertJsonPath('asset.status', 'available')
            ->json('asset.id');

        $assignmentId = $this->postJson("/api/v1/equipment/assets/{$assetId}/assign", [
            'project_id' => $project->id,
            'meter_start' => 100,
        ])
            ->assertCreated()
            ->assertJsonPath('asset.status', 'assigned')
            ->json('assignment.id');

        $this->postJson("/api/v1/equipment/assets/{$assetId}/fuel-logs", [
            'project_id' => $project->id,
            'quantity' => 50,
            'unit_cost' => 12,
            'meter_reading' => 108,
        ])
            ->assertCreated()
            ->assertJsonPath('fuel_log.total_cost', '600.00');

        $this->postJson("/api/v1/equipment/assignments/{$assignmentId}/release", [
            'meter_end' => 110,
        ])
            ->assertOk()
            ->assertJsonPath('assignment.status', 'completed')
            ->assertJsonPath('assignment.asset.status', 'available');

        $this->postJson("/api/v1/equipment/assets/{$assetId}/maintenance", [
            'status' => 'completed',
            'service_date' => now()->toDateString(),
            'meter_reading' => 112,
            'cost_amount' => 900,
            'description' => 'Oil and filter change',
        ])
            ->assertCreated()
            ->assertJsonPath('asset.status', 'available')
            ->assertJsonPath('maintenance.status', 'completed');
    }

    public function test_quality_and_safety_workflows_work(): void
    {
        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $project = Project::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'code' => 'PRJ-QA-001',
            'name' => 'Quality Test Project',
        ]);

        $inspectionId = $this->postJson("/api/v1/projects/{$project->id}/inspections", [
            'type' => 'quality',
            'area' => 'Level 1 columns',
            'items' => [
                ['checklist_item' => 'Rebar spacing', 'requirement' => 'Drawing S-102', 'result' => 'pass'],
                ['checklist_item' => 'Concrete cover', 'requirement' => 'Project specification', 'result' => 'fail', 'severity' => 'high'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('inspection.type', 'quality')
            ->assertJsonCount(2, 'inspection.items')
            ->json('inspection.id');

        $this->postJson("/api/v1/compliance/inspections/{$inspectionId}/complete")
            ->assertOk()
            ->assertJsonPath('inspection.status', 'failed')
            ->assertJsonPath('inspection.score', 50);

        $ncrId = $this->postJson("/api/v1/projects/{$project->id}/ncrs", [
            'inspection_id' => $inspectionId,
            'title' => 'Insufficient cover',
            'department' => 'qa',
            'category' => 'reinforcement',
            'location' => 'Level 1 grid B3',
            'contractor' => 'Main Works Contractor',
            'reference_documents' => ['Drawing S-102', 'Concrete specification'],
            'evidence' => ['cover-meter-photo.jpg'],
            'root_cause' => 'Poor workmanship',
            'corrective_action' => 'Chip out and reinstate cover to specification.',
            'preventive_action' => 'Brief steel fixing team before next pour.',
            'severity' => 'high',
        ])
            ->assertCreated()
            ->assertJsonPath('ncr.status', 'open')
            ->assertJsonPath('ncr.category', 'reinforcement')
            ->assertJsonPath('ncr.location', 'Level 1 grid B3')
            ->assertJsonPath('ncr.reference_documents.0', 'Drawing S-102')
            ->json('ncr.id');

        $this->postJson("/api/v1/compliance/ncrs/{$ncrId}/close", [
            'corrective_action' => 'Chipped and reinstated cover to specification.',
            'preventive_action' => 'Added hold point before concrete pour.',
            'verification_notes' => 'QA verified cover depth against specification.',
        ])
            ->assertOk()
            ->assertJsonPath('ncr.status', 'closed')
            ->assertJsonPath('ncr.preventive_action', 'Added hold point before concrete pour.');

        $incidentId = $this->postJson('/api/v1/safety/incidents', [
            'project_id' => $project->id,
            'description' => 'Near miss during lifting activity.',
            'severity' => 'high',
        ])
            ->assertCreated()
            ->assertJsonPath('incident.status', 'reported')
            ->json('incident.id');

        $this->postJson("/api/v1/safety/incidents/{$incidentId}/close", [
            'root_cause' => 'Lift zone not barricaded.',
            'corrective_action' => 'Updated lift plan and exclusion controls.',
        ])
            ->assertOk()
            ->assertJsonPath('incident.status', 'closed');

        $this->postJson('/api/v1/safety/toolbox-talks', [
            'project_id' => $project->id,
            'topic' => 'Lifting exclusion zones',
            'attendee_count' => 18,
        ])->assertCreated();

        $observationId = $this->postJson('/api/v1/safety/observations', [
            'project_id' => $project->id,
            'description' => 'Open trench without signage.',
        ])
            ->assertCreated()
            ->json('observation.id');

        $this->postJson("/api/v1/safety/observations/{$observationId}/close", [
            'corrective_action' => 'Installed signage and barricades.',
        ])
            ->assertOk()
            ->assertJsonPath('observation.status', 'closed');

        $permitId = $this->postJson('/api/v1/safety/permits', [
            'project_id' => $project->id,
            'permit_type' => 'hot_work',
            'location' => 'Plant room',
        ])
            ->assertCreated()
            ->assertJsonPath('permit.status', 'submitted')
            ->json('permit.id');

        $this->postJson("/api/v1/safety/permits/{$permitId}/transition", ['status' => 'approved'])->assertOk();
        $this->postJson("/api/v1/safety/permits/{$permitId}/transition", ['status' => 'active'])->assertOk();
        $this->postJson("/api/v1/safety/permits/{$permitId}/transition", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('permit.status', 'closed');
    }

    public function test_client_and_consultant_portal_workflows_work(): void
    {
        [$user, $branch] = $this->tenantUser();
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'name' => 'Portal Client',
        ]);

        $project = Project::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'code' => 'PRJ-PORT-001',
            'name' => 'Portal Test Project',
        ]);

        $drawing = Drawing::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'drawing_number' => 'A-900',
            'title' => 'Portal drawing',
            'discipline' => 'architectural',
            'status' => 'issued_for_review',
        ]);

        $document = Document::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'document_number' => 'DOC-PORT-001',
            'title' => 'Portal document',
            'document_type' => 'contract',
            'repository_scope' => 'project',
        ]);

        $portalUserId = $this->postJson('/api/v1/portals/users', [
            'client_id' => $client->id,
            'user_type' => 'client',
            'name' => 'Client Reviewer',
            'email' => 'reviewer@example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('portal_user.status', 'active')
            ->json('portal_user.id');

        $this->postJson("/api/v1/portals/users/{$portalUserId}/access", [
            'project_id' => $project->id,
            'access_level' => 'approve',
            'disciplines' => ['architectural'],
        ])
            ->assertCreated()
            ->assertJsonPath('access.access_level', 'approve');

        $approvalId = $this->postJson("/api/v1/projects/{$project->id}/client-approvals", [
            'portal_user_id' => $portalUserId,
            'drawing_id' => $drawing->id,
            'document_id' => $document->id,
            'title' => 'Approve portal drawing',
        ])
            ->assertCreated()
            ->assertJsonPath('client_approval.status', 'submitted')
            ->json('client_approval.id');

        $this->postJson("/api/v1/portals/client-approvals/{$approvalId}/review", [
            'status' => 'approved',
            'decision_notes' => 'Accepted.',
        ])
            ->assertOk()
            ->assertJsonPath('client_approval.status', 'approved');

        $submittalId = $this->postJson("/api/v1/projects/{$project->id}/consultant-submittals", [
            'portal_user_id' => $portalUserId,
            'drawing_id' => $drawing->id,
            'title' => 'Consultant detail package',
            'discipline' => 'architectural',
        ])
            ->assertCreated()
            ->assertJsonPath('consultant_submittal.status', 'submitted')
            ->json('consultant_submittal.id');

        $this->postJson("/api/v1/portals/consultant-submittals/{$submittalId}/review", [
            'status' => 'approved',
            'comments' => 'Reviewed for construction coordination.',
        ])
            ->assertOk()
            ->assertJsonPath('consultant_submittal.status', 'approved');
    }

    private function tenantUser(): array
    {
        $company = Company::query()->create([
            'name' => 'Phase Three Build Co',
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
