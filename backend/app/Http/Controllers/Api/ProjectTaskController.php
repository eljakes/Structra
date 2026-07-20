<?php

namespace App\Http\Controllers\Api;

use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectTaskController extends ApiController
{
    public function store(Request $request, int $project): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        $data = $this->validated($request);
        $this->validateAssignee($request, $data['assigned_to'] ?? null);

        $task = ProjectTask::query()->create([
            'company_id' => $projectModel->company_id,
            'branch_id' => $projectModel->branch_id,
            'project_id' => $projectModel->id,
            ...$data,
            'created_by' => $this->user($request)->id,
            'updated_by' => $this->user($request)->id,
            'completed_at' => ($data['status'] ?? 'todo') === 'done' ? now() : null,
        ]);

        $this->syncProjectProgress($projectModel);

        return response()->json(['task' => $task->load('assignee')], 201);
    }

    public function update(Request $request, int $project, ProjectTask $task): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        abort_if($task->project_id !== $projectModel->id || $task->company_id !== $projectModel->company_id, 404);

        $data = $this->validated($request, true);
        $this->validateAssignee($request, $data['assigned_to'] ?? null);

        if (($data['status'] ?? $task->status) === 'done' && ! $task->completed_at) {
            $data['completed_at'] = now();
            $data['progress_percent'] = 100;
        }

        $task->update([
            ...$data,
            'updated_by' => $this->user($request)->id,
        ]);

        $this->syncProjectProgress($projectModel);

        return response()->json(['task' => $task->fresh('assignee')]);
    }

    public function destroy(Request $request, int $project, ProjectTask $task): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        abort_if($task->project_id !== $projectModel->id || $task->company_id !== $projectModel->company_id, 404);

        $task->delete();
        $this->syncProjectProgress($projectModel);

        return response()->json(['message' => 'Task archived.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'assigned_to' => ['nullable', 'integer'],
            'title' => [$prefix, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => [$partial ? 'sometimes' : 'nullable', Rule::in(['todo', 'in_progress', 'blocked', 'done', 'cancelled'])],
            'priority' => [$partial ? 'sometimes' : 'nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'progress_percent' => [$partial ? 'sometimes' : 'nullable', 'integer', 'between:0,100'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'dependencies' => ['nullable', 'array'],
        ]);
    }

    private function validateAssignee(Request $request, ?int $assignedTo): void
    {
        if (! $assignedTo) {
            return;
        }

        User::query()
            ->where('company_id', $this->companyId($request))
            ->whereKey($assignedTo)
            ->firstOrFail();
    }
}
