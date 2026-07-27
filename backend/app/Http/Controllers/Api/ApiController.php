<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class ApiController extends Controller
{
    protected function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    protected function companyId(Request $request): int
    {
        return (int) $this->user($request)->company_id;
    }

    protected function publishAutomationEvent(Request $request, string $event, array $payload = []): void
    {
        try {
            app(AutomationController::class)->dispatchEvent($request, $event, $payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function nextNumber(string $prefix, string $modelClass, string $column, int $companyId): string
    {
        return $this->nextScopedSequenceCode(
            "{$this->codePrefix($prefix)}-".now()->format('ym'),
            $modelClass,
            $column,
            ['company_id' => $companyId],
            5,
        );
    }

    protected function nextCompanyCode(string $prefix, string $modelClass, string $column, int $companyId, int $pad = 6): string
    {
        return $this->nextScopedSequenceCode($prefix, $modelClass, $column, ['company_id' => $companyId], $pad);
    }

    protected function nextProjectCode(string $prefix, string $modelClass, string $column, int $projectId, int $pad = 6): string
    {
        return $this->nextScopedSequenceCode($prefix, $modelClass, $column, ['project_id' => $projectId], $pad);
    }

    protected function suppliedCode(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return strtoupper(trim($value));
    }

    protected function codePrefix(?string $value, string $fallback = 'GEN'): string
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));

        if (blank($prefix)) {
            $prefix = $fallback;
        }

        return substr($prefix, 0, 3);
    }

    private function nextScopedSequenceCode(string $prefix, string $modelClass, string $column, array $scope, int $pad): string
    {
        /** @var class-string<Model> $modelClass */
        $model = new $modelClass();
        $table = $model->getTable();
        $normalizedPrefix = $this->sequencePrefix($prefix);
        $query = DB::table($table);

        foreach ($scope as $field => $value) {
            $query->where($field, $value);
        }

        $next = (clone $query)
            ->where($column, 'like', "{$normalizedPrefix}-%")
            ->count() + 1;

        do {
            $candidate = sprintf("%s-%0{$pad}d", $normalizedPrefix, $next);
            $existsQuery = DB::table($table);

            foreach ($scope as $field => $value) {
                $existsQuery->where($field, $value);
            }

            $exists = $existsQuery->where($column, $candidate)->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    private function sequencePrefix(string $prefix): string
    {
        $normalized = preg_replace('/[^A-Z0-9-]/', '', strtoupper($prefix));

        return blank($normalized) ? 'GEN' : trim($normalized, '-');
    }

    protected function projectForTenant(Request $request, int|string $projectId): Project
    {
        return Project::query()
            ->forCompany($this->companyId($request))
            ->whereKey($projectId)
            ->firstOrFail();
    }

    protected function syncProjectCosts(Project $project): void
    {
        $budgetLines = BudgetLine::query()
            ->where('project_id', $project->id)
            ->get()
            ->keyBy('cost_code');

        $poLines = DB::table('purchase_order_lines')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.project_id', $project->id)
            ->whereNotIn('purchase_orders.status', ['cancelled', 'draft'])
            ->select([
                'purchase_order_lines.cost_code',
                DB::raw('sum(purchase_order_lines.line_total) as committed_total'),
                DB::raw("sum(case when purchase_orders.status in ('delivered', 'closed') then purchase_order_lines.line_total else 0 end) as actual_total"),
            ])
            ->groupBy('purchase_order_lines.cost_code')
            ->get();

        foreach ($poLines as $poLine) {
            if (! $poLine->cost_code) {
                continue;
            }

            $budgetLine = $budgetLines->get($poLine->cost_code);

            if (! $budgetLine) {
                $budgetLine = BudgetLine::query()->create([
                    'company_id' => $project->company_id,
                    'branch_id' => $project->branch_id,
                    'project_id' => $project->id,
                    'cost_code' => $poLine->cost_code,
                    'description' => 'Procurement commitment '.$poLine->cost_code,
                    'category' => 'procurement',
                    'budget_amount' => 0,
                ]);
            }

            $committed = (float) $poLine->committed_total;
            $actual = (float) $poLine->actual_total;

            $budgetLine->forceFill([
                'committed_amount' => $committed,
                'actual_amount' => $actual,
                'forecast_amount' => max((float) $budgetLine->budget_amount, $actual + max(0, $committed - $actual)),
            ])->save();
        }

        $totals = BudgetLine::query()
            ->where('project_id', $project->id)
            ->selectRaw('coalesce(sum(budget_amount), 0) as budget_total, coalesce(sum(committed_amount), 0) as committed_total, coalesce(sum(actual_amount), 0) as actual_cost, coalesce(sum(forecast_amount), 0) as forecast_to_complete')
            ->first();

        $project->forceFill([
            'budget_total' => $totals->budget_total ?? 0,
            'committed_total' => $totals->committed_total ?? 0,
            'actual_cost' => $totals->actual_cost ?? 0,
            'forecast_to_complete' => $totals->forecast_to_complete ?? 0,
        ])->save();
    }

    protected function syncProjectProgress(Project $project): void
    {
        $average = (int) round((float) $project->tasks()->avg('progress_percent'));

        if ($project->tasks()->count() === 0) {
            return;
        }

        $lateOpenTasks = $project->tasks()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        $health = $lateOpenTasks > 0 ? 'at_risk' : 'on_track';

        $project->forceFill([
            'progress_percent' => max(0, min(100, $average)),
            'health_status' => $health,
        ])->save();
    }

    protected function syncPurchaseOrderTotals(PurchaseOrder $purchaseOrder): void
    {
        $subtotal = (float) $purchaseOrder->lines()->sum('line_total');
        $tax = (float) $purchaseOrder->tax_amount;

        $purchaseOrder->forceFill([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal + $tax,
        ])->save();
    }
}
