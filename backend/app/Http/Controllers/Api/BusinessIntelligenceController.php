<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceRecord;
use App\Models\BiDashboard;
use App\Models\BiWidget;
use App\Models\Branch;
use App\Models\BudgetLine;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\ConsultantSubmittal;
use App\Models\EmployeeProfile;
use App\Models\EquipmentAsset;
use App\Models\EquipmentAssignment;
use App\Models\Expense;
use App\Models\FieldIssue;
use App\Models\FuelLog;
use App\Models\GoodsReceipt;
use App\Models\Inspection;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\MaintenanceLog;
use App\Models\MetricSnapshot;
use App\Models\NonConformanceReport;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\ProcurementQualityInspection;
use App\Models\ProcurementRfq;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\SafetyIncident;
use App\Models\SafetyObservation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierQuotation;
use App\Models\ToolboxTalk;
use App\Models\WorkPermit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BusinessIntelligenceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $projects = Project::query()
            ->forCompany($companyId)
            ->with(['client:id,name', 'branch:id,name,code'])
            ->orderBy('name')
            ->get();
        $projectAnalytics = $this->projectAnalytics($companyId, $projects);
        $metrics = $this->metrics($companyId, $projectAnalytics);
        $datasets = $this->datasets($companyId, $projectAnalytics);

        return response()->json([
            'dashboards' => BiDashboard::query()->forCompany($companyId)->with('widgets')->latest()->get(),
            'snapshots' => MetricSnapshot::query()->forCompany($companyId)->latest('snapshot_date')->limit(24)->get(),
            'datasets' => $datasets,
            'metrics' => $metrics,
            'filters' => $this->filters($companyId, $projects),
            'meta' => [
                'module_name' => 'Structra Intelligence',
                'data_freshness_at' => now()->toIso8601String(),
                'currency' => $this->user($request)->company?->default_currency ?? 'GHS',
                'safety_rate_exposure_basis' => 'Incidents per 200,000 recorded labour hours',
                'health_weights' => $this->healthWeights(),
                'kpi_definitions' => $this->kpiDefinitions(),
            ],
            'executive' => $this->executive($companyId, $projectAnalytics, $metrics),
            'portfolio' => $this->portfolio($companyId, $projectAnalytics),
            'project_controls' => $this->projectControls($companyId, $projectAnalytics),
            'financial' => $this->financial($companyId),
            'commercial' => $this->commercial($companyId, $projectAnalytics),
            'procurement' => $this->procurement($companyId),
            'inventory' => $this->inventory($companyId),
            'schedule' => $this->schedule($companyId, $projectAnalytics),
            'workforce' => $this->workforce($companyId),
            'equipment' => $this->equipment($companyId),
            'quality' => $this->quality($companyId),
            'hse' => $this->hse($companyId),
            'risk' => $this->risk($companyId, $projectAnalytics),
            'sustainability' => $this->sustainability($companyId),
            'client_reporting' => $this->clientReporting($companyId, $projectAnalytics),
            'alerts' => $this->alerts($companyId, $projectAnalytics),
        ]);
    }

    public function storeDashboard(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'audience' => ['nullable', Rule::in(['executive', 'operations', 'finance', 'commercial', 'qhse'])],
            'refresh_interval' => ['nullable', Rule::in(['hourly', 'daily', 'weekly', 'monthly'])],
            'filters' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
            'widgets' => ['nullable', 'array'],
            'widgets.*.title' => ['required_with:widgets', 'string', 'max:255'],
            'widgets.*.widget_type' => ['nullable', Rule::in(['metric', 'bar', 'line', 'table', 'pie'])],
            'widgets.*.metric_key' => ['required_with:widgets', 'string', 'max:120'],
            'widgets.*.configuration' => ['nullable', 'array'],
        ]);

        $dashboard = DB::transaction(function () use ($request, $companyId, $data) {
            if ($data['is_default'] ?? false) {
                BiDashboard::query()->forCompany($companyId)->update(['is_default' => false]);
            }

            $dashboard = BiDashboard::query()->create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($companyId, $data['name']),
                'audience' => $data['audience'] ?? 'executive',
                'refresh_interval' => $data['refresh_interval'] ?? 'daily',
                'filters' => $data['filters'] ?? [],
                'is_default' => $data['is_default'] ?? false,
                'created_by' => $this->user($request)->id,
            ]);

            foreach ($data['widgets'] ?? $this->defaultWidgets() as $index => $widget) {
                BiWidget::query()->create([
                    'company_id' => $companyId,
                    'bi_dashboard_id' => $dashboard->id,
                    'title' => $widget['title'],
                    'widget_type' => $widget['widget_type'] ?? 'metric',
                    'metric_key' => $widget['metric_key'],
                    'configuration' => $widget['configuration'] ?? [],
                    'position' => $index + 1,
                ]);
            }

            return $dashboard;
        });

        return response()->json(['dashboard' => $dashboard->fresh('widgets')], 201);
    }

    public function createSnapshot(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'period_label' => ['nullable', 'string', 'max:80'],
            'snapshot_date' => ['nullable', 'date'],
        ]);

        $snapshot = MetricSnapshot::query()->create([
            'company_id' => $companyId,
            'snapshot_number' => $this->nextNumber('BI', MetricSnapshot::class, 'snapshot_number', $companyId),
            'period_label' => $data['period_label'] ?? now()->format('Y-m'),
            'snapshot_date' => $data['snapshot_date'] ?? now()->toDateString(),
            'metrics' => $this->metrics($companyId, $this->projectAnalytics($companyId)),
            'created_by' => $this->user($request)->id,
        ]);

        return response()->json(['snapshot' => $snapshot], 201);
    }

    private function metrics(int $companyId, ?array $projectAnalytics = null): array
    {
        $projectAnalytics ??= $this->projectAnalytics($companyId);
        $now = now();
        $invoiceBase = Invoice::query()->forCompany($companyId)->whereNotIn('status', ['draft', 'void']);
        $paymentsReceived = (float) Payment::query()->forCompany($companyId)->sum('amount');
        $supplierPayments = (float) SupplierPayment::query()->forCompany($companyId)->sum('amount');
        $expensesPaid = (float) Expense::query()->forCompany($companyId)->whereIn('status', ['paid', 'approved'])->sum(DB::raw('amount + tax_amount'));
        $revenueThisMonth = (float) (clone $invoiceBase)->whereBetween('issue_date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])->sum('total_amount');
        $revenueYtd = (float) (clone $invoiceBase)->whereBetween('issue_date', [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()])->sum('total_amount');
        $contractValue = (float) Project::query()->forCompany($companyId)->sum('contract_value');
        $actualCost = (float) Project::query()->forCompany($companyId)->sum('actual_cost');
        $recognizedRevenue = (float) $invoiceBase->sum('total_amount');
        $grossProfit = $recognizedRevenue - $actualCost;
        $grossMargin = $recognizedRevenue > 0 ? round(($grossProfit / $recognizedRevenue) * 100, 1) : 0;

        return [
            'active_projects' => Project::query()->forCompany($companyId)->where('status', 'active')->count(),
            'contract_value' => $contractValue,
            'current_backlog' => max(0, $contractValue - $recognizedRevenue),
            'revenue_this_month' => $revenueThisMonth,
            'revenue_year_to_date' => $revenueYtd,
            'gross_profit' => $grossProfit,
            'gross_margin' => $grossMargin,
            'cash_position' => $paymentsReceived - $supplierPayments - $expensesPaid,
            'budget_total' => (float) Project::query()->forCompany($companyId)->sum('budget_total'),
            'actual_cost' => $actualCost,
            'committed_total' => (float) Project::query()->forCompany($companyId)->sum('committed_total'),
            'accounts_receivable' => (float) Invoice::query()->forCompany($companyId)->whereNotIn('payment_status', ['paid'])->sum('balance_due'),
            'accounts_payable' => (float) SupplierInvoice::query()->forCompany($companyId)->whereNotIn('status', ['paid'])->sum('balance_due'),
            'forecast_final_cost' => (float) Project::query()->forCompany($companyId)->sum('forecast_to_complete'),
            'portfolio_budget_variance' => (float) Project::query()->forCompany($companyId)->sum(DB::raw('budget_total - actual_cost')),
            'portfolio_schedule_variance' => round(collect($projectAnalytics['projects'])->avg('schedule_variance') ?? 0, 1),
            'projects_at_risk' => collect($projectAnalytics['projects'])->whereIn('health', ['amber', 'red'])->count(),
            'issued_po_value' => (float) PurchaseOrder::query()->forCompany($companyId)->whereIn('status', ['issued', 'approved', 'delivered', 'closed'])->sum('total_amount'),
            'payroll_liability' => (float) PayrollRun::query()->forCompany($companyId)->whereIn('status', ['draft', 'approved'])->sum('net_pay'),
            'open_field_issues' => FieldIssue::query()->forCompany($companyId)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'open_ncrs' => NonConformanceReport::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->count(),
            'open_critical_ncrs' => NonConformanceReport::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->where('severity', 'critical')->count(),
            'open_safety_incidents' => SafetyIncident::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->count(),
            'lost_time_incidents' => SafetyIncident::query()->forCompany($companyId)->where('incident_type', 'lost_time_injury')->count(),
            'reorder_alerts' => InventoryItem::query()->forCompany($companyId)->whereColumn('quantity_on_hand', '<=', 'reorder_level')->count(),
            'equipment_available' => EquipmentAsset::query()->forCompany($companyId)->where('status', 'available')->count(),
            'critical_alerts' => collect($this->alerts($companyId, $projectAnalytics)['items'])->whereIn('severity', ['critical', 'high'])->count(),
        ];
    }

    private function datasets(int $companyId, ?array $projectAnalytics = null): array
    {
        $projectAnalytics ??= $this->projectAnalytics($companyId);

        return [
            'cost_by_category' => BudgetLine::query()
                ->forCompany($companyId)
                ->select('category')
                ->selectRaw('coalesce(sum(budget_amount), 0) as budget')
                ->selectRaw('coalesce(sum(committed_amount), 0) as committed')
                ->selectRaw('coalesce(sum(actual_amount), 0) as actual')
                ->groupBy('category')
                ->orderBy('category')
                ->get(),
            'project_health' => Project::query()
                ->forCompany($companyId)
                ->select('health_status', DB::raw('count(*) as total'))
                ->groupBy('health_status')
                ->get(),
            'calculated_project_health' => collect($projectAnalytics['projects'])->groupBy('health')->map(fn ($items, $key) => [
                'health' => $key,
                'total' => $items->count(),
            ])->values(),
            'receivables_by_status' => Invoice::query()
                ->forCompany($companyId)
                ->select('payment_status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(balance_due), 0) as balance'))
                ->groupBy('payment_status')
                ->get(),
            'procurement_funnel' => $this->procurementFunnel($companyId),
            'supplier_spend' => $this->supplierSpend($companyId),
        ];
    }

    private function filters(int $companyId, $projects): array
    {
        return [
            'companies' => [['id' => $this->user(request())->company_id, 'name' => $this->user(request())->company?->name]],
            'branches' => Branch::query()->forCompany($companyId)->orderBy('name')->get(['id', 'name', 'code', 'country']),
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'status' => $project->status,
                'country' => $project->country,
                'branch_id' => $project->branch_id,
                'client_id' => $project->client_id,
            ])->values(),
            'clients' => Client::query()->forCompany($companyId)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->forCompany($companyId)->orderBy('name')->get(['id', 'name']),
            'countries' => $projects->pluck('country')->filter()->unique()->values(),
            'project_statuses' => $projects->pluck('status')->filter()->unique()->values(),
            'currencies' => $projects->pluck('currency')->filter()->unique()->values(),
            'cost_codes' => BudgetLine::query()->forCompany($companyId)->whereNotNull('cost_code')->distinct()->orderBy('cost_code')->pluck('cost_code'),
            'work_breakdown_structure' => BudgetLine::query()->forCompany($companyId)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'saved_views' => [
                ['name' => 'Active projects', 'criteria' => ['project_status' => 'active']],
                ['name' => 'Projects above 10M', 'criteria' => ['contract_value_min' => 10000000]],
                ['name' => 'High cost or schedule risk', 'criteria' => ['health' => ['amber', 'red']]],
            ],
        ];
    }

    private function executive(int $companyId, array $projectAnalytics, array $metrics): array
    {
        return [
            'headline_scorecards' => [
                ['key' => 'contract_value', 'label' => 'Total contract value', 'value' => $metrics['contract_value']],
                ['key' => 'current_backlog', 'label' => 'Current backlog', 'value' => $metrics['current_backlog']],
                ['key' => 'revenue_this_month', 'label' => 'Revenue this month', 'value' => $metrics['revenue_this_month']],
                ['key' => 'revenue_year_to_date', 'label' => 'Revenue YTD', 'value' => $metrics['revenue_year_to_date']],
                ['key' => 'gross_profit', 'label' => 'Gross profit', 'value' => $metrics['gross_profit']],
                ['key' => 'gross_margin', 'label' => 'Gross margin', 'value' => $metrics['gross_margin'], 'unit' => '%'],
                ['key' => 'cash_position', 'label' => 'Cash position', 'value' => $metrics['cash_position']],
                ['key' => 'accounts_receivable', 'label' => 'Accounts receivable', 'value' => $metrics['accounts_receivable']],
                ['key' => 'accounts_payable', 'label' => 'Accounts payable', 'value' => $metrics['accounts_payable']],
                ['key' => 'forecast_final_cost', 'label' => 'Forecast final cost', 'value' => $metrics['forecast_final_cost']],
                ['key' => 'portfolio_budget_variance', 'label' => 'Portfolio budget variance', 'value' => $metrics['portfolio_budget_variance']],
                ['key' => 'portfolio_schedule_variance', 'label' => 'Portfolio schedule variance', 'value' => $metrics['portfolio_schedule_variance'], 'unit' => '%'],
                ['key' => 'active_projects', 'label' => 'Active projects', 'value' => $metrics['active_projects']],
                ['key' => 'projects_at_risk', 'label' => 'Projects at risk', 'value' => $metrics['projects_at_risk']],
                ['key' => 'lost_time_incidents', 'label' => 'Lost-time incidents', 'value' => $metrics['lost_time_incidents']],
                ['key' => 'open_critical_ncrs', 'label' => 'Open critical NCRs', 'value' => $metrics['open_critical_ncrs']],
            ],
            'health_matrix' => $projectAnalytics['projects'],
            'trends' => [
                'revenue_margin' => $this->monthlyRevenueTrend($companyId),
                'cash_flow' => $this->monthlyCashFlow($companyId),
                'contract_value_vs_earned' => collect($projectAnalytics['projects'])->map(fn (array $project): array => [
                    'project' => $project['project'],
                    'contract_value' => $project['contract_value'],
                    'earned_value' => $project['earned_value'],
                ])->values(),
            ],
            'executive_actions' => array_slice($this->alerts($companyId, $projectAnalytics)['items'], 0, 8),
        ];
    }

    private function portfolio(int $companyId, array $projectAnalytics): array
    {
        $projects = collect($projectAnalytics['projects']);

        return [
            'kpis' => [
                'projects_by_status' => Project::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
                'total_risk_exposure' => (float) $projects->sum('risk_exposure'),
                'average_cpi' => round((float) $projects->whereNotNull('cpi')->avg('cpi'), 2),
                'average_spi' => round((float) $projects->whereNotNull('spi')->avg('spi'), 2),
            ],
            'comparison' => $projects->values(),
            'rankings' => [
                'profitability' => $projects->sortByDesc('margin_percent')->take(10)->values(),
                'schedule_delay' => $projects->sortBy('schedule_variance')->take(10)->values(),
                'cash_flow_pressure' => $projects->sortBy('cash_position')->take(10)->values(),
                'safety_incidents' => $projects->sortByDesc('open_safety_incidents')->take(10)->values(),
                'ncr_closure_pressure' => $projects->sortByDesc('open_ncrs')->take(10)->values(),
                'supplier_exposure' => $projects->sortByDesc('committed_cost')->take(10)->values(),
            ],
        ];
    }

    private function projectControls(int $companyId, array $projectAnalytics): array
    {
        return [
            'earned_value' => collect($projectAnalytics['projects'])->map(fn (array $project): array => [
                'project_id' => $project['project_id'],
                'project' => $project['project'],
                'planned_value' => $project['planned_value'],
                'earned_value' => $project['earned_value'],
                'actual_cost' => $project['actual_cost'],
                'cost_variance' => $project['cost_variance'],
                'schedule_variance_value' => $project['schedule_variance_value'],
                'cpi' => $project['cpi'],
                'spi' => $project['spi'],
                'budget_at_completion' => $project['budget_total'],
                'estimate_at_completion' => $project['estimate_at_completion'],
                'estimate_to_complete' => $project['estimate_to_complete'],
                'variance_at_completion' => $project['variance_at_completion'],
                'to_complete_performance_index' => $project['tcpi'],
            ])->values(),
            'cost_code_performance' => BudgetLine::query()
                ->forCompany($companyId)
                ->with('project:id,name,code')
                ->latest()
                ->limit(80)
                ->get()
                ->map(fn (BudgetLine $line): array => [
                    'project' => $line->project?->name,
                    'cost_code' => $line->cost_code,
                    'description' => $line->description,
                    'category' => $line->category,
                    'budget' => (float) $line->budget_amount,
                    'committed' => (float) $line->committed_amount,
                    'actual' => (float) $line->actual_amount,
                    'forecast' => (float) $line->forecast_amount,
                    'variance' => (float) $line->budget_amount - (float) $line->forecast_amount,
                ]),
            'delayed_activities' => $this->delayedTasks($companyId),
            'milestone_status' => ProjectTask::query()->forCompany($companyId)->with('project:id,name,code')->latest('due_date')->limit(60)->get()->map(fn (ProjectTask $task): array => [
                'id' => $task->id,
                'project' => $task->project?->name,
                'activity' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'progress' => (int) $task->progress_percent,
                'due_date' => optional($task->due_date)->toDateString(),
                'days_variance' => $task->due_date && ! in_array($task->status, ['done', 'cancelled'], true) ? now()->startOfDay()->diffInDays($task->due_date, false) : null,
            ]),
        ];
    }

    private function financial(int $companyId): array
    {
        $invoices = Invoice::query()->forCompany($companyId)->with(['client:id,name', 'project:id,name,code'])->latest('issue_date')->limit(80)->get();
        $supplierInvoices = SupplierInvoice::query()->forCompany($companyId)->with(['supplier:id,name', 'purchaseOrder:id,po_number'])->latest('invoice_date')->limit(80)->get();
        $payments = Payment::query()->forCompany($companyId)->latest('received_at')->get();

        return [
            'revenue_profitability' => [
                'recognized_revenue' => (float) $invoices->whereNotIn('status', ['draft', 'void'])->sum('total_amount'),
                'billed_revenue' => (float) $invoices->sum('total_amount'),
                'collected_revenue' => (float) $payments->sum('amount'),
                'unbilled_revenue' => max(0, (float) Project::query()->forCompany($companyId)->sum('contract_value') - (float) $invoices->sum('total_amount')),
                'profit_by_project' => Project::query()->forCompany($companyId)->orderByDesc('contract_value')->limit(20)->get()->map(fn (Project $project): array => [
                    'project' => $project->name,
                    'contract_value' => (float) $project->contract_value,
                    'actual_cost' => (float) $project->actual_cost,
                    'gross_profit' => (float) $project->contract_value - (float) $project->actual_cost,
                    'gross_margin' => (float) $project->contract_value > 0 ? round((((float) $project->contract_value - (float) $project->actual_cost) / (float) $project->contract_value) * 100, 1) : 0,
                ]),
            ],
            'cost_analytics' => [
                'original_budget' => (float) Project::query()->forCompany($companyId)->sum('budget_total'),
                'committed_cost' => (float) Project::query()->forCompany($companyId)->sum('committed_total'),
                'actual_cost' => (float) Project::query()->forCompany($companyId)->sum('actual_cost'),
                'forecast_cost' => (float) Project::query()->forCompany($companyId)->sum('forecast_to_complete'),
                'cost_by_cost_code' => BudgetLine::query()->forCompany($companyId)->select('cost_code')->selectRaw('coalesce(sum(actual_amount),0) as actual')->selectRaw('coalesce(sum(committed_amount),0) as committed')->groupBy('cost_code')->orderByDesc('actual')->limit(20)->get(),
            ],
            'cash_flow' => [
                'cash_inflows' => (float) Payment::query()->forCompany($companyId)->sum('amount'),
                'cash_outflows' => (float) SupplierPayment::query()->forCompany($companyId)->sum('amount') + (float) Expense::query()->forCompany($companyId)->where('status', 'paid')->sum(DB::raw('amount + tax_amount')),
                'planned_vs_actual' => $this->monthlyCashFlow($companyId),
                'overdue_client_payments' => (float) Invoice::query()->forCompany($companyId)->whereNotIn('payment_status', ['paid'])->whereDate('due_date', '<', now()->toDateString())->sum('balance_due'),
            ],
            'accounts_receivable' => [
                'outstanding' => (float) $invoices->where('payment_status', '!=', 'paid')->sum('balance_due'),
                'ageing' => $this->invoiceAgeing($invoices),
                'drilldown' => $invoices->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'client' => $invoice->client?->name,
                    'project' => $invoice->project?->name,
                    'due_date' => optional($invoice->due_date)->toDateString(),
                    'status' => $invoice->status,
                    'payment_status' => $invoice->payment_status,
                    'total' => (float) $invoice->total_amount,
                    'balance' => (float) $invoice->balance_due,
                ]),
            ],
            'accounts_payable' => [
                'outstanding' => (float) $supplierInvoices->where('status', '!=', 'paid')->sum('balance_due'),
                'due_this_week' => (float) $supplierInvoices->whereBetween('due_date', [now()->startOfDay(), now()->copy()->addWeek()->endOfDay()])->sum('balance_due'),
                'drilldown' => $supplierInvoices->map(fn (SupplierInvoice $invoice): array => [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'supplier' => $invoice->supplier?->name,
                    'po' => $invoice->purchaseOrder?->po_number,
                    'status' => $invoice->status,
                    'due_date' => optional($invoice->due_date)->toDateString(),
                    'total' => (float) $invoice->total_amount,
                    'balance' => (float) $invoice->balance_due,
                ]),
            ],
        ];
    }

    private function commercial(int $companyId, array $projectAnalytics): array
    {
        return [
            'contract_kpis' => [
                'original_contract_value' => (float) Project::query()->forCompany($companyId)->sum('contract_value'),
                'revised_contract_value' => (float) Project::query()->forCompany($companyId)->sum('contract_value'),
                'approved_variations' => 0,
                'pending_variations' => 0,
                'claims_outstanding' => 0,
                'retention' => 0,
                'advance_payment' => 0,
            ],
            'certification_status' => Invoice::query()->forCompany($companyId)->with(['client:id,name', 'project:id,name,code'])->latest('issue_date')->limit(50)->get()->map(fn (Invoice $invoice): array => [
                'invoice' => $invoice->invoice_number,
                'client' => $invoice->client?->name,
                'project' => $invoice->project?->name,
                'certified_value' => (float) $invoice->total_amount,
                'payment_status' => $invoice->payment_status,
                'due_date' => optional($invoice->due_date)->toDateString(),
            ]),
            'critical_alerts' => $this->commercialAlerts($companyId),
        ];
    }

    private function procurement(int $companyId): array
    {
        $requisitions = PurchaseRequisition::query()->forCompany($companyId)->get();
        $orders = PurchaseOrder::query()->forCompany($companyId)->with(['supplier:id,name', 'project:id,name,code'])->get();
        $quotes = SupplierQuotation::query()->forCompany($companyId)->with('supplier:id,name')->get();
        $receipts = GoodsReceipt::query()->forCompany($companyId)->with(['purchaseOrder:id,po_number,expected_delivery_date', 'supplier:id,name'])->get();
        $supplierInvoices = SupplierInvoice::query()->forCompany($companyId)->get();

        return [
            'kpis' => [
                'procurement_spend' => (float) $orders->whereIn('status', ['issued', 'approved', 'delivered', 'closed'])->sum('total_amount'),
                'open_requisitions' => $requisitions->whereNotIn('status', ['converted', 'cancelled', 'rejected'])->count(),
                'pending_approvals' => $requisitions->where('status', 'submitted')->count(),
                'open_purchase_orders' => $orders->whereIn('status', ['issued', 'approved'])->count(),
                'po_value' => (float) $orders->sum('total_amount'),
                'orders_awaiting_delivery' => $orders->whereIn('delivery_status', ['pending', 'partial'])->count(),
                'late_deliveries' => $orders->filter(fn (PurchaseOrder $order): bool => $order->expected_delivery_date && $order->expected_delivery_date->isPast() && ! in_array($order->delivery_status, ['delivered'], true))->count(),
                'invoice_match_exceptions' => $supplierInvoices->whereIn('status', ['submitted', 'rejected'])->count(),
                'average_approval_time_days' => round((float) $requisitions->filter(fn ($item) => $item->submitted_at && $item->reviewed_at)->avg(fn ($item) => $item->submitted_at->diffInHours($item->reviewed_at) / 24), 1),
                'rfq_cycle_time_days' => round((float) ProcurementRfq::query()->forCompany($companyId)->whereNotNull('sent_at')->get()->avg(fn ($rfq) => $rfq->created_at->diffInHours($rfq->sent_at) / 24), 1),
            ],
            'funnel' => $this->procurementFunnel($companyId),
            'supplier_scorecards' => $this->supplierScorecards($companyId),
            'spend_by_supplier' => $this->supplierSpend($companyId),
            'spend_by_project' => $orders->groupBy('project_id')->map(fn ($items): array => [
                'project' => $items->first()->project?->name ?: 'Unassigned',
                'spend' => (float) $items->sum('total_amount'),
                'orders' => $items->count(),
            ])->values(),
            'late_delivery_drilldown' => $orders->filter(fn (PurchaseOrder $order): bool => $order->expected_delivery_date && $order->expected_delivery_date->isPast() && ! in_array($order->delivery_status, ['delivered'], true))->map(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'po' => $order->po_number,
                'supplier' => $order->supplier?->name,
                'project' => $order->project?->name,
                'expected_delivery_date' => optional($order->expected_delivery_date)->toDateString(),
                'status' => $order->status,
                'value' => (float) $order->total_amount,
            ])->values(),
            'quality_inspections' => ProcurementQualityInspection::query()->forCompany($companyId)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
            'quotations' => $quotes->map(fn (SupplierQuotation $quote): array => [
                'quotation' => $quote->quotation_number,
                'supplier' => $quote->supplier?->name,
                'status' => $quote->status,
                'total' => (float) $quote->total_amount,
                'lead_time_days' => $quote->lead_time_days,
                'recommendation_score' => $quote->recommendation_score,
            ]),
            'goods_receipts' => $receipts->map(fn (GoodsReceipt $receipt): array => [
                'grn' => $receipt->grn_number,
                'po' => $receipt->purchaseOrder?->po_number,
                'supplier' => $receipt->supplier?->name,
                'status' => $receipt->status,
                'received_date' => optional($receipt->received_date)->toDateString(),
            ]),
        ];
    }

    private function inventory(int $companyId): array
    {
        $items = InventoryItem::query()->forCompany($companyId)->get();
        $movements = StockMovement::query()->forCompany($companyId)->with('item:id,sku,name,category')->latest('moved_at')->limit(80)->get();
        $stockValue = (float) $items->sum(fn (InventoryItem $item): float => (float) $item->quantity_on_hand * (float) $item->average_cost);

        return [
            'kpis' => [
                'current_stock_value' => $stockValue,
                'stock_on_hand' => (float) $items->sum('quantity_on_hand'),
                'stockout_frequency' => $items->where('quantity_on_hand', '<=', 0)->count(),
                'reorder_requirements' => $items->filter(fn (InventoryItem $item): bool => (float) $item->quantity_on_hand <= (float) $item->reorder_level)->count(),
                'inventory_turnover' => $stockValue > 0 ? round((float) $movements->whereIn('type', ['issue', 'transfer'])->sum('total_cost') / $stockValue, 2) : 0,
                'material_consumption_value' => (float) $movements->whereIn('type', ['issue', 'transfer'])->sum('total_cost'),
                'unexplained_stock_adjustment' => (float) $movements->where('type', 'adjustment')->sum('total_cost'),
            ],
            'stock_by_category' => $items->groupBy('category')->map(fn ($group, $category): array => [
                'category' => $category ?: 'uncategorized',
                'quantity' => (float) $group->sum('quantity_on_hand'),
                'value' => (float) $group->sum(fn ($item) => (float) $item->quantity_on_hand * (float) $item->average_cost),
            ])->values(),
            'reorder_drilldown' => $items->filter(fn (InventoryItem $item): bool => (float) $item->quantity_on_hand <= (float) $item->reorder_level)->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'sku' => $item->sku,
                'item' => $item->name,
                'category' => $item->category,
                'on_hand' => (float) $item->quantity_on_hand,
                'reorder_level' => (float) $item->reorder_level,
                'average_cost' => (float) $item->average_cost,
                'value' => (float) $item->quantity_on_hand * (float) $item->average_cost,
            ])->values(),
            'movement_drilldown' => $movements->map(fn (StockMovement $movement): array => [
                'number' => $movement->movement_number,
                'item' => $movement->item?->name,
                'type' => $movement->type,
                'quantity' => (float) $movement->quantity,
                'total_cost' => (float) $movement->total_cost,
                'moved_at' => optional($movement->moved_at)->toDateTimeString(),
                'reason' => $movement->reason,
            ]),
        ];
    }

    private function schedule(int $companyId, array $projectAnalytics): array
    {
        $tasks = ProjectTask::query()->forCompany($companyId)->with('project:id,name,code')->get();

        return [
            'kpis' => [
                'planned_progress' => round((float) collect($projectAnalytics['projects'])->avg('planned_progress'), 1),
                'actual_progress' => round((float) collect($projectAnalytics['projects'])->avg('progress'), 1),
                'progress_variance' => round((float) collect($projectAnalytics['projects'])->avg('schedule_variance'), 1),
                'critical_activities' => $tasks->whereIn('priority', ['high', 'critical'])->whereNotIn('status', ['done', 'cancelled'])->count(),
                'missed_milestones' => $tasks->filter(fn (ProjectTask $task): bool => $task->due_date && $task->due_date->isPast() && ! in_array($task->status, ['done', 'cancelled'], true))->count(),
                'schedule_performance_index' => round((float) collect($projectAnalytics['projects'])->whereNotNull('spi')->avg('spi'), 2),
            ],
            'critical_path' => $tasks->whereIn('priority', ['high', 'critical'])->sortBy('due_date')->values()->map(fn (ProjectTask $task): array => [
                'id' => $task->id,
                'project' => $task->project?->name,
                'activity' => $task->title,
                'priority' => $task->priority,
                'status' => $task->status,
                'progress' => (int) $task->progress_percent,
                'due_date' => optional($task->due_date)->toDateString(),
            ]),
            'six_week_forecast' => $tasks->filter(fn (ProjectTask $task): bool => $task->due_date && $task->due_date->between(now(), now()->copy()->addWeeks(6)))->sortBy('due_date')->values()->map(fn (ProjectTask $task): array => [
                'project' => $task->project?->name,
                'activity' => $task->title,
                'status' => $task->status,
                'due_date' => optional($task->due_date)->toDateString(),
            ]),
            'schedule_variance_heatmap' => collect($projectAnalytics['projects'])->map(fn (array $project): array => [
                'project' => $project['project'],
                'variance' => $project['schedule_variance'],
                'health' => $project['schedule_variance'] < -10 ? 'red' : ($project['schedule_variance'] < -5 ? 'amber' : 'green'),
            ])->values(),
        ];
    }

    private function workforce(int $companyId): array
    {
        $employees = EmployeeProfile::query()->forCompany($companyId)->with('branch:id,name,code')->get();
        $attendance = AttendanceRecord::query()->forCompany($companyId)->get();
        $totalMinutes = (float) $attendance->sum('total_minutes');

        return [
            'kpis' => [
                'total_workforce' => $employees->count(),
                'active_employees' => $employees->where('status', 'active')->count(),
                'inactive_employees' => $employees->where('status', '!=', 'active')->count(),
                'attendance_records' => $attendance->count(),
                'labour_hours' => round($totalMinutes / 60, 1),
                'productive_hours' => round($attendance->where('status', 'closed')->sum('total_minutes') / 60, 1),
                'open_attendance' => $attendance->where('status', 'open')->count(),
                'payroll_liability' => (float) PayrollRun::query()->forCompany($companyId)->whereIn('status', ['draft', 'approved'])->sum('net_pay'),
            ],
            'workforce_by_department' => $employees->groupBy('department')->map(fn ($items, $department): array => [
                'department' => $department ?: 'unassigned',
                'employees' => $items->count(),
                'active' => $items->where('status', 'active')->count(),
            ])->values(),
            'workforce_by_branch' => $employees->groupBy('branch_id')->map(fn ($items): array => [
                'branch' => $items->first()->branch?->name ?: 'Unassigned',
                'employees' => $items->count(),
            ])->values(),
        ];
    }

    private function equipment(int $companyId): array
    {
        $assets = EquipmentAsset::query()->forCompany($companyId)->with('currentProject:id,name,code')->get();
        $maintenance = MaintenanceLog::query()->forCompany($companyId)->with('asset:id,equipment_number,name')->get();
        $fuel = FuelLog::query()->forCompany($companyId)->with('asset:id,equipment_number,name')->get();
        $assignments = EquipmentAssignment::query()->forCompany($companyId)->get();

        return [
            'kpis' => [
                'fleet_size' => $assets->count(),
                'available_equipment' => $assets->where('status', 'available')->count(),
                'in_use_equipment' => $assets->where('status', 'assigned')->count(),
                'idle_equipment' => $assets->where('status', 'available')->count(),
                'equipment_utilization' => $assets->count() > 0 ? round(($assignments->where('status', 'active')->count() / $assets->count()) * 100, 1) : 0,
                'maintenance_backlog' => $maintenance->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'fuel_consumption' => (float) $fuel->sum('quantity'),
                'fuel_cost' => (float) $fuel->sum('total_cost'),
                'maintenance_cost' => (float) $maintenance->sum('cost_amount'),
            ],
            'status_breakdown' => $assets->groupBy('status')->map(fn ($items, $status): array => ['status' => $status, 'total' => $items->count()])->values(),
            'maintenance_due' => $maintenance->whereNotIn('status', ['completed', 'cancelled'])->sortBy('service_date')->values()->map(fn (MaintenanceLog $log): array => [
                'number' => $log->maintenance_number,
                'asset' => $log->asset?->name,
                'type' => $log->type,
                'status' => $log->status,
                'service_date' => optional($log->service_date)->toDateString(),
                'cost' => (float) $log->cost_amount,
            ]),
            'underutilized_equipment' => $assets->where('status', 'available')->values()->map(fn (EquipmentAsset $asset): array => [
                'number' => $asset->equipment_number,
                'asset' => $asset->name,
                'category' => $asset->category,
                'hourly_rate' => (float) $asset->hourly_rate,
            ]),
        ];
    }

    private function quality(int $companyId): array
    {
        $inspections = Inspection::query()->forCompany($companyId)->with('project:id,name,code')->get();
        $ncrs = NonConformanceReport::query()->forCompany($companyId)->with('project:id,name,code')->get();
        $completed = $inspections->whereNotNull('completed_at');
        $passed = $completed->filter(fn (Inspection $inspection): bool => in_array($inspection->status, ['passed', 'completed', 'closed'], true) || (int) $inspection->score >= 80);

        return [
            'kpis' => [
                'inspections_conducted' => $completed->count(),
                'inspection_pass_rate' => $completed->count() > 0 ? round(($passed->count() / $completed->count()) * 100, 1) : 0,
                'open_inspections' => $inspections->whereIn('status', ['scheduled', 'open', 'in_progress'])->count(),
                'overdue_inspections' => $inspections->filter(fn (Inspection $inspection): bool => $inspection->scheduled_on && $inspection->scheduled_on->isPast() && ! $inspection->completed_at)->count(),
                'ncrs_raised' => $ncrs->count(),
                'open_ncrs' => $ncrs->where('status', '!=', 'closed')->count(),
                'critical_ncrs' => $ncrs->where('severity', 'critical')->where('status', '!=', 'closed')->count(),
                'average_ncr_closure_time_days' => round((float) $ncrs->filter(fn ($ncr) => $ncr->closed_at && $ncr->created_at)->avg(fn ($ncr) => $ncr->created_at->diffInHours($ncr->closed_at) / 24), 1),
                'reopened_ncrs' => $ncrs->whereNotNull('reopened_at')->count(),
            ],
            'ncr_by_category' => $ncrs->groupBy('category')->map(fn ($items, $category): array => ['category' => $category ?: 'uncategorized', 'total' => $items->count()])->values(),
            'ncr_ageing' => $this->ncrAgeing($ncrs),
            'inspection_register' => $inspections->sortByDesc('scheduled_on')->take(80)->values()->map(fn (Inspection $inspection): array => [
                'number' => $inspection->inspection_number,
                'project' => $inspection->project?->name,
                'type' => $inspection->type,
                'area' => $inspection->area,
                'status' => $inspection->status,
                'score' => $inspection->score,
                'scheduled_on' => optional($inspection->scheduled_on)->toDateString(),
            ]),
            'ncr_drilldown' => $ncrs->sortByDesc('created_at')->take(80)->values()->map(fn (NonConformanceReport $ncr): array => [
                'id' => $ncr->id,
                'number' => $ncr->ncr_number,
                'project' => $ncr->project?->name,
                'title' => $ncr->title,
                'category' => $ncr->category,
                'root_cause' => $ncr->root_cause,
                'severity' => $ncr->severity,
                'status' => $ncr->status,
                'due_date' => optional($ncr->due_date)->toDateString(),
            ]),
        ];
    }

    private function hse(int $companyId): array
    {
        $incidents = SafetyIncident::query()->forCompany($companyId)->with('project:id,name,code')->get();
        $observations = SafetyObservation::query()->forCompany($companyId)->with('project:id,name,code')->get();
        $toolboxTalks = ToolboxTalk::query()->forCompany($companyId)->get();
        $permits = WorkPermit::query()->forCompany($companyId)->get();
        $labourHours = (float) AttendanceRecord::query()->forCompany($companyId)->sum(DB::raw('total_minutes / 60.0'));
        $recordable = $incidents->whereIn('incident_type', ['lost_time_injury', 'medical_treatment', 'restricted_work', 'fatality'])->count();

        return [
            'exposure_basis' => 'Per 200,000 recorded labour hours',
            'kpis' => [
                'fatalities' => $incidents->where('incident_type', 'fatality')->count(),
                'lost_time_injuries' => $incidents->where('incident_type', 'lost_time_injury')->count(),
                'medical_treatment_cases' => $incidents->where('incident_type', 'medical_treatment')->count(),
                'near_misses' => $incidents->where('incident_type', 'near_miss')->count(),
                'total_recordable_incidents' => $recordable,
                'lost_time_injury_frequency_rate' => $labourHours > 0 ? round(($incidents->where('incident_type', 'lost_time_injury')->count() * 200000) / $labourHours, 2) : 0,
                'total_recordable_incident_rate' => $labourHours > 0 ? round(($recordable * 200000) / $labourHours, 2) : 0,
                'safety_observations' => $observations->count(),
                'toolbox_talks_completed' => $toolboxTalks->where('status', 'completed')->count(),
                'permit_to_work_compliance' => $permits->count() > 0 ? round(($permits->whereIn('status', ['approved', 'closed'])->count() / $permits->count()) * 100, 1) : 0,
            ],
            'incident_by_severity' => $incidents->groupBy('severity')->map(fn ($items, $severity): array => ['severity' => $severity ?: 'unknown', 'total' => $items->count()])->values(),
            'leading_vs_lagging' => [
                ['indicator' => 'Leading', 'total' => $observations->count() + $toolboxTalks->count() + $permits->count()],
                ['indicator' => 'Lagging', 'total' => $incidents->count()],
            ],
            'incident_drilldown' => $incidents->sortByDesc('occurred_at')->take(80)->values()->map(fn (SafetyIncident $incident): array => [
                'id' => $incident->id,
                'number' => $incident->incident_number,
                'project' => $incident->project?->name,
                'type' => $incident->incident_type,
                'severity' => $incident->severity,
                'status' => $incident->status,
                'location' => $incident->location,
                'occurred_at' => optional($incident->occurred_at)->toDateTimeString(),
            ]),
        ];
    }

    private function risk(int $companyId, array $projectAnalytics): array
    {
        $alerts = collect($this->alerts($companyId, $projectAnalytics)['items']);

        return [
            'kpis' => [
                'total_active_risks' => $alerts->where('status', 'open')->count(),
                'critical_risks' => $alerts->where('severity', 'critical')->count(),
                'high_risks' => $alerts->where('severity', 'high')->count(),
                'risk_exposure' => (float) collect($projectAnalytics['projects'])->sum('risk_exposure'),
                'overdue_mitigation_actions' => $alerts->where('is_overdue', true)->count(),
            ],
            'risk_heatmap' => collect($projectAnalytics['projects'])->map(fn (array $project): array => [
                'project' => $project['project'],
                'probability' => $project['risk_probability'],
                'impact' => $project['risk_impact'],
                'exposure' => $project['risk_exposure'],
                'health' => $project['health'],
            ])->values(),
            'top_risks' => $alerts->sortByDesc(fn ($item) => ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1][$item['severity']] ?? 0)->take(10)->values(),
            'risks_by_category' => $alerts->groupBy('category')->map(fn ($items, $category): array => ['category' => $category, 'total' => $items->count()])->values(),
        ];
    }

    private function sustainability(int $companyId): array
    {
        $fuel = FuelLog::query()->forCompany($companyId)->get();
        $environmentalIncidents = SafetyIncident::query()->forCompany($companyId)->where('incident_type', 'environmental')->count();
        $localProcurementSpend = (float) PurchaseOrder::query()->forCompany($companyId)->sum('total_amount');

        return [
            'environmental' => [
                'fuel_consumption' => (float) $fuel->sum('quantity'),
                'fuel_cost' => (float) $fuel->sum('total_cost'),
                'estimated_carbon_emissions' => round((float) $fuel->sum('quantity') * 2.68, 2),
                'environmental_incidents' => $environmentalIncidents,
                'material_waste_proxy' => (float) StockMovement::query()->forCompany($companyId)->where('type', 'adjustment')->sum('total_cost'),
            ],
            'social' => [
                'local_employment' => EmployeeProfile::query()->forCompany($companyId)->where('status', 'active')->count(),
                'training_events_proxy' => ToolboxTalk::query()->forCompany($companyId)->count(),
                'local_procurement' => $localProcurementSpend,
            ],
            'governance' => [
                'approval_exceptions' => PurchaseRequisition::query()->forCompany($companyId)->where('status', 'rejected')->count(),
                'compliance_breaches' => NonConformanceReport::query()->forCompany($companyId)->whereIn('severity', ['high', 'critical'])->count(),
                'supplier_due_diligence_proxy' => Supplier::query()->forCompany($companyId)->where('status', 'active')->count(),
            ],
            'emissions_factor_note' => 'Default diesel proxy uses 2.68 kg CO2e per litre. Configure by country, fuel type, and reporting framework before statutory reporting.',
        ];
    }

    private function clientReporting(int $companyId, array $projectAnalytics): array
    {
        return [
            'controlled_reports' => collect($projectAnalytics['projects'])->map(fn (array $project): array => [
                'project_id' => $project['project_id'],
                'project' => $project['project'],
                'client' => $project['client'],
                'overall_progress' => $project['progress'],
                'milestone_status' => $project['schedule_variance'] < -10 ? 'delayed' : 'on_track',
                'certified_value' => $project['billed_revenue'],
                'payment_status' => $project['receivable_balance'] > 0 ? 'outstanding' : 'current',
                'major_risks' => $project['health'] === 'red' ? 'Executive attention required' : 'Within reporting tolerance',
                'quality_summary' => "{$project['open_ncrs']} open NCRs",
                'safety_summary' => "{$project['open_safety_incidents']} open incidents",
            ])->values(),
            'hidden_internal_fields' => ['gross_margin', 'supplier_margin', 'internal_forecast_assumptions', 'confidential_commercial_notes'],
            'pending_decisions' => ClientApproval::query()->forCompany($companyId)->whereIn('status', ['submitted', 'pending'])->count()
                + ConsultantSubmittal::query()->forCompany($companyId)->whereIn('status', ['submitted', 'pending'])->count(),
        ];
    }

    private function alerts(int $companyId, ?array $projectAnalytics = null): array
    {
        $projectAnalytics ??= $this->projectAnalytics($companyId);
        $items = [];

        foreach ($projectAnalytics['projects'] as $project) {
            if (($project['cpi'] ?? 1) < 0.95) {
                $items[] = $this->alert('high', 'Project Controls', $project['project'], 'CPI below 0.95', 'Review cost code overruns and reforecast EAC.', 'project', $project['project_id']);
            }

            if (($project['spi'] ?? 1) < 0.90) {
                $items[] = $this->alert('high', 'Schedule', $project['project'], 'SPI below 0.90', 'Review delayed critical activities and recovery plan.', 'project', $project['project_id']);
            }

            if ($project['forecast_cost_variance'] < 0) {
                $items[] = $this->alert('critical', 'Financial', $project['project'], 'Forecast cost exceeds approved budget', 'Escalate forecast overrun and approve mitigation plan.', 'project', $project['project_id']);
            }

            if ($project['cash_position'] < 0) {
                $items[] = $this->alert('medium', 'Cash Flow', $project['project'], 'Cash position is negative', 'Prioritize collections and review supplier payment timing.', 'project', $project['project_id']);
            }
        }

        PurchaseOrder::query()
            ->forCompany($companyId)
            ->with(['supplier:id,name', 'project:id,name'])
            ->whereDate('expected_delivery_date', '<', now()->toDateString())
            ->whereNotIn('delivery_status', ['delivered'])
            ->limit(25)
            ->get()
            ->each(function (PurchaseOrder $order) use (&$items): void {
                $items[] = $this->alert('medium', 'Procurement', $order->project?->name, "PO {$order->po_number} overdue", 'Expedite supplier delivery or approve substitute sourcing.', 'purchase_order', $order->id, optional($order->expected_delivery_date)->toDateString());
            });

        NonConformanceReport::query()
            ->forCompany($companyId)
            ->with('project:id,name')
            ->where('severity', 'critical')
            ->whereNotIn('status', ['closed'])
            ->limit(25)
            ->get()
            ->each(function (NonConformanceReport $ncr) use (&$items): void {
                $items[] = $this->alert('critical', 'QA/QC', $ncr->project?->name, "Critical NCR {$ncr->ncr_number} open", 'Assign responsible person and verify corrective action.', 'ncr', $ncr->id, optional($ncr->due_date)->toDateString());
            });

        InventoryItem::query()
            ->forCompany($companyId)
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->limit(25)
            ->get()
            ->each(function (InventoryItem $item) use (&$items): void {
                $items[] = $this->alert('medium', 'Inventory', null, "{$item->sku} below reorder level", 'Create requisition or transfer stock from another warehouse.', 'inventory_item', $item->id);
            });

        return [
            'items' => collect($items)->sortByDesc(fn ($item) => ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1][$item['severity']] ?? 0)->values()->all(),
        ];
    }

    private function projectAnalytics(int $companyId, $projects = null): array
    {
        $projects ??= Project::query()->forCompany($companyId)->with(['client:id,name', 'branch:id,name,code'])->get();
        $weights = $this->healthWeights();

        return [
            'projects' => $projects->map(function (Project $project) use ($weights): array {
                $budget = (float) $project->budget_total;
                $actual = (float) $project->actual_cost;
                $forecast = (float) $project->forecast_to_complete;
                $progress = (float) $project->progress_percent;
                $plannedProgress = $this->plannedProgress($project);
                $plannedValue = $budget * ($plannedProgress / 100);
                $earnedValue = $budget * ($progress / 100);
                $cpi = $actual > 0 ? round($earnedValue / $actual, 2) : null;
                $spi = $plannedValue > 0 ? round($earnedValue / $plannedValue, 2) : null;
                $eac = $cpi && $cpi > 0 ? round($budget / $cpi, 2) : max($forecast, $actual + max(0, $budget - $earnedValue));
                $etc = max(0, $eac - $actual);
                $receivable = (float) Invoice::query()->where('project_id', $project->id)->whereNotIn('payment_status', ['paid'])->sum('balance_due');
                $payable = (float) SupplierInvoice::query()->where('project_id', $project->id)->whereNotIn('status', ['paid'])->sum('balance_due');
                $openNcrs = NonConformanceReport::query()->where('project_id', $project->id)->whereNotIn('status', ['closed'])->count();
                $criticalNcrs = NonConformanceReport::query()->where('project_id', $project->id)->where('severity', 'critical')->whereNotIn('status', ['closed'])->count();
                $safetyIncidents = SafetyIncident::query()->where('project_id', $project->id)->whereNotIn('status', ['closed'])->count();
                $riskLevel = $project->risk_level ?: 'medium';
                $scores = [
                    'cost' => $this->indicatorScore($cpi, [1, 0.95]),
                    'schedule' => $this->indicatorScore($spi, [1, 0.9]),
                    'cash_flow' => $receivable - $payable >= 0 ? 100 : 55,
                    'quality' => $criticalNcrs > 0 ? 25 : ($openNcrs > 0 ? 70 : 100),
                    'safety' => $safetyIncidents > 0 ? 55 : 100,
                    'risk' => ['low' => 100, 'medium' => 75, 'high' => 45, 'critical' => 20][$riskLevel] ?? 65,
                ];
                $healthScore = collect($scores)->reduce(fn ($carry, $score, $key) => $carry + ($score * ($weights[$key] ?? 0)), 0);
                $health = $healthScore >= 80 ? 'green' : ($healthScore >= 60 ? 'amber' : 'red');

                if ($budget <= 0 || $project->start_date === null || $project->target_end_date === null) {
                    $health = 'grey';
                }

                return [
                    'project_id' => $project->id,
                    'project' => $project->name,
                    'code' => $project->code,
                    'client' => $project->client?->name,
                    'branch' => $project->branch?->name,
                    'status' => $project->status,
                    'country' => $project->country,
                    'contract_value' => (float) $project->contract_value,
                    'budget_total' => $budget,
                    'actual_cost' => $actual,
                    'committed_cost' => (float) $project->committed_total,
                    'forecast_cost' => $forecast,
                    'progress' => $progress,
                    'planned_progress' => $plannedProgress,
                    'schedule_variance' => round($progress - $plannedProgress, 1),
                    'planned_value' => round($plannedValue, 2),
                    'earned_value' => round($earnedValue, 2),
                    'cost_variance' => round($earnedValue - $actual, 2),
                    'schedule_variance_value' => round($earnedValue - $plannedValue, 2),
                    'cpi' => $cpi,
                    'spi' => $spi,
                    'estimate_at_completion' => $eac,
                    'estimate_to_complete' => $etc,
                    'variance_at_completion' => round($budget - $eac, 2),
                    'to_complete_performance_index' => ($budget - $actual) > 0 ? round(($budget - $earnedValue) / ($budget - $actual), 2) : null,
                    'tcpi' => ($budget - $actual) > 0 ? round(($budget - $earnedValue) / ($budget - $actual), 2) : null,
                    'forecast_cost_variance' => round($budget - $forecast, 2),
                    'billed_revenue' => (float) Invoice::query()->where('project_id', $project->id)->sum('total_amount'),
                    'receivable_balance' => $receivable,
                    'payable_balance' => $payable,
                    'cash_position' => round($receivable - $payable, 2),
                    'margin_percent' => (float) $project->contract_value > 0 ? round((((float) $project->contract_value - $forecast) / (float) $project->contract_value) * 100, 1) : 0,
                    'open_ncrs' => $openNcrs,
                    'critical_ncrs' => $criticalNcrs,
                    'open_safety_incidents' => $safetyIncidents,
                    'risk_level' => $riskLevel,
                    'risk_probability' => ['low' => 1, 'medium' => 2, 'high' => 4, 'critical' => 5][$riskLevel] ?? 2,
                    'risk_impact' => $forecast > $budget ? 5 : ($safetyIncidents + $criticalNcrs > 0 ? 4 : 2),
                    'risk_exposure' => (float) max(0, $forecast - $budget) + ($criticalNcrs * 50000) + ($safetyIncidents * 25000),
                    'health_score' => round($healthScore, 1),
                    'health' => $health,
                    'health_components' => $scores,
                ];
            })->values()->all(),
        ];
    }

    private function plannedProgress(Project $project): float
    {
        if (! $project->start_date || ! $project->target_end_date) {
            return (float) $project->progress_percent;
        }

        $start = Carbon::parse($project->start_date)->startOfDay();
        $end = Carbon::parse($project->target_end_date)->startOfDay();
        $totalDays = max(1, $start->diffInDays($end));
        $elapsedDays = max(0, min($totalDays, $start->diffInDays(now()->startOfDay(), false)));

        return round(($elapsedDays / $totalDays) * 100, 1);
    }

    private function indicatorScore(?float $value, array $thresholds): int
    {
        if ($value === null) {
            return 60;
        }

        if ($value >= $thresholds[0]) {
            return 100;
        }

        if ($value >= $thresholds[1]) {
            return 70;
        }

        return 35;
    }

    private function healthWeights(): array
    {
        return [
            'cost' => 0.30,
            'schedule' => 0.25,
            'cash_flow' => 0.15,
            'quality' => 0.10,
            'safety' => 0.10,
            'risk' => 0.10,
        ];
    }

    private function procurementFunnel(int $companyId): array
    {
        return [
            ['stage' => 'Material Requests', 'count' => PurchaseRequisition::query()->forCompany($companyId)->count()],
            ['stage' => 'RFQs', 'count' => ProcurementRfq::query()->forCompany($companyId)->count()],
            ['stage' => 'Quotations', 'count' => SupplierQuotation::query()->forCompany($companyId)->count()],
            ['stage' => 'Purchase Orders', 'count' => PurchaseOrder::query()->forCompany($companyId)->count()],
            ['stage' => 'Goods Receipts', 'count' => GoodsReceipt::query()->forCompany($companyId)->count()],
            ['stage' => 'Supplier Invoices', 'count' => SupplierInvoice::query()->forCompany($companyId)->count()],
            ['stage' => 'Payments', 'count' => SupplierPayment::query()->forCompany($companyId)->count()],
        ];
    }

    private function supplierSpend(int $companyId)
    {
        return PurchaseOrder::query()
            ->forCompany($companyId)
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->select('suppliers.name as supplier')
            ->selectRaw('count(purchase_orders.id) as orders')
            ->selectRaw('coalesce(sum(purchase_orders.total_amount), 0) as spend')
            ->groupBy('suppliers.name')
            ->orderByDesc('spend')
            ->limit(15)
            ->get();
    }

    private function supplierScorecards(int $companyId)
    {
        $suppliers = Supplier::query()->forCompany($companyId)->withCount('supplierInvoices')->get();

        return $suppliers->map(function (Supplier $supplier): array {
            $orders = PurchaseOrder::query()->where('supplier_id', $supplier->id)->get();
            $lateOrders = $orders->filter(fn (PurchaseOrder $order): bool => $order->expected_delivery_date && $order->expected_delivery_date->isPast() && ! in_array($order->delivery_status, ['delivered'], true))->count();
            $rejectedQuality = ProcurementQualityInspection::query()->whereIn('purchase_order_id', $orders->pluck('id'))->whereIn('status', ['failed', 'rejected'])->count();

            return [
                'supplier' => $supplier->name,
                'rating' => $supplier->rating,
                'orders' => $orders->count(),
                'total_spend' => (float) $orders->sum('total_amount'),
                'on_time_delivery' => $orders->count() > 0 ? round((($orders->count() - $lateOrders) / $orders->count()) * 100, 1) : 0,
                'late_deliveries' => $lateOrders,
                'rejection_rate' => $orders->count() > 0 ? round(($rejectedQuality / $orders->count()) * 100, 1) : 0,
                'average_lead_time' => $supplier->lead_time_days,
                'outstanding_balance' => (float) SupplierInvoice::query()->where('supplier_id', $supplier->id)->where('status', '!=', 'paid')->sum('balance_due'),
            ];
        })->sortByDesc('total_spend')->values();
    }

    private function monthlyRevenueTrend(int $companyId)
    {
        return Invoice::query()
            ->forCompany($companyId)
            ->whereNotIn('status', ['draft', 'void'])
            ->get()
            ->groupBy(fn (Invoice $invoice): string => optional($invoice->issue_date)->format('Y-m') ?: 'undated')
            ->map(fn ($items, $period): array => [
                'period' => $period,
                'revenue' => (float) $items->sum('total_amount'),
                'gross_margin' => 0,
            ])
            ->sortBy('period')
            ->values();
    }

    private function monthlyCashFlow(int $companyId)
    {
        $inflows = Payment::query()->forCompany($companyId)->get()->groupBy(fn (Payment $payment): string => optional($payment->received_at)->format('Y-m') ?: 'undated');
        $outflows = SupplierPayment::query()->forCompany($companyId)->get()->groupBy(fn (SupplierPayment $payment): string => optional($payment->payment_date)->format('Y-m') ?: 'undated');
        $periods = $inflows->keys()->merge($outflows->keys())->unique()->sort()->values();

        return $periods->map(fn ($period): array => [
            'period' => $period,
            'inflows' => (float) ($inflows->get($period)?->sum('amount') ?? 0),
            'outflows' => (float) ($outflows->get($period)?->sum('amount') ?? 0),
            'net_cash_flow' => (float) ($inflows->get($period)?->sum('amount') ?? 0) - (float) ($outflows->get($period)?->sum('amount') ?? 0),
        ]);
    }

    private function invoiceAgeing($invoices): array
    {
        $open = $invoices->where('payment_status', '!=', 'paid');

        return [
            ['bucket' => 'Current', 'balance' => (float) $open->filter(fn ($invoice) => ! $invoice->due_date || $invoice->due_date->isFuture())->sum('balance_due')],
            ['bucket' => '1-30 days', 'balance' => (float) $open->filter(fn ($invoice) => $invoice->due_date && $invoice->due_date->diffInDays(now(), false) >= 1 && $invoice->due_date->diffInDays(now(), false) <= 30)->sum('balance_due')],
            ['bucket' => '31-60 days', 'balance' => (float) $open->filter(fn ($invoice) => $invoice->due_date && $invoice->due_date->diffInDays(now(), false) >= 31 && $invoice->due_date->diffInDays(now(), false) <= 60)->sum('balance_due')],
            ['bucket' => '60+ days', 'balance' => (float) $open->filter(fn ($invoice) => $invoice->due_date && $invoice->due_date->diffInDays(now(), false) > 60)->sum('balance_due')],
        ];
    }

    private function ncrAgeing($ncrs): array
    {
        $open = $ncrs->where('status', '!=', 'closed');

        return [
            ['bucket' => '0-7 days', 'total' => $open->filter(fn ($ncr) => $ncr->created_at->diffInDays(now()) <= 7)->count()],
            ['bucket' => '8-14 days', 'total' => $open->filter(fn ($ncr) => $ncr->created_at->diffInDays(now()) >= 8 && $ncr->created_at->diffInDays(now()) <= 14)->count()],
            ['bucket' => '15-30 days', 'total' => $open->filter(fn ($ncr) => $ncr->created_at->diffInDays(now()) >= 15 && $ncr->created_at->diffInDays(now()) <= 30)->count()],
            ['bucket' => '30+ days', 'total' => $open->filter(fn ($ncr) => $ncr->created_at->diffInDays(now()) > 30)->count()],
        ];
    }

    private function delayedTasks(int $companyId)
    {
        return ProjectTask::query()
            ->forCompany($companyId)
            ->with('project:id,name,code')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['done', 'cancelled'])
            ->orderBy('due_date')
            ->limit(80)
            ->get()
            ->map(fn (ProjectTask $task): array => [
                'id' => $task->id,
                'project' => $task->project?->name,
                'activity' => $task->title,
                'priority' => $task->priority,
                'status' => $task->status,
                'progress' => (int) $task->progress_percent,
                'due_date' => optional($task->due_date)->toDateString(),
                'days_late' => $task->due_date?->diffInDays(now()) ?? 0,
            ]);
    }

    private function commercialAlerts(int $companyId)
    {
        return ClientApproval::query()
            ->forCompany($companyId)
            ->whereDate('due_date', '<=', now()->copy()->addWeek()->toDateString())
            ->whereIn('status', ['submitted', 'pending'])
            ->limit(25)
            ->get()
            ->map(fn (ClientApproval $approval): array => [
                'approval' => $approval->approval_number,
                'title' => $approval->title,
                'status' => $approval->status,
                'due_date' => optional($approval->due_date)->toDateString(),
                'recommended_action' => 'Follow up client decision before entitlement or schedule impact.',
            ]);
    }

    private function alert(string $severity, string $category, ?string $project, string $title, string $recommendedAction, string $sourceType, ?int $sourceId, ?string $dueDate = null): array
    {
        return [
            'severity' => $severity,
            'category' => $category,
            'project' => $project,
            'title' => $title,
            'recommended_action' => $recommendedAction,
            'responsible_person' => $category === 'Financial' ? 'Finance Manager' : 'Project Director',
            'date_triggered' => now()->toDateString(),
            'status' => 'open',
            'escalation_level' => $severity === 'critical' ? 'executive' : 'management',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'due_date' => $dueDate,
            'is_overdue' => $dueDate ? Carbon::parse($dueDate)->isPast() : false,
        ];
    }

    private function kpiDefinitions(): array
    {
        return [
            'CPI' => 'Earned Value divided by Actual Cost. Values below 1.0 indicate cost underperformance.',
            'SPI' => 'Earned Value divided by Planned Value. Values below 1.0 indicate schedule underperformance.',
            'EAC' => 'Estimate at Completion. Current projected total cost at completion.',
            'TCPI' => 'To-Complete Performance Index. Required cost efficiency for remaining work.',
            'Project Health' => 'Weighted score using Cost 30%, Schedule 25%, Cash Flow 15%, Quality 10%, Safety 10%, Risk 10%.',
            'LTIFR' => 'Lost-time injuries multiplied by 200,000 divided by recorded labour hours.',
            'TRIR' => 'Total recordable incidents multiplied by 200,000 divided by recorded labour hours.',
        ];
    }

    private function uniqueSlug(int $companyId, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 2;

        while (BiDashboard::query()->forCompany($companyId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function defaultWidgets(): array
    {
        return [
            ['title' => 'Active Projects', 'widget_type' => 'metric', 'metric_key' => 'active_projects'],
            ['title' => 'Accounts Receivable', 'widget_type' => 'metric', 'metric_key' => 'accounts_receivable'],
            ['title' => 'Cost By Category', 'widget_type' => 'bar', 'metric_key' => 'cost_by_category'],
            ['title' => 'Project Health', 'widget_type' => 'pie', 'metric_key' => 'calculated_project_health'],
        ];
    }
}
