<?php

namespace App\Http\Controllers\Api;

use App\Models\AiInsight;
use App\Models\AutomationRule;
use App\Models\AutomationRuleVersion;
use App\Models\AutomationRun;
use App\Models\AutomationTemplate;
use App\Models\Expense;
use App\Models\FieldDailyReport;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\NonConformanceReport;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\PurchaseRequisition;
use App\Models\SafetyIncident;
use App\Models\SupplierInvoice;
use App\Models\WorkPermit;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class AutomationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $rules = AutomationRule::query()
            ->forCompany($companyId)
            ->withCount('runs')
            ->with(['versions' => fn ($query) => $query->latest('version')->limit(3)])
            ->latest()
            ->get();
        $runs = AutomationRun::query()
            ->forCompany($companyId)
            ->with('rule:id,name,rule_type,module')
            ->latest('started_at')
            ->limit(150)
            ->get();

        return response()->json([
            'rules' => $rules,
            'runs' => $runs,
            'templates' => $this->templates($companyId)->values(),
            'catalog' => $this->catalog(),
            'analytics' => $this->analytics($rules, $runs),
            'summary' => [
                'active_rules' => $rules->where('is_active', true)->count(),
                'failed_workflows' => $runs->where('status', 'failed')->count(),
                'running_workflows' => $runs->whereIn('status', ['queued', 'running'])->count(),
                'completed_today' => AutomationRun::query()->forCompany($companyId)->where('status', 'completed')->whereDate('started_at', now()->toDateString())->count(),
                'runs_today' => AutomationRun::query()->forCompany($companyId)->whereDate('started_at', now()->toDateString())->count(),
                'actions_today' => AutomationRun::query()->forCompany($companyId)->whereDate('started_at', now()->toDateString())->sum('actions_executed'),
                'scheduled_jobs' => $rules->filter(fn (AutomationRule $rule): bool => ($rule->schedule_config['frequency'] ?? 'manual') !== 'manual')->count(),
                'approval_workflows' => $rules->filter(fn (AutomationRule $rule): bool => filled($rule->approval_config['steps'] ?? []))->count(),
                'average_execution_time_ms' => round((float) $runs->avg('duration_ms'), 1),
            ],
        ]);
    }

    public function catalog(): array
    {
        return [
            'modules' => ['projects', 'procurement', 'finance', 'hr', 'inventory', 'field', 'equipment', 'qa_hse', 'crm', 'documents', 'portals', 'general'],
            'triggers' => [
                ['key' => 'manual', 'label' => 'Manual Run', 'module' => 'general'],
                ['key' => 'schedule_due', 'label' => 'Schedule Due', 'module' => 'general'],
                ['key' => 'project_created', 'label' => 'Project Created', 'module' => 'projects'],
                ['key' => 'project_delayed', 'label' => 'Project Delayed', 'module' => 'projects'],
                ['key' => 'budget_exceeded', 'label' => 'Budget Exceeded', 'module' => 'projects'],
                ['key' => 'material_request_submitted', 'label' => 'Material Request Submitted', 'module' => 'procurement'],
                ['key' => 'purchase_order_approved', 'label' => 'Purchase Order Approved', 'module' => 'procurement'],
                ['key' => 'supplier_invoice_submitted', 'label' => 'Supplier Invoice Submitted', 'module' => 'procurement'],
                ['key' => 'supplier_quotation_submitted', 'label' => 'Supplier Quotation Submitted', 'module' => 'procurement'],
                ['key' => 'invoice_generated', 'label' => 'Invoice Generated', 'module' => 'finance'],
                ['key' => 'invoice_overdue', 'label' => 'Invoice Overdue', 'module' => 'finance'],
                ['key' => 'expense_submitted', 'label' => 'Expense Submitted', 'module' => 'finance'],
                ['key' => 'payment_received', 'label' => 'Payment Received', 'module' => 'finance'],
                ['key' => 'stock_low', 'label' => 'Stock Low', 'module' => 'inventory'],
                ['key' => 'employee_checked_in', 'label' => 'Employee Checked In', 'module' => 'field'],
                ['key' => 'attendance_missing', 'label' => 'Attendance Missing', 'module' => 'hr'],
                ['key' => 'daily_report_submitted', 'label' => 'Daily Report Submitted', 'module' => 'field'],
                ['key' => 'equipment_maintenance_due', 'label' => 'Equipment Maintenance Due', 'module' => 'equipment'],
                ['key' => 'equipment_returned', 'label' => 'Equipment Returned', 'module' => 'equipment'],
                ['key' => 'safety_incident_reported', 'label' => 'Safety Incident Reported', 'module' => 'qa_hse'],
                ['key' => 'work_permit_expiring', 'label' => 'Permit Expiring', 'module' => 'qa_hse'],
                ['key' => 'document_uploaded', 'label' => 'Document Uploaded', 'module' => 'documents'],
                ['key' => 'contract_expiring', 'label' => 'Contract Expiring', 'module' => 'crm'],
                ['key' => 'leave_request_pending', 'label' => 'Leave Request Pending', 'module' => 'hr'],
            ],
            'operators' => ['equals', 'not_equals', 'contains', 'starts_with', 'ends_with', 'greater_than', 'less_than', 'between', 'empty', 'not_empty', 'date_before', 'date_after', 'today', 'yesterday'],
            'condition_fields' => ['amount', 'balance_due', 'grand_total', 'total_amount', 'status', 'priority', 'severity', 'module', 'project', 'department', 'budget_percent', 'stock_level', 'due_date', 'submitted_at'],
            'condition_modes' => ['all', 'any'],
            'actions' => [
                ['key' => 'create_insight', 'label' => 'Create In-App Insight', 'category' => 'notification'],
                ['key' => 'create_task', 'label' => 'Create Project Task', 'category' => 'record'],
                ['key' => 'update_record', 'label' => 'Update Record', 'category' => 'record'],
                ['key' => 'send_in_app_notification', 'label' => 'Send In-App Notification', 'category' => 'notification'],
                ['key' => 'send_email', 'label' => 'Send Email', 'category' => 'notification'],
                ['key' => 'send_sms', 'label' => 'Send SMS', 'category' => 'notification'],
                ['key' => 'send_whatsapp', 'label' => 'Send WhatsApp', 'category' => 'notification'],
                ['key' => 'teams_notification', 'label' => 'Teams Notification', 'category' => 'integration'],
                ['key' => 'slack_notification', 'label' => 'Slack Notification', 'category' => 'integration'],
                ['key' => 'call_webhook', 'label' => 'Call Webhook', 'category' => 'integration'],
                ['key' => 'generate_report', 'label' => 'Generate Report', 'category' => 'document'],
                ['key' => 'export_pdf', 'label' => 'Export PDF', 'category' => 'document'],
                ['key' => 'export_excel', 'label' => 'Export Excel', 'category' => 'document'],
                ['key' => 'run_ai_analysis', 'label' => 'Run AI Analysis', 'category' => 'ai'],
                ['key' => 'run_cost_prediction', 'label' => 'Run Cost Prediction', 'category' => 'ai'],
                ['key' => 'run_delay_prediction', 'label' => 'Run Delay Prediction', 'category' => 'ai'],
                ['key' => 'run_risk_analysis', 'label' => 'Run Risk Analysis', 'category' => 'ai'],
            ],
            'schedules' => ['event_driven', 'manual', 'every_minute', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'custom_cron'],
            'approval_modes' => ['none', 'single', 'sequential', 'parallel', 'multi_level', 'conditional', 'department', 'role', 'budget', 'project', 'finance', 'executive'],
        ];
    }

    public function storeRule(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $data = $this->validateRule($request);
        $workflow = $this->workflowDefinition($data);

        $rule = DB::transaction(function () use ($request, $companyId, $data, $workflow) {
            $rule = AutomationRule::query()->create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'module' => $data['module'] ?? $this->moduleForTrigger($data['trigger_event'] ?? 'manual'),
                'status' => $data['status'] ?? 'active',
                'version' => 1,
                'rule_type' => $data['rule_type'] ?? $this->ruleTypeForTrigger($data['trigger_event'] ?? 'manual'),
                'trigger_event' => $data['trigger_event'] ?? 'manual',
                'conditions' => $data['conditions'] ?? $this->defaultConditions($data['rule_type'] ?? 'manual'),
                'actions' => $data['actions'] ?? [['type' => 'create_insight', 'recommendation' => 'Review and close the matched operational action.']],
                'workflow_definition' => $workflow,
                'schedule_config' => $data['schedule_config'] ?? ['frequency' => ($data['trigger_event'] ?? 'manual') === 'manual' ? 'manual' : 'event_driven'],
                'approval_config' => $data['approval_config'] ?? ['mode' => 'none', 'steps' => []],
                'notification_config' => $data['notification_config'] ?? ['channels' => ['in_app']],
                'settings' => $data['settings'] ?? ['retry_policy' => ['max_retries' => 2, 'on_failure' => 'notify_admin']],
                'execution_mode' => $data['execution_mode'] ?? 'sync',
                'severity' => $data['severity'] ?? 'medium',
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $this->user($request)->id,
            ]);

            $this->snapshotVersion($rule, $request);

            return $rule;
        });

        return response()->json(['rule' => $rule->fresh('versions')], 201);
    }

    public function updateRule(Request $request, AutomationRule $rule): JsonResponse
    {
        $this->assertTenant($request, $rule);
        $data = $this->validateRule($request, true);

        DB::transaction(function () use ($request, $rule, $data): void {
            $shouldVersion = $this->shouldVersion($data);
            $payload = $data;

            if ($shouldVersion && collect(['workflow_definition', 'trigger_event', 'conditions', 'actions', 'approval_config'])->intersect(array_keys($data))->isNotEmpty()) {
                $payload['workflow_definition'] = $this->workflowDefinition([...$rule->fresh()->toArray(), ...$data]);
            }

            $rule->update([...$payload, 'version' => $shouldVersion ? $rule->version + 1 : $rule->version]);

            if ($shouldVersion) {
                $this->snapshotVersion($rule->fresh(), $request);
            }
        });

        return response()->json(['rule' => $rule->fresh(['runs', 'versions'])]);
    }

    public function destroyRule(Request $request, AutomationRule $rule): JsonResponse
    {
        $this->assertTenant($request, $rule);

        $rule->delete();

        return response()->json(['message' => 'Automation rule archived.']);
    }

    public function instantiateTemplate(Request $request, string $template): JsonResponse
    {
        $companyId = $this->companyId($request);
        $templateData = $this->templates($companyId)->firstWhere('key', $template)
            ?? $this->templates($companyId)->firstWhere('id', (int) $template);

        abort_if(! $templateData, 404, 'Automation template was not found.');

        $rule = AutomationRule::query()->create([
            'company_id' => $companyId,
            'name' => $request->input('name', $templateData['name']),
            'description' => $templateData['description'] ?? null,
            'module' => $templateData['module'] ?? 'general',
            'status' => 'draft',
            'version' => 1,
            'rule_type' => $this->ruleTypeForTrigger($templateData['trigger_event'] ?? 'manual'),
            'trigger_event' => $templateData['trigger_event'] ?? 'manual',
            'conditions' => $templateData['conditions'] ?? [],
            'actions' => $templateData['actions'] ?? [['type' => 'create_insight']],
            'workflow_definition' => $templateData['workflow_definition'],
            'approval_config' => $templateData['approval_config'] ?? ['mode' => 'none', 'steps' => []],
            'schedule_config' => $templateData['schedule_config'] ?? ['frequency' => 'event_driven'],
            'notification_config' => ['channels' => ['in_app']],
            'settings' => ['source_template' => $templateData['key'] ?? $templateData['id']],
            'severity' => $templateData['severity'] ?? 'medium',
            'is_active' => false,
            'created_by' => $this->user($request)->id,
        ]);

        $this->snapshotVersion($rule, $request);

        return response()->json(['rule' => $rule->fresh('versions')], 201);
    }

    public function rollbackVersion(Request $request, AutomationRule $rule, int $version): JsonResponse
    {
        $this->assertTenant($request, $rule);
        $snapshot = AutomationRuleVersion::query()
            ->forCompany($rule->company_id)
            ->where('automation_rule_id', $rule->id)
            ->where('version', $version)
            ->firstOrFail();

        $payload = collect($snapshot->snapshot)->only([
            'name', 'description', 'module', 'status', 'rule_type', 'trigger_event',
            'conditions', 'actions', 'workflow_definition', 'schedule_config',
            'approval_config', 'notification_config', 'settings', 'execution_mode',
            'severity', 'is_active',
        ])->all();

        $rule->update([...$payload, 'version' => $rule->version + 1]);
        $this->snapshotVersion($rule->fresh(), $request);

        return response()->json(['rule' => $rule->fresh('versions')]);
    }

    public function runRule(Request $request, AutomationRule $rule): JsonResponse
    {
        $this->assertTenant($request, $rule);
        abort_if(! $rule->is_active, 422, 'Automation workflow is not active.');

        return response()->json($this->executeRule($request, $rule, ['source' => 'manual']));
    }

    public function runActive(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $runs = [];

        AutomationRule::query()
            ->forCompany($companyId)
            ->where('is_active', true)
            ->whereIn('status', ['active', 'published'])
            ->get()
            ->each(function (AutomationRule $rule) use ($request, &$runs): void {
                $runs[] = $this->executeRule($request, $rule, ['source' => 'run_active'])['run'];
            });

        return response()->json(['runs' => $runs]);
    }

    public function triggerEvent(Request $request, string $event): JsonResponse
    {
        return response()->json([
            'event' => $event,
            'runs' => $this->dispatchEvent($request, $event, $request->input('payload', [])),
        ]);
    }

    public function dispatchEvent(Request $request, string $event, array $payload = []): array
    {
        $companyId = $this->companyId($request);

        return AutomationRule::query()
            ->forCompany($companyId)
            ->where('is_active', true)
            ->whereIn('status', ['active', 'published'])
            ->where('trigger_event', $event)
            ->get()
            ->map(fn (AutomationRule $rule): AutomationRun => $this->executeRule($request, $rule, [
                'source' => $payload['source'] ?? 'system_event',
                'event' => $event,
                'payload' => $payload,
            ])['run'])
            ->values()
            ->all();
    }

    private function executeRule(Request $request, AutomationRule $rule, array $context = []): array
    {
        $started = microtime(true);
        $matchedRecords = [];
        $results = [];
        $status = 'completed';
        $error = null;

        try {
            $matchedRecords = $this->matchedRecords($rule);

            foreach ($matchedRecords as $record) {
                if (! $this->passesConditions($rule, $record)) {
                    continue;
                }

                foreach ($this->actions($rule) as $action) {
                    $results[] = $this->executeAction($request, $rule, $record, $action);
                }
            }
        } catch (Throwable $exception) {
            $status = 'failed';
            $error = $exception->getMessage();
        }

        $finished = microtime(true);
        $run = AutomationRun::query()->create([
            'company_id' => $rule->company_id,
            'automation_rule_id' => $rule->id,
            'run_number' => $this->nextNumber('AUTO', AutomationRun::class, 'run_number', $rule->company_id),
            'status' => $status,
            'trigger_event' => $rule->trigger_event,
            'matched_count' => count($matchedRecords),
            'actions_executed' => collect($results)->where('status', 'executed')->count(),
            'duration_ms' => (int) round(($finished - $started) * 1000),
            'retry_count' => 0,
            'ip_address' => $request->ip(),
            'matched_records' => $matchedRecords,
            'action_results' => $results,
            'context_payload' => $context,
            'error_message' => $error,
            'started_at' => now(),
            'finished_at' => now(),
            'run_by' => $this->user($request)->id,
        ]);

        $rule->update(['last_run_at' => now()]);

        return ['run' => $run->fresh('rule'), 'rule' => $rule->fresh()];
    }

    private function matchedRecords(AutomationRule $rule): array
    {
        $conditions = $rule->conditions ?? [];
        $trigger = $rule->trigger_event ?: $rule->rule_type;

        if (in_array($trigger, ['manual', 'daily', 'schedule_due', 'every_minute', 'hourly', 'weekly', 'monthly', 'yearly'], true) && $rule->rule_type !== 'manual') {
            $trigger = $rule->rule_type;
        }

        return match ($trigger) {
            'project_overrun', 'budget_exceeded', 'project_delayed' => Project::query()
                ->forCompany($rule->company_id)
                ->where(function ($query) use ($conditions): void {
                    $threshold = ((float) ($conditions['threshold_percent'] ?? 0)) / 100;
                    $query->whereColumn('forecast_to_complete', '>', 'budget_total')
                        ->orWhereIn('health_status', ['at_risk', 'critical'])
                        ->orWhereRaw('(actual_cost + committed_total) > (budget_total * ?)', [1 + $threshold]);
                })
                ->get()
                ->map(fn (Project $project): array => $this->recordPayload('project', $project->id, $project->code.' '.$project->name, [
                    'project_id' => $project->id,
                    'amount' => (float) $project->forecast_to_complete,
                    'budget_total' => (float) $project->budget_total,
                    'budget_percent' => (float) $project->budget_total > 0 ? round((((float) $project->actual_cost + (float) $project->committed_total) / (float) $project->budget_total) * 100, 1) : 0,
                    'status' => $project->status,
                    'severity' => $project->health_status,
                ]))
                ->all(),
            'overdue_invoice', 'invoice_overdue' => Invoice::query()
                ->forCompany($rule->company_id)
                ->whereNotIn('payment_status', ['paid'])
                ->whereDate('due_date', '<', now()->subDays((int) ($conditions['grace_days'] ?? 0))->toDateString())
                ->where('balance_due', '>=', (float) ($conditions['minimum_balance'] ?? 0))
                ->get()
                ->map(fn (Invoice $invoice): array => $this->recordPayload('invoice', $invoice->id, $invoice->invoice_number, [
                    'project_id' => $invoice->project_id,
                    'amount' => (float) $invoice->balance_due,
                    'balance_due' => (float) $invoice->balance_due,
                    'status' => $invoice->status,
                    'due_date' => $invoice->due_date?->toDateString(),
                ]))
                ->all(),
            'low_stock', 'stock_low' => InventoryItem::query()
                ->forCompany($rule->company_id)
                ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
                ->where('status', 'active')
                ->get()
                ->map(fn (InventoryItem $item): array => $this->recordPayload('inventory_item', $item->id, $item->sku.' '.$item->name, [
                    'amount' => (float) $item->quantity_on_hand,
                    'stock_level' => (float) $item->quantity_on_hand,
                    'reorder_level' => (float) $item->reorder_level,
                    'status' => $item->status,
                ]))
                ->all(),
            'hse_open', 'safety_incident_reported' => [
                ...NonConformanceReport::query()->forCompany($rule->company_id)->whereNotIn('status', ['closed'])->get()->map(fn (NonConformanceReport $ncr): array => $this->recordPayload('ncr', $ncr->id, $ncr->ncr_number.' '.$ncr->title, [
                    'project_id' => $ncr->project_id,
                    'severity' => $ncr->severity,
                    'status' => $ncr->status,
                ]))->all(),
                ...SafetyIncident::query()->forCompany($rule->company_id)->whereNotIn('status', ['closed'])->get()->map(fn (SafetyIncident $incident): array => $this->recordPayload('safety_incident', $incident->id, $incident->incident_number, [
                    'project_id' => $incident->project_id,
                    'severity' => $incident->severity,
                    'status' => $incident->status,
                ]))->all(),
            ],
            'permit_expiry', 'work_permit_expiring' => WorkPermit::query()
                ->forCompany($rule->company_id)
                ->whereIn('status', ['approved', 'active'])
                ->whereNotNull('valid_until')
                ->where('valid_until', '<=', now()->addDays((int) ($conditions['days'] ?? 3)))
                ->get()
                ->map(fn (WorkPermit $permit): array => $this->recordPayload('work_permit', $permit->id, $permit->permit_number.' '.str_replace('_', ' ', $permit->permit_type), [
                    'project_id' => $permit->project_id,
                    'due_date' => $permit->valid_until?->toDateString(),
                    'status' => $permit->status,
                ]))
                ->all(),
            'material_request_submitted' => PurchaseRequisition::query()
                ->forCompany($rule->company_id)
                ->where('status', 'submitted')
                ->get()
                ->map(fn (PurchaseRequisition $requisition): array => $this->recordPayload('material_request', $requisition->id, $requisition->requisition_number.' '.$requisition->title, [
                    'project_id' => $requisition->project_id,
                    'amount' => (float) ($requisition->grand_total ?: $requisition->total_estimated),
                    'grand_total' => (float) $requisition->grand_total,
                    'priority' => $requisition->priority,
                    'department' => $requisition->department,
                    'status' => $requisition->status,
                    'due_date' => $requisition->required_by?->toDateString(),
                ]))
                ->all(),
            'expense_submitted' => Expense::query()
                ->forCompany($rule->company_id)
                ->where('status', 'submitted')
                ->get()
                ->map(fn (Expense $expense): array => $this->recordPayload('expense', $expense->id, $expense->expense_number.' '.$expense->description, [
                    'project_id' => $expense->project_id,
                    'amount' => (float) $expense->amount + (float) $expense->tax_amount,
                    'status' => $expense->status,
                ]))
                ->all(),
            'supplier_invoice_submitted' => SupplierInvoice::query()
                ->forCompany($rule->company_id)
                ->where('status', 'submitted')
                ->get()
                ->map(fn (SupplierInvoice $invoice): array => $this->recordPayload('supplier_invoice', $invoice->id, $invoice->invoice_number, [
                    'project_id' => $invoice->project_id,
                    'amount' => (float) $invoice->total_amount,
                    'balance_due' => (float) $invoice->balance_due,
                    'status' => $invoice->status,
                    'due_date' => $invoice->due_date?->toDateString(),
                ]))
                ->all(),
            'daily_report_submitted' => FieldDailyReport::query()
                ->forCompany($rule->company_id)
                ->where('status', 'submitted')
                ->get()
                ->map(fn (FieldDailyReport $report): array => $this->recordPayload('daily_report', $report->id, $report->report_number, [
                    'project_id' => $report->project_id,
                    'status' => $report->status,
                    'submitted_at' => $report->submitted_at?->toISOString(),
                ]))
                ->all(),
            'leave_request_pending' => LeaveRequest::query()
                ->forCompany($rule->company_id)
                ->where('status', 'pending')
                ->get()
                ->map(fn (LeaveRequest $leave): array => $this->recordPayload('leave_request', $leave->id, 'Leave request '.$leave->id, [
                    'amount' => (float) $leave->days,
                    'status' => $leave->status,
                    'due_date' => $leave->starts_on?->toDateString(),
                ]))
                ->all(),
            default => [],
        };
    }

    private function passesConditions(AutomationRule $rule, array $record): bool
    {
        $conditions = $this->normalizedConditions($rule->conditions ?? []);

        if ($conditions === []) {
            return true;
        }

        $mode = $rule->settings['condition_mode'] ?? 'all';
        $results = collect($conditions)->map(fn (array $condition): bool => $this->conditionPasses($condition, $record));

        return $mode === 'any' ? $results->contains(true) : ! $results->contains(false);
    }

    private function conditionPasses(array $condition, array $record): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;
        $actual = data_get($record, "signals.{$field}", data_get($record, $field));

        return match ($operator) {
            'equals' => (string) $actual === (string) $value,
            'not_equals' => (string) $actual !== (string) $value,
            'contains' => str_contains(strtolower((string) $actual), strtolower((string) $value)),
            'starts_with' => str_starts_with(strtolower((string) $actual), strtolower((string) $value)),
            'ends_with' => str_ends_with(strtolower((string) $actual), strtolower((string) $value)),
            'greater_than' => (float) $actual > (float) $value,
            'less_than' => (float) $actual < (float) $value,
            'between' => (float) $actual >= (float) ($condition['min'] ?? 0) && (float) $actual <= (float) ($condition['max'] ?? 0),
            'empty' => blank($actual),
            'not_empty' => filled($actual),
            'date_before' => $actual && strtotime((string) $actual) < strtotime((string) $value),
            'date_after' => $actual && strtotime((string) $actual) > strtotime((string) $value),
            'today' => $actual && date('Y-m-d', strtotime((string) $actual)) === now()->toDateString(),
            'yesterday' => $actual && date('Y-m-d', strtotime((string) $actual)) === now()->subDay()->toDateString(),
            default => true,
        };
    }

    private function executeAction(Request $request, AutomationRule $rule, array $record, array $action): array
    {
        $actionType = $action['type'] ?? 'create_insight';

        return match ($actionType) {
            'create_insight', 'run_ai_analysis', 'run_cost_prediction', 'run_delay_prediction', 'run_risk_analysis' => $this->createInsightAction($request, $rule, $record, $action),
            'create_task' => $this->createTaskAction($request, $rule, $record, $action),
            'send_in_app_notification', 'send_email', 'send_sms', 'send_whatsapp', 'teams_notification', 'slack_notification' => [
                'type' => $actionType,
                'status' => 'executed',
                'message' => $action['message'] ?? $rule->name,
                'record' => $record,
            ],
            'call_webhook' => [
                'type' => 'call_webhook',
                'status' => filled($action['url'] ?? null) ? 'queued' : 'skipped',
                'message' => filled($action['url'] ?? null) ? 'Webhook queued for secure dispatcher.' : 'Webhook URL not configured.',
                'record' => $record,
            ],
            'generate_report', 'export_pdf', 'export_excel' => [
                'type' => $actionType,
                'status' => 'executed',
                'artifact' => "{$actionType}-{$record['type']}-{$record['id']}",
                'record' => $record,
            ],
            default => ['type' => $actionType, 'status' => 'skipped', 'record' => $record],
        };
    }

    private function createInsightAction(Request $request, AutomationRule $rule, array $record, array $action): array
    {
        $insight = AiInsight::query()->updateOrCreate(
            ['company_id' => $rule->company_id, 'source_key' => "automation-{$rule->id}-{$record['type']}-{$record['id']}"],
            [
                'project_id' => $record['project_id'] ?? null,
                'category' => 'automation',
                'severity' => $action['severity'] ?? $rule->severity,
                'title' => $rule->name.': '.$record['label'],
                'narrative' => $action['message'] ?? 'Automation workflow matched '.$record['label'].' from '.$record['type'].'.',
                'recommendation' => $action['recommendation'] ?? 'Review and close the matched operational action.',
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

    private function createTaskAction(Request $request, AutomationRule $rule, array $record, array $action): array
    {
        if (empty($record['project_id'])) {
            return ['type' => 'create_task', 'status' => 'skipped', 'message' => 'Matched record has no project.', 'record' => $record];
        }

        $project = Project::query()->forCompany($rule->company_id)->whereKey($record['project_id'])->first();

        if (! $project) {
            return ['type' => 'create_task', 'status' => 'skipped', 'message' => 'Project was not found.', 'record' => $record];
        }

        $task = ProjectTask::query()->create([
            'company_id' => $rule->company_id,
            'branch_id' => $project->branch_id,
            'project_id' => $project->id,
            'title' => $action['title'] ?? "Automation: {$record['label']}",
            'description' => $action['message'] ?? "Created by automation workflow {$rule->name}.",
            'status' => 'todo',
            'priority' => $rule->severity === 'critical' ? 'urgent' : $rule->severity,
            'created_by' => $this->user($request)->id,
        ]);

        return ['type' => 'create_task', 'status' => 'executed', 'task_id' => $task->id, 'record' => $record];
    }

    private function validateRule(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$sometimes, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'module' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'published', 'paused', 'archived'])],
            'rule_type' => ['nullable', Rule::in($this->ruleTypes())],
            'trigger_event' => ['nullable', 'string', 'max:120'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'workflow_definition' => ['nullable', 'array'],
            'schedule_config' => ['nullable', 'array'],
            'approval_config' => ['nullable', 'array'],
            'notification_config' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'execution_mode' => ['nullable', Rule::in(['sync', 'queued'])],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function workflowDefinition(array $data): array
    {
        if (! empty($data['workflow_definition'])) {
            return $data['workflow_definition'];
        }

        $actions = $this->normalizedActions($data['actions'] ?? [['type' => 'create_insight']]);
        $conditions = $this->normalizedConditions($data['conditions'] ?? []);
        $nodes = [
            ['id' => 'trigger', 'type' => 'trigger', 'label' => $this->triggerLabel($data['trigger_event'] ?? 'manual')],
        ];

        if ($conditions !== []) {
            $nodes[] = ['id' => 'conditions', 'type' => 'condition', 'label' => 'Decision Engine', 'conditions' => $conditions];
        }

        foreach ($actions as $index => $action) {
            $nodes[] = ['id' => "action_{$index}", 'type' => 'action', 'label' => str($action['type'] ?? 'action')->replace('_', ' ')->title()->toString(), 'action' => $action];
        }

        return [
            'schema' => 'structra.workflow.v1',
            'nodes' => $nodes,
            'edges' => collect($nodes)->values()->map(fn (array $node, int $index) => isset($nodes[$index + 1]) ? ['from' => $node['id'], 'to' => $nodes[$index + 1]['id']] : null)->filter()->values()->all(),
        ];
    }

    private function snapshotVersion(AutomationRule $rule, Request $request): void
    {
        AutomationRuleVersion::query()->updateOrCreate(
            ['automation_rule_id' => $rule->id, 'version' => $rule->version],
            [
                'company_id' => $rule->company_id,
                'snapshot' => collect($rule->fresh()->toArray())->except(['versions', 'runs'])->all(),
                'changed_by' => $this->user($request)->id,
                'changed_at' => now(),
            ],
        );
    }

    private function shouldVersion(array $data): bool
    {
        return collect($data)->keys()->intersect([
            'name', 'description', 'module', 'status', 'rule_type', 'trigger_event',
            'conditions', 'actions', 'workflow_definition', 'schedule_config',
            'approval_config', 'notification_config', 'settings', 'execution_mode',
        ])->isNotEmpty();
    }

    private function templates(int $companyId): Collection
    {
        $system = collect($this->systemTemplates());
        $company = AutomationTemplate::query()
            ->where(function ($query) use ($companyId): void {
                $query->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->latest()
            ->get()
            ->map(fn (AutomationTemplate $template): array => [
                'id' => $template->id,
                'key' => "template_{$template->id}",
                'name' => $template->name,
                'module' => $template->module,
                'category' => $template->category,
                'description' => $template->description,
                'workflow_definition' => $template->workflow_definition,
                'conditions' => $template->conditions,
                'actions' => $template->actions,
                'approval_config' => $template->approval_config,
                'schedule_config' => $template->schedule_config,
                'is_system' => $template->is_system,
            ]);

        return $system->concat($company);
    }

    private function systemTemplates(): array
    {
        return [
            $this->template('procurement_approval', 'Procurement Approval', 'procurement', 'material_request_submitted', [['field' => 'amount', 'operator' => 'greater_than', 'value' => 20000]], [['type' => 'create_insight', 'recommendation' => 'Route to procurement and finance approvers.']], ['mode' => 'sequential', 'steps' => ['Site Manager', 'Procurement Manager', 'Finance Director']]),
            $this->template('invoice_approval', 'Invoice Approval', 'finance', 'supplier_invoice_submitted', [['field' => 'amount', 'operator' => 'greater_than', 'value' => 0]], [['type' => 'create_insight', 'recommendation' => 'Review three-way match before finance approval.']], ['mode' => 'single', 'steps' => ['Finance']]),
            $this->template('budget_alert', 'Budget Alert', 'projects', 'budget_exceeded', [['field' => 'budget_percent', 'operator' => 'greater_than', 'value' => 100]], [['type' => 'create_task', 'title' => 'Review budget variance'], ['type' => 'create_insight']]),
            $this->template('low_inventory', 'Low Inventory', 'inventory', 'stock_low', [], [['type' => 'create_insight', 'recommendation' => 'Create replenishment request for low-stock item.']]),
            $this->template('daily_report_reminder', 'Daily Report Reminder', 'field', 'daily_report_submitted', [], [['type' => 'send_in_app_notification', 'message' => 'Daily report is awaiting approval.']]),
            $this->template('safety_incident', 'Safety Incident', 'qa_hse', 'safety_incident_reported', [['field' => 'severity', 'operator' => 'not_empty']], [['type' => 'create_task', 'title' => 'Investigate safety incident'], ['type' => 'create_insight']]),
            $this->template('payment_reminder', 'Payment Reminder', 'finance', 'invoice_overdue', [['field' => 'balance_due', 'operator' => 'greater_than', 'value' => 0]], [['type' => 'send_in_app_notification', 'message' => 'Invoice is overdue.'], ['type' => 'create_insight']]),
            $this->template('leave_approval', 'Leave Approval', 'hr', 'leave_request_pending', [], [['type' => 'create_insight', 'recommendation' => 'HR approval required.']], ['mode' => 'single', 'steps' => ['HR']]),
        ];
    }

    private function template(string $key, string $name, string $module, string $trigger, array $conditions, array $actions, array $approval = ['mode' => 'none', 'steps' => []]): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'module' => $module,
            'category' => 'system',
            'description' => "{$name} workflow template",
            'trigger_event' => $trigger,
            'conditions' => $conditions,
            'actions' => $actions,
            'approval_config' => $approval,
            'schedule_config' => ['frequency' => 'event_driven'],
            'workflow_definition' => $this->workflowDefinition(['trigger_event' => $trigger, 'conditions' => $conditions, 'actions' => $actions]),
            'is_system' => true,
        ];
    }

    private function analytics(EloquentCollection $rules, EloquentCollection $runs): array
    {
        return [
            'workflow_executions' => $runs->groupBy(fn (AutomationRun $run) => $run->started_at?->toDateString() ?? 'unknown')->map(fn (Collection $items, string $date): array => ['date' => $date, 'executions' => $items->count()])->values(),
            'failures' => $runs->where('status', 'failed')->groupBy(fn (AutomationRun $run) => $run->rule?->name ?? 'Unknown')->map(fn (Collection $items, string $name): array => ['name' => $name, 'failures' => $items->count()])->values(),
            'top_used_workflows' => $rules->sortByDesc('runs_count')->take(8)->map(fn (AutomationRule $rule): array => ['name' => $rule->name, 'runs' => $rule->runs_count])->values(),
            'most_triggered_events' => $rules->groupBy('trigger_event')->map(fn (Collection $items, string $event): array => ['event' => $event, 'workflows' => $items->count()])->values(),
            'notification_statistics' => $runs->flatMap(fn (AutomationRun $run) => collect($run->action_results ?? [])->whereIn('type', ['send_in_app_notification', 'send_email', 'send_sms', 'send_whatsapp']))->groupBy('type')->map(fn (Collection $items, string $type): array => ['type' => $type, 'sent' => $items->count()])->values(),
        ];
    }

    private function actions(AutomationRule $rule): array
    {
        return $this->normalizedActions($rule->actions ?? [['type' => 'create_insight']]);
    }

    private function normalizedActions(array $actions): array
    {
        if (isset($actions['type'])) {
            return [$actions];
        }

        return array_values($actions);
    }

    private function normalizedConditions(array $conditions): array
    {
        if ($conditions === []) {
            return [];
        }

        if (isset($conditions['field'])) {
            return [$conditions];
        }

        if (array_is_list($conditions)) {
            return collect($conditions)
                ->filter(fn ($condition): bool => is_array($condition) && filled($condition['field'] ?? null))
                ->values()
                ->all();
        }

        return [];
    }

    private function recordPayload(string $type, int $id, string $label, array $signals = []): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'project_id' => $signals['project_id'] ?? null,
            'amount' => $signals['amount'] ?? null,
            'signals' => $signals,
        ];
    }

    private function ruleTypes(): array
    {
        return ['manual', 'project_overrun', 'overdue_invoice', 'low_stock', 'hse_open', 'permit_expiry', 'event_workflow'];
    }

    private function ruleTypeForTrigger(string $trigger): string
    {
        return match ($trigger) {
            'budget_exceeded', 'project_delayed' => 'project_overrun',
            'invoice_overdue' => 'overdue_invoice',
            'stock_low' => 'low_stock',
            'safety_incident_reported' => 'hse_open',
            'work_permit_expiring' => 'permit_expiry',
            'manual' => 'manual',
            default => 'event_workflow',
        };
    }

    private function moduleForTrigger(string $trigger): string
    {
        return collect($this->catalog()['triggers'])->firstWhere('key', $trigger)['module'] ?? 'general';
    }

    private function triggerLabel(string $trigger): string
    {
        return collect($this->catalog()['triggers'])->firstWhere('key', $trigger)['label'] ?? str($trigger)->replace('_', ' ')->title()->toString();
    }

    private function defaultConditions(string $ruleType): array
    {
        return match ($ruleType) {
            'project_overrun' => ['threshold_percent' => 0],
            'overdue_invoice' => ['grace_days' => 0, 'minimum_balance' => 0],
            'low_stock' => ['compare' => 'quantity_on_hand <= reorder_level'],
            'hse_open' => ['include_ncrs' => true, 'include_incidents' => true],
            'permit_expiry' => ['days' => 3],
            default => [],
        };
    }

    private function assertTenant(Request $request, AutomationRule $rule): void
    {
        abort_if((int) $rule->company_id !== $this->companyId($request), 404);
    }
}
