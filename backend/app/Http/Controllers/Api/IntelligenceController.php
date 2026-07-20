<?php

namespace App\Http\Controllers\Api;

use App\Models\AiInsight;
use App\Models\AssistantQuery;
use App\Models\Expense;
use App\Models\FieldIssue;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\NonConformanceReport;
use App\Models\PredictiveForecast;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\PurchaseRequisition;
use App\Models\SafetyIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntelligenceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        return response()->json([
            'summary' => $this->summary($companyId),
            'insights' => AiInsight::query()->forCompany($companyId)->with('project:id,code,name')->latest('detected_at')->limit(100)->get(),
            'forecasts' => PredictiveForecast::query()->forCompany($companyId)->with('project:id,code,name')->latest('generated_at')->limit(100)->get(),
            'assistant_queries' => AssistantQuery::query()->forCompany($companyId)->latest('answered_at')->limit(40)->get(),
        ]);
    }

    public function analyze(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $insights = collect([
            ...$this->projectRiskInsights($request),
            ...$this->cashAndProcurementInsights($request),
            ...$this->stockAndComplianceInsights($request),
        ]);

        $forecasts = collect($this->generateForecasts($request));

        return response()->json([
            'message' => 'Analysis completed.',
            'insights_created_or_updated' => $insights->count(),
            'forecasts_created_or_updated' => $forecasts->count(),
            'summary' => $this->summary($companyId),
            'insights' => AiInsight::query()->forCompany($companyId)->with('project:id,code,name')->latest('detected_at')->limit(100)->get(),
            'forecasts' => PredictiveForecast::query()->forCompany($companyId)->with('project:id,code,name')->latest('generated_at')->limit(100)->get(),
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        [$intent, $answer, $payload, $sources] = $this->answerQuestion($companyId, $data['question']);

        $query = AssistantQuery::query()->create([
            'company_id' => $companyId,
            'user_id' => $this->user($request)->id,
            'intent' => $intent,
            'question' => $data['question'],
            'answer' => $answer,
            'filters' => [],
            'data_sources' => $sources,
            'result_payload' => $payload,
            'confidence_score' => $intent === 'general' ? 68 : 88,
            'answered_at' => now(),
        ]);

        return response()->json(['assistant_query' => $query]);
    }

    public function resolveInsight(Request $request, AiInsight $insight): JsonResponse
    {
        abort_if((int) $insight->company_id !== $this->companyId($request), 404);

        $insight->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json(['insight' => $insight->fresh('project')]);
    }

    private function summary(int $companyId): array
    {
        return [
            'open_insights' => AiInsight::query()->forCompany($companyId)->where('status', 'open')->count(),
            'critical_insights' => AiInsight::query()->forCompany($companyId)->where('status', 'open')->where('severity', 'critical')->count(),
            'forecasts' => PredictiveForecast::query()->forCompany($companyId)->where('status', 'current')->count(),
            'latest_analysis_at' => AiInsight::query()->forCompany($companyId)->max('detected_at'),
            'overdue_invoices' => Invoice::query()->forCompany($companyId)->whereNotIn('payment_status', ['paid'])->whereDate('due_date', '<', now()->toDateString())->count(),
            'at_risk_projects' => Project::query()->forCompany($companyId)->whereIn('health_status', ['at_risk', 'critical'])->count(),
        ];
    }

    private function projectRiskInsights(Request $request): array
    {
        $companyId = $this->companyId($request);
        $created = [];

        Project::query()
            ->forCompany($companyId)
            ->withCount([
                'tasks as late_tasks_count' => fn ($query) => $query->whereNotIn('status', ['done', 'cancelled'])->whereDate('due_date', '<', now()->toDateString()),
                'fieldIssues as open_field_issues_count' => fn ($query) => $query->whereNotIn('status', ['resolved', 'closed']),
            ])
            ->get()
            ->each(function (Project $project) use ($request, &$created): void {
                $budget = (float) $project->budget_total;
                $forecast = (float) $project->forecast_to_complete;
                $actual = (float) $project->actual_cost;
                $committed = (float) $project->committed_total;
                $burnRatio = $budget > 0 ? (($actual + $committed) / $budget) : 0;
                $overrun = $budget > 0 ? $forecast - $budget : 0;

                if ($overrun > 0 || $burnRatio >= 0.85 || $project->late_tasks_count > 0 || $project->open_field_issues_count > 0) {
                    $severity = match (true) {
                        $overrun > $budget * 0.15 || $project->late_tasks_count >= 3 => 'critical',
                        $overrun > 0 || $burnRatio >= 0.95 || $project->late_tasks_count > 0 => 'high',
                        default => 'medium',
                    };

                    $created[] = $this->upsertInsight($request, [
                        'project_id' => $project->id,
                        'source_key' => "project-risk-{$project->id}",
                        'category' => 'project_risk',
                        'severity' => $severity,
                        'title' => "{$project->name} risk trend",
                        'narrative' => "Project risk signals show {$project->late_tasks_count} late task(s), {$project->open_field_issues_count} open field issue(s), and a cost burn ratio of ".round($burnRatio * 100, 1).'%.',
                        'recommendation' => 'Review late tasks, unblock field issues, and rebaseline forecast-to-complete before the next progress meeting.',
                        'signals' => [
                            'budget_total' => $budget,
                            'actual_cost' => $actual,
                            'committed_total' => $committed,
                            'forecast_to_complete' => $forecast,
                            'overrun' => $overrun,
                            'late_tasks' => $project->late_tasks_count,
                            'open_field_issues' => $project->open_field_issues_count,
                        ],
                        'confidence_score' => 84,
                    ]);
                }
            });

        return $created;
    }

    private function cashAndProcurementInsights(Request $request): array
    {
        $companyId = $this->companyId($request);
        $created = [];

        Invoice::query()
            ->forCompany($companyId)
            ->whereNotIn('payment_status', ['paid'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->with(['project:id,name', 'client:id,name'])
            ->get()
            ->each(function (Invoice $invoice) use ($request, &$created): void {
                $created[] = $this->upsertInsight($request, [
                    'project_id' => $invoice->project_id,
                    'source_key' => "overdue-invoice-{$invoice->id}",
                    'category' => 'cash_flow',
                    'severity' => (float) $invoice->balance_due > 100000 ? 'high' : 'medium',
                    'title' => "Overdue receivable {$invoice->invoice_number}",
                    'narrative' => "{$invoice->invoice_number} has an outstanding balance of {$invoice->balance_due} {$invoice->currency}.",
                    'recommendation' => 'Escalate collection follow-up and include this receivable in the weekly cash review.',
                    'signals' => [
                        'invoice_id' => $invoice->id,
                        'balance_due' => (float) $invoice->balance_due,
                        'due_date' => $invoice->due_date?->toDateString(),
                        'client' => $invoice->client?->name,
                    ],
                    'confidence_score' => 92,
                ]);
            });

        $submittedRequisitions = PurchaseRequisition::query()
            ->forCompany($companyId)
            ->where('status', 'submitted')
            ->whereDate('required_by', '<=', now()->addDays(7)->toDateString())
            ->count();

        if ($submittedRequisitions > 0) {
            $created[] = $this->upsertInsight($request, [
                'source_key' => 'pending-procurement-next-7-days',
                'category' => 'procurement',
                'severity' => 'medium',
                'title' => 'Procurement approvals due within 7 days',
                'narrative' => "{$submittedRequisitions} submitted requisition(s) are required within the next 7 days.",
                'recommendation' => 'Clear procurement approvals before supplier lead times affect site progress.',
                'signals' => ['submitted_requisitions' => $submittedRequisitions],
                'confidence_score' => 86,
            ]);
        }

        return $created;
    }

    private function stockAndComplianceInsights(Request $request): array
    {
        $companyId = $this->companyId($request);
        $created = [];

        InventoryItem::query()
            ->forCompany($companyId)
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->where('status', 'active')
            ->get()
            ->each(function (InventoryItem $item) use ($request, &$created): void {
                $created[] = $this->upsertInsight($request, [
                    'source_key' => "low-stock-{$item->id}",
                    'category' => 'inventory',
                    'severity' => (float) $item->quantity_on_hand <= 0 ? 'critical' : 'medium',
                    'title' => "Reorder alert: {$item->name}",
                    'narrative' => "{$item->name} is at {$item->quantity_on_hand} {$item->unit}, below or equal to the reorder level of {$item->reorder_level}.",
                    'recommendation' => 'Create a requisition or confirm inbound purchase orders for this item.',
                    'signals' => [
                        'inventory_item_id' => $item->id,
                        'quantity_on_hand' => (float) $item->quantity_on_hand,
                        'reorder_level' => (float) $item->reorder_level,
                    ],
                    'confidence_score' => 94,
                ]);
            });

        $openNcrs = NonConformanceReport::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->count();
        $openIncidents = SafetyIncident::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->count();

        if ($openNcrs > 0 || $openIncidents > 0) {
            $created[] = $this->upsertInsight($request, [
                'source_key' => 'open-qhse-load',
                'category' => 'quality_safety',
                'severity' => $openIncidents > 0 ? 'high' : 'medium',
                'title' => 'Open QA/HSE actions require attention',
                'narrative' => "There are {$openNcrs} open NCR(s) and {$openIncidents} open safety incident(s).",
                'recommendation' => 'Prioritize corrective action closure before upcoming client or consultant reviews.',
                'signals' => ['open_ncrs' => $openNcrs, 'open_incidents' => $openIncidents],
                'confidence_score' => 88,
            ]);
        }

        return $created;
    }

    private function generateForecasts(Request $request): array
    {
        $companyId = $this->companyId($request);
        $forecasts = [];

        Project::query()->forCompany($companyId)->get()->each(function (Project $project) use ($request, &$forecasts): void {
            $budget = (float) $project->budget_total;
            $forecast = (float) $project->forecast_to_complete;
            $variance = $forecast - $budget;

            $forecasts[] = PredictiveForecast::query()->updateOrCreate(
                ['company_id' => $project->company_id, 'source_key' => "cost-forecast-{$project->id}"],
                [
                    'project_id' => $project->id,
                    'forecast_number' => PredictiveForecast::query()->where('company_id', $project->company_id)->where('source_key', "cost-forecast-{$project->id}")->value('forecast_number')
                        ?: $this->nextNumber('FCST', PredictiveForecast::class, 'forecast_number', $project->company_id),
                    'forecast_type' => 'cost',
                    'period_label' => now()->format('Y-m'),
                    'baseline_value' => $budget,
                    'forecast_value' => $forecast,
                    'variance_value' => $variance,
                    'confidence_score' => $budget > 0 ? 82 : 65,
                    'drivers' => [
                        'actual_cost' => (float) $project->actual_cost,
                        'committed_total' => (float) $project->committed_total,
                        'progress_percent' => (int) $project->progress_percent,
                    ],
                    'status' => 'current',
                    'generated_at' => now(),
                    'created_by' => $this->user($request)->id,
                ],
            );

            $lateTasks = ProjectTask::query()
                ->where('project_id', $project->id)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->count();

            $openIssues = FieldIssue::query()
                ->where('project_id', $project->id)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count();

            $delayDays = ($lateTasks * 3) + ($openIssues * 2);

            $forecasts[] = PredictiveForecast::query()->updateOrCreate(
                ['company_id' => $project->company_id, 'source_key' => "schedule-forecast-{$project->id}"],
                [
                    'project_id' => $project->id,
                    'forecast_number' => PredictiveForecast::query()->where('company_id', $project->company_id)->where('source_key', "schedule-forecast-{$project->id}")->value('forecast_number')
                        ?: $this->nextNumber('FCST', PredictiveForecast::class, 'forecast_number', $project->company_id),
                    'forecast_type' => 'schedule',
                    'period_label' => now()->format('Y-m'),
                    'baseline_value' => 0,
                    'forecast_value' => $delayDays,
                    'variance_value' => $delayDays,
                    'confidence_score' => 78,
                    'drivers' => ['late_tasks' => $lateTasks, 'open_field_issues' => $openIssues],
                    'status' => 'current',
                    'generated_at' => now(),
                    'created_by' => $this->user($request)->id,
                ],
            );
        });

        $openReceivables = (float) Invoice::query()->forCompany($companyId)->whereNotIn('payment_status', ['paid'])->sum('balance_due');
        $approvedExpenses = (float) Expense::query()->forCompany($companyId)->where('status', 'approved')->sum('amount');

        $forecasts[] = PredictiveForecast::query()->updateOrCreate(
            ['company_id' => $companyId, 'source_key' => 'cash-flow-30-day'],
            [
                'forecast_number' => PredictiveForecast::query()->where('company_id', $companyId)->where('source_key', 'cash-flow-30-day')->value('forecast_number')
                    ?: $this->nextNumber('FCST', PredictiveForecast::class, 'forecast_number', $companyId),
                'forecast_type' => 'cash_flow',
                'period_label' => 'next_30_days',
                'baseline_value' => $openReceivables,
                'forecast_value' => $openReceivables - $approvedExpenses,
                'variance_value' => -$approvedExpenses,
                'confidence_score' => 76,
                'drivers' => ['open_receivables' => $openReceivables, 'approved_expenses' => $approvedExpenses],
                'status' => 'current',
                'generated_at' => now(),
                'created_by' => $this->user($request)->id,
            ],
        );

        return $forecasts;
    }

    private function answerQuestion(int $companyId, string $question): array
    {
        $normalized = strtolower($question);

        if (str_contains($normalized, 'risk') || str_contains($normalized, 'at risk')) {
            $projects = Project::query()->forCompany($companyId)->whereIn('health_status', ['at_risk', 'critical'])->orderBy('health_status')->get(['id', 'code', 'name', 'health_status', 'progress_percent']);
            $names = $projects->map(fn (Project $project) => "{$project->code} {$project->name} ({$project->health_status})")->join('; ');

            return ['risk', $projects->isEmpty() ? 'No projects are currently marked at risk or critical.' : "At-risk projects: {$names}.", ['projects' => $projects], ['projects', 'ai_insights']];
        }

        if (str_contains($normalized, 'cash') || str_contains($normalized, 'receivable') || str_contains($normalized, 'invoice')) {
            $outstanding = (float) Invoice::query()->forCompany($companyId)->whereNotIn('payment_status', ['paid'])->sum('balance_due');
            $overdue = (float) Invoice::query()->forCompany($companyId)->whereNotIn('payment_status', ['paid'])->whereDate('due_date', '<', now()->toDateString())->sum('balance_due');

            return ['cash_flow', "Outstanding receivables are {$outstanding}; overdue receivables are {$overdue}.", ['outstanding' => $outstanding, 'overdue' => $overdue], ['invoices', 'payments']];
        }

        if (str_contains($normalized, 'stock') || str_contains($normalized, 'inventory') || str_contains($normalized, 'reorder')) {
            $items = InventoryItem::query()->forCompany($companyId)->whereColumn('quantity_on_hand', '<=', 'reorder_level')->get(['id', 'sku', 'name', 'quantity_on_hand', 'reorder_level', 'unit']);

            return ['inventory', $items->isEmpty() ? 'No inventory items are currently at or below reorder level.' : $items->count().' inventory item(s) are at or below reorder level.', ['items' => $items], ['inventory_items']];
        }

        if (str_contains($normalized, 'safety') || str_contains($normalized, 'hse') || str_contains($normalized, 'quality') || str_contains($normalized, 'ncr')) {
            $openNcrs = NonConformanceReport::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->count();
            $openIncidents = SafetyIncident::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->count();

            return ['quality_safety', "There are {$openNcrs} open NCR(s) and {$openIncidents} open safety incident(s).", ['open_ncrs' => $openNcrs, 'open_incidents' => $openIncidents], ['non_conformance_reports', 'safety_incidents']];
        }

        $summary = $this->summary($companyId);

        return ['general', 'I can answer questions about project risk, cash flow, inventory reorder exposure, and QA/HSE status. Current open insights: '.$summary['open_insights'].'.', $summary, ['ai_insights', 'projects', 'invoices']];
    }

    private function upsertInsight(Request $request, array $payload): AiInsight
    {
        return AiInsight::query()->updateOrCreate(
            ['company_id' => $this->companyId($request), 'source_key' => $payload['source_key']],
            [
                'project_id' => $payload['project_id'] ?? null,
                'category' => $payload['category'],
                'severity' => $payload['severity'],
                'title' => $payload['title'],
                'narrative' => $payload['narrative'],
                'recommendation' => $payload['recommendation'] ?? null,
                'signals' => $payload['signals'] ?? [],
                'confidence_score' => $payload['confidence_score'] ?? 75,
                'status' => 'open',
                'source' => 'structra_ai',
                'detected_at' => now(),
                'created_by' => $this->user($request)->id,
            ],
        );
    }
}
