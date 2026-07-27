<?php

namespace App\Http\Controllers\Api;

use App\Models\BudgetLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetLineController extends ApiController
{
    public function store(Request $request, int $project): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        $data = $this->validated($request, $projectModel->id);
        $data['cost_code'] = $this->suppliedCode($data['cost_code'] ?? null)
            ?? $this->nextProjectCode($this->codePrefix($data['category'] ?? $data['description'], 'CST'), BudgetLine::class, 'cost_code', $projectModel->id);

        $budgetLine = BudgetLine::query()->create([
            'company_id' => $projectModel->company_id,
            'branch_id' => $projectModel->branch_id,
            'project_id' => $projectModel->id,
            ...$data,
            'forecast_amount' => $data['forecast_amount'] ?? $data['budget_amount'],
        ]);

        $this->syncProjectCosts($projectModel);

        return response()->json(['budget_line' => $budgetLine], 201);
    }

    public function update(Request $request, int $project, BudgetLine $budgetLine): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        abort_if($budgetLine->project_id !== $projectModel->id || $budgetLine->company_id !== $projectModel->company_id, 404);

        $data = $this->validated($request, $projectModel->id, true);

        if (array_key_exists('cost_code', $data)) {
            $data['cost_code'] = $this->suppliedCode($data['cost_code'])
                ?? $this->nextProjectCode($this->codePrefix($data['category'] ?? $budgetLine->category, 'CST'), BudgetLine::class, 'cost_code', $projectModel->id);
        }

        $budgetLine->update($data);
        $this->syncProjectCosts($projectModel);

        return response()->json(['budget_line' => $budgetLine->fresh()]);
    }

    public function destroy(Request $request, int $project, BudgetLine $budgetLine): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        abort_if($budgetLine->project_id !== $projectModel->id || $budgetLine->company_id !== $projectModel->company_id, 404);

        $budgetLine->delete();
        $this->syncProjectCosts($projectModel);

        return response()->json(['message' => 'Budget line archived.']);
    }

    private function validated(Request $request, int $projectId, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'cost_code' => [
                $partial ? 'sometimes' : 'nullable',
                'nullable',
                'string',
                'max:40',
                Rule::unique('budget_lines')->where('project_id', $projectId)->ignore($request->route('budgetLine')),
            ],
            'description' => [$prefix, 'string', 'max:255'],
            'category' => [$partial ? 'sometimes' : 'nullable', Rule::in(['materials', 'labour', 'equipment', 'subcontractor', 'overheads', 'procurement', 'other'])],
            'budget_amount' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'committed_amount' => ['nullable', 'numeric', 'min:0'],
            'actual_amount' => ['nullable', 'numeric', 'min:0'],
            'forecast_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
