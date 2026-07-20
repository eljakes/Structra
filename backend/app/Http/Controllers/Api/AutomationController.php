<?php

namespace App\Http\Controllers\Api;

use App\Models\AiInsight;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\NonConformanceReport;
use App\Models\Project;
use App\Models\SafetyIncident;
use App\Models\WorkPermit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AutomationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        return response()->json([
            'rules' => AutomationRule::query()->forCompany($companyId)->withCount('runs')->latest()->get(),
            'runs' => AutomationRun::query()->forCompany($companyId)->with('rule:id,name,rule_type')->latest('started_at')->limit(100)->get(),
            'summary' => [
                'active_rules' => AutomationRule::query()->forCompany($companyId)->where('is_active', true)->count(),
                'runs_today' => AutomationRun::query()->forCompany($companyId)->whereDate('started_at', now()->toDateString())->count(),
                'actions_today' => AutomationRun::query()->forCompany($companyId)->whereDate('started_at', now()->toDateString())->sum('actions_executed'),
            ],
        ]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rule_type' => ['required', Rule::in(['project_overrun', 'overdue_invoice', 'low_stock', 'hse_open', 'permit_expiry'])],
            'trigger_event' => ['nullable', Rule::in(['manual', 'daily', 'record_created', 'record_updated'])],
            'conditions' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule = AutomationRule::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'rule_type' => $data['rule_type'],
            'trigger_event' => $data['trigger_event'] ?? 'manual',
            'conditions' => $data['conditions'] ?? $this->defaultConditions($data['rule_type']),
            'actions' => $data['actions'] ?? ['type' => 'create_insight'],
            'severity' => $data['severity'] ?? 'medium',
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $this->user($request)->id,
        ]);

        return response()->json(['rule' => $rule], 201);
    }

    public function updateRule(Request $request, AutomationRule $rule): JsonResponse
    {
        $this->assertTenant($request, $rule);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'severity' => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule->update($data);

        return response()->json(['rule' => $rule->fresh()]);
    }

    public function runRule(Request $request, AutomationRule $rule): JsonResponse
    {
        $this->assertTenant($request, $rule);
        abort_if(! $rule->is_active, 422, 'Automation rule is not active.');

        $run = DB::transaction(function () use ($request, $rule) {
            $matchedRecords = $this->matchedRecords($rule);
            $results = [];

            foreach ($matchedRecords as $record) {
                $results[] = $this->executeAction($request, $rule, $record);
            }

            $run = AutomationRun::query()->create([
                'company_id' => $rule->company_id,
                'automation_rule_id' => $rule->id,
                'run_number' => $this->nextNumber('AUTO', AutomationRun::class, 'run_number', $rule->company_id),
                'status' => 'completed',
                'matched_count' => count($matchedRecords),
                'actions_executed' => count($results),
                'matched_records' => $matchedRecords,
                'action_results' => $results,
                'started_at' => now(),
                'finished_at' => now(),
                'run_by' => $this->user($request)->id,
            ]);

            $rule->update(['last_run_at' => now()]);

            return $run;
        });

        return response()->json(['run' => $run->fresh('rule'), 'rule' => $rule->fresh()]);
    }

    public function runActive(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $runs = [];

        AutomationRule::query()
            ->forCompany($companyId)
            ->where('is_active', true)
            ->get()
            ->each(function (AutomationRule $rule) use ($request, &$runs): void {
                $runs[] = $this->runRule($request, $rule)->getData(true)['run'];
            });

        return response()->json(['runs' => $runs]);
    }

    private function matchedRecords(AutomationRule $rule): array
    {
        $conditions = $rule->conditions ?? [];

        return match ($rule->rule_type) {
            'project_overrun' => Project::query()
                ->forCompany($rule->company_id)
                ->where(function ($query) use ($conditions): void {
                    $threshold = ((float) ($conditions['threshold_percent'] ?? 0)) / 100;
                    $query->whereColumn('forecast_to_complete', '>', 'budget_total')
                        ->orWhereIn('health_status', ['at_risk', 'critical'])
                        ->orWhereRaw('(actual_cost + committed_total) > (budget_total * ?)', [1 + $threshold]);
                })
                ->get()
                ->map(fn (Project $project) => [
                    'type' => 'project',
                    'id' => $project->id,
                    'label' => $project->code.' '.$project->name,
                    'signals' => [
                        'budget_total' => (float) $project->budget_total,
                        'forecast_to_complete' => (float) $project->forecast_to_complete,
                        'health_status' => $project->health_status,
                    ],
                ])
                ->all(),
            'overdue_invoice' => Invoice::query()
                ->forCompany($rule->company_id)
                ->whereNotIn('payment_status', ['paid'])
                ->whereDate('due_date', '<', now()->subDays((int) ($conditions['grace_days'] ?? 0))->toDateString())
                ->where('balance_due', '>=', (float) ($conditions['minimum_balance'] ?? 0))
                ->get()
                ->map(fn (Invoice $invoice) => [
                    'type' => 'invoice',
                    'id' => $invoice->id,
                    'label' => $invoice->invoice_number,
                    'project_id' => $invoice->project_id,
                    'signals' => ['balance_due' => (float) $invoice->balance_due, 'due_date' => $invoice->due_date?->toDateString()],
                ])
                ->all(),
            'low_stock' => InventoryItem::query()
                ->forCompany($rule->company_id)
                ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
                ->where('status', 'active')
                ->get()
                ->map(fn (InventoryItem $item) => [
                    'type' => 'inventory_item',
                    'id' => $item->id,
                    'label' => $item->sku.' '.$item->name,
                    'signals' => ['quantity_on_hand' => (float) $item->quantity_on_hand, 'reorder_level' => (float) $item->reorder_level],
                ])
                ->all(),
            'hse_open' => [
                ...NonConformanceReport::query()
                    ->forCompany($rule->company_id)
                    ->whereNotIn('status', ['closed'])
                    ->get()
                    ->map(fn (NonConformanceReport $ncr) => [
                        'type' => 'ncr',
                        'id' => $ncr->id,
                        'label' => $ncr->ncr_number.' '.$ncr->title,
                        'project_id' => $ncr->project_id,
                        'signals' => ['severity' => $ncr->severity, 'status' => $ncr->status],
                    ])
                    ->all(),
                ...SafetyIncident::query()
                    ->forCompany($rule->company_id)
                    ->whereNotIn('status', ['closed'])
                    ->get()
                    ->map(fn (SafetyIncident $incident) => [
                        'type' => 'safety_incident',
                        'id' => $incident->id,
                        'label' => $incident->incident_number,
                        'project_id' => $incident->project_id,
                        'signals' => ['severity' => $incident->severity, 'status' => $incident->status],
                    ])
                    ->all(),
            ],
            'permit_expiry' => WorkPermit::query()
                ->forCompany($rule->company_id)
                ->whereIn('status', ['approved', 'active'])
                ->whereNotNull('valid_until')
                ->where('valid_until', '<=', now()->addDays((int) ($conditions['days'] ?? 3)))
                ->get()
                ->map(fn (WorkPermit $permit) => [
                    'type' => 'work_permit',
                    'id' => $permit->id,
                    'label' => $permit->permit_number.' '.str_replace('_', ' ', $permit->permit_type),
                    'project_id' => $permit->project_id,
                    'signals' => ['valid_until' => $permit->valid_until?->toISOString(), 'status' => $permit->status],
                ])
                ->all(),
        };
    }

    private function executeAction(Request $request, AutomationRule $rule, array $record): array
    {
        $actionType = $rule->actions['type'] ?? 'create_insight';

        if ($actionType !== 'create_insight') {
            return ['type' => $actionType, 'status' => 'skipped', 'record' => $record];
        }

        $insight = AiInsight::query()->updateOrCreate(
            ['company_id' => $rule->company_id, 'source_key' => "automation-{$rule->id}-{$record['type']}-{$record['id']}"],
            [
                'project_id' => $record['project_id'] ?? null,
                'category' => 'automation',
                'severity' => $rule->severity,
                'title' => $rule->name.': '.$record['label'],
                'narrative' => 'Automation rule matched '.$record['label'].' from '.$record['type'].'.',
                'recommendation' => $rule->actions['recommendation'] ?? 'Review and close the matched operational action.',
                'signals' => $record['signals'] ?? [],
                'confidence_score' => 90,
                'status' => 'open',
                'source' => 'workflow_automation',
                'detected_at' => now(),
                'created_by' => $this->user($request)->id,
            ],
        );

        return ['type' => 'create_insight', 'status' => 'executed', 'insight_id' => $insight->id, 'record' => $record];
    }

    private function defaultConditions(string $ruleType): array
    {
        return match ($ruleType) {
            'project_overrun' => ['threshold_percent' => 0],
            'overdue_invoice' => ['grace_days' => 0, 'minimum_balance' => 0],
            'low_stock' => ['compare' => 'quantity_on_hand <= reorder_level'],
            'hse_open' => ['include_ncrs' => true, 'include_incidents' => true],
            'permit_expiry' => ['days' => 3],
        };
    }

    private function assertTenant(Request $request, AutomationRule $rule): void
    {
        abort_if((int) $rule->company_id !== $this->companyId($request), 404);
    }
}
