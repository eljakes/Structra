<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\NonConformanceReport;
use App\Models\Project;
use App\Models\SafetyIncident;
use App\Models\SafetyObservation;
use App\Models\ToolboxTalk;
use App\Models\WorkPermit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ComplianceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        return response()->json([
            'inspections' => Inspection::query()->forCompany($companyId)->with(['project:id,code,name', 'items', 'ncrs'])->latest()->limit(100)->get(),
            'ncrs' => NonConformanceReport::query()->forCompany($companyId)->with(['project:id,code,name', 'inspection:id,inspection_number'])->latest()->limit(100)->get(),
            'incidents' => SafetyIncident::query()->forCompany($companyId)->with('project:id,code,name')->latest('occurred_at')->limit(100)->get(),
            'toolbox_talks' => ToolboxTalk::query()->forCompany($companyId)->with('project:id,code,name')->latest('talk_date')->limit(80)->get(),
            'observations' => SafetyObservation::query()->forCompany($companyId)->with('project:id,code,name')->latest('observed_at')->limit(100)->get(),
            'permits' => WorkPermit::query()->forCompany($companyId)->with('project:id,code,name')->latest()->limit(100)->get(),
            'summary' => [
                'open_ncrs' => NonConformanceReport::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->count(),
                'open_incidents' => SafetyIncident::query()->forCompany($companyId)->whereNotIn('status', ['closed'])->count(),
                'active_permits' => WorkPermit::query()->forCompany($companyId)->whereIn('status', ['approved', 'active'])->count(),
                'failed_inspections' => Inspection::query()->forCompany($companyId)->where('status', 'failed')->count(),
            ],
        ]);
    }

    public function storeInspection(Request $request, int $project): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        $data = $request->validate([
            'type' => ['nullable', Rule::in(['quality', 'workmanship', 'materials', 'handover', 'snagging', 'safety', 'environmental', 'ppe', 'fire', 'risk_assessment', 'punch_list'])],
            'area' => ['nullable', 'string', 'max:255'],
            'scheduled_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['nullable', 'array'],
            'items.*.checklist_item' => ['required_with:items', 'string', 'max:255'],
            'items.*.requirement' => ['nullable', 'string', 'max:255'],
            'items.*.result' => ['nullable', Rule::in(['pending', 'pass', 'fail', 'na'])],
            'items.*.severity' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $inspection = DB::transaction(function () use ($request, $projectModel, $data) {
            $inspection = Inspection::query()->create([
                'company_id' => $projectModel->company_id,
                'branch_id' => $projectModel->branch_id,
                'project_id' => $projectModel->id,
                'inspection_number' => $this->nextNumber('INS', Inspection::class, 'inspection_number', $projectModel->company_id),
                'type' => $data['type'] ?? 'quality',
                'area' => $data['area'] ?? null,
                'status' => 'scheduled',
                'scheduled_on' => $data['scheduled_on'] ?? now()->toDateString(),
                'inspector_id' => $this->user($request)->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                InspectionItem::query()->create([
                    'company_id' => $projectModel->company_id,
                    'inspection_id' => $inspection->id,
                    'checklist_item' => $item['checklist_item'],
                    'requirement' => $item['requirement'] ?? null,
                    'result' => $item['result'] ?? 'pending',
                    'severity' => $item['severity'] ?? 'medium',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $inspection;
        });

        return response()->json(['inspection' => $inspection->fresh(['project', 'items', 'ncrs'])], 201);
    }

    public function completeInspection(Request $request, Inspection $inspection): JsonResponse
    {
        $this->assertTenant($request, $inspection);

        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'integer'],
            'items.*.result' => ['required_with:items', Rule::in(['pass', 'fail', 'na'])],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        DB::transaction(function () use ($inspection, $data) {
            foreach ($data['items'] ?? [] as $itemData) {
                $item = InspectionItem::query()
                    ->where('company_id', $inspection->company_id)
                    ->where('inspection_id', $inspection->id)
                    ->whereKey($itemData['id'])
                    ->firstOrFail();

                $item->update([
                    'result' => $itemData['result'],
                    'notes' => $itemData['notes'] ?? $item->notes,
                    'corrected_at' => $itemData['result'] === 'pass' && $item->result === 'fail' ? now() : $item->corrected_at,
                ]);
            }

            $items = $inspection->items()->get();
            $scored = $items->whereIn('result', ['pass', 'fail']);
            $passed = $scored->where('result', 'pass')->count();
            $failed = $scored->where('result', 'fail')->count();
            $score = $scored->count() > 0 ? (int) round(($passed / $scored->count()) * 100) : 100;

            $inspection->update([
                'status' => $failed > 0 ? 'failed' : 'passed',
                'score' => $score,
                'completed_at' => now(),
                'notes' => $data['notes'] ?? $inspection->notes,
            ]);
        });

        return response()->json(['inspection' => $inspection->fresh(['project', 'items', 'ncrs'])]);
    }

    public function storeNcr(Request $request, int $project): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        $data = $request->validate([
            'inspection_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'department' => ['nullable', Rule::in(['qa', 'hse', 'technical', 'operations'])],
            'category' => ['nullable', Rule::in(['concrete', 'reinforcement', 'masonry', 'plumbing', 'electrical', 'finishes', 'safety', 'mechanical', 'environmental', 'documentation'])],
            'location' => ['nullable', 'string', 'max:255'],
            'contractor' => ['nullable', 'string', 'max:255'],
            'subcontractor' => ['nullable', 'string', 'max:255'],
            'reference_documents' => ['nullable', 'array'],
            'reference_documents.*' => ['string', 'max:255'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['string', 'max:255'],
            'root_cause' => ['nullable', 'string', 'max:4000'],
            'corrective_action' => ['nullable', 'string', 'max:4000'],
            'preventive_action' => ['nullable', 'string', 'max:4000'],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'assigned_to' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (! empty($data['inspection_id'])) {
            Inspection::query()
                ->forCompany($projectModel->company_id)
                ->where('project_id', $projectModel->id)
                ->whereKey($data['inspection_id'])
                ->firstOrFail();
        }

        $ncr = NonConformanceReport::query()->create([
            'company_id' => $projectModel->company_id,
            'branch_id' => $projectModel->branch_id,
            'project_id' => $projectModel->id,
            'inspection_id' => $data['inspection_id'] ?? null,
            'ncr_number' => $this->nextNumber('NCR', NonConformanceReport::class, 'ncr_number', $projectModel->company_id),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'department' => $data['department'] ?? 'qa',
            'category' => $data['category'] ?? null,
            'location' => $data['location'] ?? null,
            'contractor' => $data['contractor'] ?? null,
            'subcontractor' => $data['subcontractor'] ?? null,
            'reference_documents' => $data['reference_documents'] ?? [],
            'evidence' => $data['evidence'] ?? [],
            'root_cause' => $data['root_cause'] ?? null,
            'corrective_action' => $data['corrective_action'] ?? null,
            'preventive_action' => $data['preventive_action'] ?? null,
            'severity' => $data['severity'] ?? 'medium',
            'status' => 'open',
            'assigned_to' => $data['assigned_to'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'raised_by' => $this->user($request)->id,
        ]);

        return response()->json(['ncr' => $ncr->load(['project', 'inspection'])], 201);
    }

    public function closeNcr(Request $request, NonConformanceReport $ncr): JsonResponse
    {
        $this->assertTenant($request, $ncr);

        $data = $request->validate([
            'root_cause' => ['nullable', 'string', 'max:4000'],
            'corrective_action' => ['required', 'string', 'max:4000'],
            'preventive_action' => ['nullable', 'string', 'max:4000'],
            'verification_notes' => ['nullable', 'string', 'max:4000'],
            'status' => ['nullable', Rule::in(['corrective_action', 'under_review', 'closed', 'rework_required', 'reopened'])],
        ]);

        $status = $data['status'] ?? 'closed';
        $closed = $status === 'closed';
        $reopened = in_array($status, ['rework_required', 'reopened'], true);

        $ncr->update([
            'status' => $status,
            'root_cause' => $data['root_cause'] ?? $ncr->root_cause,
            'corrective_action' => $data['corrective_action'],
            'preventive_action' => $data['preventive_action'] ?? $ncr->preventive_action,
            'verification_notes' => $data['verification_notes'] ?? $ncr->verification_notes,
            'closed_by' => $closed ? $this->user($request)->id : null,
            'closed_at' => $closed ? now() : null,
            'verified_at' => $closed ? now() : $ncr->verified_at,
            'reopened_at' => $reopened ? now() : $ncr->reopened_at,
        ]);

        return response()->json(['ncr' => $ncr->fresh(['project', 'inspection'])]);
    }

    public function storeIncident(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'incident_type' => ['nullable', Rule::in(['near_miss', 'first_aid', 'medical_treatment', 'lost_time', 'property_damage', 'environmental'])],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'occurred_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'injured_person' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:4000'],
            'immediate_action' => ['nullable', 'string', 'max:4000'],
            'assigned_to' => ['nullable', 'integer'],
        ]);

        [$project, $branchId] = $this->projectAndBranch($request, $data);

        $incident = SafetyIncident::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'project_id' => $project?->id,
            'incident_number' => $this->nextNumber('HSE', SafetyIncident::class, 'incident_number', $companyId),
            'incident_type' => $data['incident_type'] ?? 'near_miss',
            'severity' => $data['severity'] ?? 'medium',
            'status' => 'reported',
            'occurred_at' => $data['occurred_at'] ?? now(),
            'location' => $data['location'] ?? null,
            'injured_person' => $data['injured_person'] ?? null,
            'description' => $data['description'],
            'immediate_action' => $data['immediate_action'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'reported_by' => $this->user($request)->id,
        ]);

        return response()->json(['incident' => $incident->load('project')], 201);
    }

    public function closeIncident(Request $request, SafetyIncident $incident): JsonResponse
    {
        $this->assertTenant($request, $incident);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['investigating', 'corrective_action', 'closed'])],
            'root_cause' => ['nullable', 'string', 'max:4000'],
            'corrective_action' => ['nullable', 'string', 'max:4000'],
        ]);

        $status = $data['status'] ?? 'closed';

        $incident->update([
            'status' => $status,
            'root_cause' => $data['root_cause'] ?? $incident->root_cause,
            'corrective_action' => $data['corrective_action'] ?? $incident->corrective_action,
            'closed_at' => $status === 'closed' ? now() : null,
        ]);

        return response()->json(['incident' => $incident->fresh('project')]);
    }

    public function storeToolboxTalk(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'topic' => ['required', 'string', 'max:255'],
            'talk_date' => ['nullable', 'date'],
            'attendee_count' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'summary' => ['nullable', 'string', 'max:4000'],
            'hazards_discussed' => ['nullable', 'array'],
        ]);

        [$project, $branchId] = $this->projectAndBranch($request, $data);

        $talk = ToolboxTalk::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'project_id' => $project?->id,
            'talk_number' => $this->nextNumber('TBT', ToolboxTalk::class, 'talk_number', $companyId),
            'topic' => $data['topic'],
            'talk_date' => $data['talk_date'] ?? now()->toDateString(),
            'presenter_id' => $this->user($request)->id,
            'attendee_count' => $data['attendee_count'] ?? 0,
            'summary' => $data['summary'] ?? null,
            'hazards_discussed' => $data['hazards_discussed'] ?? [],
            'status' => 'completed',
        ]);

        return response()->json(['toolbox_talk' => $talk->load('project')], 201);
    }

    public function storeObservation(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'observation_type' => ['nullable', Rule::in(['safe', 'unsafe', 'near_miss', 'hazard', 'environmental', 'ppe', 'fire'])],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:4000'],
            'corrective_action' => ['nullable', 'string', 'max:4000'],
            'observed_at' => ['nullable', 'date'],
        ]);

        [$project, $branchId] = $this->projectAndBranch($request, $data);

        $observation = SafetyObservation::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'project_id' => $project?->id,
            'observation_number' => $this->nextNumber('OBS', SafetyObservation::class, 'observation_number', $companyId),
            'observation_type' => $data['observation_type'] ?? 'unsafe',
            'severity' => $data['severity'] ?? 'medium',
            'status' => ($data['observation_type'] ?? 'unsafe') === 'safe' ? 'closed' : 'open',
            'location' => $data['location'] ?? null,
            'description' => $data['description'],
            'corrective_action' => $data['corrective_action'] ?? null,
            'observed_at' => $data['observed_at'] ?? now(),
            'observed_by' => $this->user($request)->id,
            'closed_at' => ($data['observation_type'] ?? 'unsafe') === 'safe' ? now() : null,
        ]);

        return response()->json(['observation' => $observation->load('project')], 201);
    }

    public function closeObservation(Request $request, SafetyObservation $observation): JsonResponse
    {
        $this->assertTenant($request, $observation);

        $data = $request->validate([
            'corrective_action' => ['required', 'string', 'max:4000'],
        ]);

        $observation->update([
            'status' => 'closed',
            'corrective_action' => $data['corrective_action'],
            'closed_at' => now(),
        ]);

        return response()->json(['observation' => $observation->fresh('project')]);
    }

    public function storePermit(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'permit_type' => ['nullable', Rule::in(['hot_work', 'confined_space', 'excavation', 'lifting', 'electrical', 'work_at_height'])],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'hazards' => ['nullable', 'string', 'max:4000'],
            'controls' => ['nullable', 'string', 'max:4000'],
        ]);

        [$project, $branchId] = $this->projectAndBranch($request, $data);

        $permit = WorkPermit::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'project_id' => $project?->id,
            'permit_number' => $this->nextNumber('PTW', WorkPermit::class, 'permit_number', $companyId),
            'permit_type' => $data['permit_type'] ?? 'hot_work',
            'status' => 'submitted',
            'requested_by' => $this->user($request)->id,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'location' => $data['location'] ?? null,
            'hazards' => $data['hazards'] ?? null,
            'controls' => $data['controls'] ?? null,
        ]);

        return response()->json(['permit' => $permit->load('project')], 201);
    }

    public function transitionPermit(Request $request, WorkPermit $permit): JsonResponse
    {
        $this->assertTenant($request, $permit);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'active', 'closed', 'rejected'])],
        ]);

        $allowed = [
            'submitted' => ['approved', 'rejected'],
            'approved' => ['active', 'closed'],
            'active' => ['closed'],
            'closed' => [],
            'rejected' => [],
        ];

        abort_if(! in_array($data['status'], $allowed[$permit->status] ?? [], true), 422, 'Invalid permit transition.');

        $permit->update([
            'status' => $data['status'],
            'approved_by' => $data['status'] === 'approved' ? $this->user($request)->id : $permit->approved_by,
            'closed_at' => $data['status'] === 'closed' ? now() : null,
        ]);

        return response()->json(['permit' => $permit->fresh('project')]);
    }

    private function projectAndBranch(Request $request, array $data): array
    {
        $companyId = $this->companyId($request);
        $project = null;
        $branchId = $data['branch_id'] ?? $this->user($request)->branch_id;

        if (! empty($data['project_id'])) {
            $project = Project::query()->forCompany($companyId)->whereKey($data['project_id'])->firstOrFail();
            $branchId = $project->branch_id;
        }

        Branch::query()->forCompany($companyId)->whereKey($branchId)->firstOrFail();

        return [$project, $branchId];
    }

    private function assertTenant(Request $request, object $model): void
    {
        abort_if((int) $model->company_id !== $this->companyId($request), 404);
    }
}
