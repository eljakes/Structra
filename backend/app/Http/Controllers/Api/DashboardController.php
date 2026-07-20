<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\BudgetLine;
use App\Models\ClientApproval;
use App\Models\ConsultantSubmittal;
use App\Models\Document;
use App\Models\Drawing;
use App\Models\EmployeeProfile;
use App\Models\EquipmentAsset;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\FieldDailyReport;
use App\Models\FieldIssue;
use App\Models\Inspection;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\NonConformanceReport;
use App\Models\Opportunity;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\SafetyIncident;
use App\Models\Tender;
use App\Models\WorkPermit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $portfolio = Project::query()
            ->forCompany($companyId)
            ->selectRaw('count(*) as total_projects')
            ->selectRaw("sum(case when status = 'active' then 1 else 0 end) as active_projects")
            ->selectRaw("sum(case when health_status = 'critical' then 1 else 0 end) as critical_projects")
            ->selectRaw('coalesce(sum(contract_value), 0) as contract_value')
            ->selectRaw('coalesce(sum(budget_total), 0) as budget_total')
            ->selectRaw('coalesce(sum(committed_total), 0) as committed_total')
            ->selectRaw('coalesce(sum(actual_cost), 0) as actual_cost')
            ->selectRaw('coalesce(avg(progress_percent), 0) as average_progress')
            ->first();

        $lateTasks = ProjectTask::query()
            ->forCompany($companyId)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        $pendingApprovals = PurchaseRequisition::query()
            ->forCompany($companyId)
            ->where('status', 'submitted')
            ->count();

        $issuedPoValue = PurchaseOrder::query()
            ->forCompany($companyId)
            ->whereIn('status', ['issued', 'approved', 'delivered', 'closed'])
            ->sum('total_amount');

        return response()->json([
            'kpis' => [
                'total_projects' => (int) ($portfolio->total_projects ?? 0),
                'active_projects' => (int) ($portfolio->active_projects ?? 0),
                'critical_projects' => (int) ($portfolio->critical_projects ?? 0),
                'contract_value' => (float) ($portfolio->contract_value ?? 0),
                'budget_total' => (float) ($portfolio->budget_total ?? 0),
                'committed_total' => (float) ($portfolio->committed_total ?? 0),
                'actual_cost' => (float) ($portfolio->actual_cost ?? 0),
                'issued_po_value' => (float) $issuedPoValue,
                'variance' => (float) (($portfolio->budget_total ?? 0) - ($portfolio->actual_cost ?? 0)),
                'average_progress' => round((float) ($portfolio->average_progress ?? 0), 1),
                'late_tasks' => $lateTasks,
                'pending_approvals' => $pendingApprovals,
                'open_leads' => Lead::query()->forCompany($companyId)->whereNotIn('stage', ['won', 'lost'])->count(),
                'active_tenders' => Tender::query()->forCompany($companyId)->whereIn('status', ['draft', 'submitted', 'pending'])->count(),
                'open_field_issues' => FieldIssue::query()->forCompany($companyId)->whereNotIn('status', ['resolved', 'closed'])->count(),
                'reorder_alerts' => InventoryItem::query()->forCompany($companyId)->whereColumn('quantity_on_hand', '<=', 'reorder_level')->count(),
                'clocked_in' => AttendanceRecord::query()->forCompany($companyId)->where('status', 'open')->count(),
                'accounts_receivable' => (float) Invoice::query()->forCompany($companyId)->whereNotIn('payment_status', ['paid'])->sum('balance_due'),
                'payroll_liability' => (float) PayrollRun::query()->forCompany($companyId)->whereIn('status', ['draft', 'approved'])->sum('net_pay'),
                'equipment_available' => EquipmentAsset::query()->forCompany($companyId)->where('status', 'available')->count(),
                'open_ncrs' => NonConformanceReport::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->count(),
                'open_incidents' => SafetyIncident::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->count(),
                'portal_reviews' => ClientApproval::query()->forCompany($companyId)->where('status', 'submitted')->count()
                    + ConsultantSubmittal::query()->forCompany($companyId)->whereIn('status', ['submitted', 'in_review'])->count(),
            ],
            'project_health' => $this->projectHealth($companyId),
            'cost_by_category' => $this->costByCategory($companyId),
            'procurement_status' => $this->procurementStatus($companyId),
            'sales_pipeline' => $this->salesPipeline($companyId),
            'field_status' => $this->fieldStatus($companyId),
            'upcoming' => $this->upcoming($companyId),
            'recent_activity' => $this->recentActivity($companyId),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        return response()->json([
            'portfolio' => Project::query()
                ->forCompany($companyId)
                ->with(['branch:id,name,code', 'client:id,name'])
                ->orderBy('status')
                ->orderBy('target_end_date')
                ->get(),
            'cost_control' => BudgetLine::query()
                ->forCompany($companyId)
                ->with('project:id,code,name,currency')
                ->orderBy('project_id')
                ->orderBy('cost_code')
                ->get()
                ->map(fn (BudgetLine $line) => [
                    'id' => $line->id,
                    'project' => $line->project,
                    'cost_code' => $line->cost_code,
                    'description' => $line->description,
                    'category' => $line->category,
                    'budget_amount' => (float) $line->budget_amount,
                    'committed_amount' => (float) $line->committed_amount,
                    'actual_amount' => (float) $line->actual_amount,
                    'forecast_amount' => (float) $line->forecast_amount,
                    'variance' => (float) $line->budget_amount - (float) $line->actual_amount,
                ]),
            'documents' => [
                'total' => Document::query()->forCompany($companyId)->count(),
                'by_type' => Document::query()
                    ->forCompany($companyId)
                    ->select('document_type', DB::raw('count(*) as total'))
                    ->groupBy('document_type')
                    ->orderByDesc('total')
                    ->get(),
                'drawings_by_status' => Drawing::query()
                    ->forCompany($companyId)
                    ->select('status', DB::raw('count(*) as total'))
                    ->groupBy('status')
                    ->get(),
            ],
            'procurement' => [
                'requisitions' => PurchaseRequisition::query()
                    ->forCompany($companyId)
                    ->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(total_estimated), 0) as value'))
                    ->groupBy('status')
                    ->get(),
                'purchase_orders' => PurchaseOrder::query()
                    ->forCompany($companyId)
                    ->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(total_amount), 0) as value'))
                    ->groupBy('status')
                    ->get(),
            ],
            'sales' => [
                'leads' => Lead::query()->forCompany($companyId)->select('stage', DB::raw('count(*) as total'), DB::raw('coalesce(sum(estimated_value), 0) as value'))->groupBy('stage')->get(),
                'opportunities' => Opportunity::query()->forCompany($companyId)->select('stage', DB::raw('count(*) as total'), DB::raw('coalesce(sum(estimated_value), 0) as value'))->groupBy('stage')->get(),
                'tenders' => Tender::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(value), 0) as value'))->groupBy('status')->get(),
                'estimates' => Estimate::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(total_amount), 0) as value'))->groupBy('status')->get(),
            ],
            'field' => [
                'daily_reports' => FieldDailyReport::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
                'issues' => FieldIssue::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
                'attendance_open' => AttendanceRecord::query()->forCompany($companyId)->where('status', 'open')->count(),
            ],
            'inventory' => [
                'items' => InventoryItem::query()->forCompany($companyId)->count(),
                'reorder_alerts' => InventoryItem::query()->forCompany($companyId)->whereColumn('quantity_on_hand', '<=', 'reorder_level')->get(),
            ],
            'finance' => [
                'invoices' => Invoice::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(total_amount), 0) as value'), DB::raw('coalesce(sum(balance_due), 0) as balance'))->groupBy('status')->get(),
                'expenses' => Expense::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(amount + tax_amount), 0) as value'))->groupBy('status')->get(),
                'receivables' => Invoice::query()->forCompany($companyId)->whereNotIn('payment_status', ['paid'])->with(['client:id,name', 'project:id,code,name'])->orderBy('due_date')->get(),
            ],
            'payroll' => [
                'employees' => EmployeeProfile::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
                'runs' => PayrollRun::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(net_pay), 0) as value'))->groupBy('status')->get(),
            ],
            'equipment' => EquipmentAsset::query()
                ->forCompany($companyId)
                ->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(hourly_rate), 0) as hourly_rate'))
                ->groupBy('status')
                ->get(),
            'quality' => [
                'inspections' => Inspection::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
                'ncrs' => NonConformanceReport::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
            ],
            'safety' => [
                'incidents' => SafetyIncident::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
                'permits' => WorkPermit::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
            ],
            'portals' => [
                'client_approvals' => ClientApproval::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
                'consultant_submittals' => ConsultantSubmittal::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
            ],
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->where('company_id', $this->companyId($request))
            ->latest('created_at')
            ->paginate((int) $request->query('per_page', 30));

        return response()->json($logs);
    }

    private function projectHealth(int $companyId)
    {
        return Project::query()
            ->forCompany($companyId)
            ->select('health_status', DB::raw('count(*) as total'))
            ->groupBy('health_status')
            ->get();
    }

    private function costByCategory(int $companyId)
    {
        return BudgetLine::query()
            ->forCompany($companyId)
            ->select('category')
            ->selectRaw('coalesce(sum(budget_amount), 0) as budget')
            ->selectRaw('coalesce(sum(committed_amount), 0) as committed')
            ->selectRaw('coalesce(sum(actual_amount), 0) as actual')
            ->groupBy('category')
            ->orderBy('category')
            ->get();
    }

    private function procurementStatus(int $companyId)
    {
        return [
            'requisitions' => PurchaseRequisition::query()
                ->forCompany($companyId)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get(),
            'purchase_orders' => PurchaseOrder::query()
                ->forCompany($companyId)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get(),
        ];
    }

    private function salesPipeline(int $companyId): array
    {
        return [
            'leads' => Lead::query()
                ->forCompany($companyId)
                ->select('stage', DB::raw('count(*) as total'), DB::raw('coalesce(sum(estimated_value), 0) as value'))
                ->groupBy('stage')
                ->get(),
            'opportunities' => Opportunity::query()
                ->forCompany($companyId)
                ->select('stage', DB::raw('count(*) as total'), DB::raw('coalesce(sum(estimated_value), 0) as value'))
                ->groupBy('stage')
                ->get(),
            'tenders' => Tender::query()
                ->forCompany($companyId)
                ->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(value), 0) as value'))
                ->groupBy('status')
                ->get(),
        ];
    }

    private function fieldStatus(int $companyId): array
    {
        return [
            'daily_reports' => FieldDailyReport::query()
                ->forCompany($companyId)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get(),
            'issues' => FieldIssue::query()
                ->forCompany($companyId)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get(),
        ];
    }

    private function upcoming(int $companyId): array
    {
        return [
            'tasks' => ProjectTask::query()
                ->forCompany($companyId)
                ->with('project:id,code,name')
                ->whereNotIn('status', ['done', 'cancelled'])
                ->whereNotNull('due_date')
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
            'requisitions' => PurchaseRequisition::query()
                ->forCompany($companyId)
                ->with('project:id,code,name')
                ->whereIn('status', ['draft', 'submitted'])
                ->whereNotNull('required_by')
                ->orderBy('required_by')
                ->limit(8)
                ->get(),
        ];
    }

    private function recentActivity(int $companyId)
    {
        return AuditLog::query()
            ->where('company_id', $companyId)
            ->latest('created_at')
            ->limit(12)
            ->get();
    }
}
