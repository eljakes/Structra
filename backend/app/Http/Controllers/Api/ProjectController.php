<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->forCompany($this->companyId($request))
            ->with(['branch', 'client'])
            ->withCount(['tasks', 'budgetLines', 'purchaseRequisitions', 'purchaseOrders', 'documents', 'drawings'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('projects')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['nullable', Rule::in(['planning', 'active', 'on_hold', 'completed', 'cancelled'])],
            'health_status' => ['nullable', Rule::in(['on_track', 'at_risk', 'critical'])],
            'risk_level' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'site_address' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'progress_percent' => ['nullable', 'integer', 'between:0,100'],
            'start_date' => ['nullable', 'date'],
            'target_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $branch = Branch::query()->forCompany($companyId)->whereKey($data['branch_id'])->firstOrFail();
        $clientId = $data['client_id'] ?? null;

        if ($clientId) {
            Client::query()->forCompany($companyId)->whereKey($clientId)->firstOrFail();
        } elseif (! empty($data['client_name'])) {
            $client = Client::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branch->id,
                'name' => $data['client_name'],
                'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            ]);
            $clientId = $client->id;
        }

        $project = Project::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branch->id,
            'client_id' => $clientId,
            'code' => $data['code'] ?? $this->nextNumber('PRJ', Project::class, 'code', $companyId),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'planning',
            'health_status' => $data['health_status'] ?? 'on_track',
            'risk_level' => $data['risk_level'] ?? 'medium',
            'site_address' => $data['site_address'] ?? null,
            'country' => strtoupper($data['country'] ?? $branch->country),
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            'contract_value' => $data['contract_value'] ?? 0,
            'progress_percent' => $data['progress_percent'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'target_end_date' => $data['target_end_date'] ?? null,
            'created_by' => $this->user($request)->id,
            'updated_by' => $this->user($request)->id,
        ]);

        return response()->json(['project' => $project->load(['branch', 'client'])], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $project = Project::query()
            ->forCompany($this->companyId($request))
            ->whereKey($project->id)
            ->with([
                'branch',
                'client',
                'tasks.assignee',
                'budgetLines',
                'purchaseRequisitions.lines',
                'purchaseOrders.supplier',
                'purchaseOrders.lines',
                'documents',
                'drawings.revisions',
                'fieldDailyReports.issues',
                'fieldIssues',
            ])
            ->firstOrFail();

        return response()->json(['project' => $project]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $project = $this->projectForTenant($request, $project->id);

        $data = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['sometimes', Rule::in(['planning', 'active', 'on_hold', 'completed', 'cancelled'])],
            'health_status' => ['sometimes', Rule::in(['on_track', 'at_risk', 'critical'])],
            'risk_level' => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'site_address' => ['nullable', 'string', 'max:2000'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'contract_value' => ['sometimes', 'numeric', 'min:0'],
            'progress_percent' => ['sometimes', 'integer', 'between:0,100'],
            'start_date' => ['nullable', 'date'],
            'target_end_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date'],
        ]);

        if (isset($data['client_id'])) {
            Client::query()->forCompany($this->companyId($request))->whereKey($data['client_id'])->firstOrFail();
        }

        $project->update([
            ...$data,
            'currency' => isset($data['currency']) ? strtoupper($data['currency']) : $project->currency,
            'updated_by' => $this->user($request)->id,
        ]);

        return response()->json(['project' => $project->fresh(['branch', 'client'])]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $project = $this->projectForTenant($request, $project->id);
        $project->delete();

        return response()->json(['message' => 'Project archived.']);
    }

    public function timeline(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $items = Project::query()
            ->forCompany($companyId)
            ->with('tasks:id,project_id,title,status,start_date,due_date,progress_percent')
            ->whereIn('status', ['planning', 'active', 'on_hold'])
            ->orderBy('target_end_date')
            ->get(['id', 'code', 'name', 'status', 'start_date', 'target_end_date', 'progress_percent']);

        return response()->json(['timeline' => $items]);
    }
}
