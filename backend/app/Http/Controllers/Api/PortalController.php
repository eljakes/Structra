<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\ConsultantSubmittal;
use App\Models\Document;
use App\Models\Drawing;
use App\Models\PortalAccess;
use App\Models\PortalUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortalController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        return response()->json([
            'portal_users' => PortalUser::query()->forCompany($companyId)->with(['client:id,name', 'accesses.project:id,code,name'])->orderBy('name')->get(),
            'accesses' => PortalAccess::query()->forCompany($companyId)->with(['portalUser:id,name,email,user_type', 'project:id,code,name'])->latest()->get(),
            'client_approvals' => ClientApproval::query()->forCompany($companyId)->with(['portalUser:id,name,email', 'project:id,code,name', 'drawing:id,drawing_number,title', 'document:id,document_number,title'])->latest()->limit(100)->get(),
            'consultant_submittals' => ConsultantSubmittal::query()->forCompany($companyId)->with(['portalUser:id,name,email', 'project:id,code,name', 'drawing:id,drawing_number,title', 'document:id,document_number,title'])->latest()->limit(100)->get(),
            'summary' => [
                'active_users' => PortalUser::query()->forCompany($companyId)->where('status', 'active')->count(),
                'pending_client_approvals' => ClientApproval::query()->forCompany($companyId)->where('status', 'submitted')->count(),
                'consultant_reviews' => ConsultantSubmittal::query()->forCompany($companyId)->whereIn('status', ['submitted', 'in_review'])->count(),
                'project_accesses' => PortalAccess::query()->forCompany($companyId)->count(),
            ],
        ]);
    }

    public function storePortalUser(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'user_type' => ['required', Rule::in(['client', 'consultant'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('portal_users')->where('company_id', $companyId)],
            'phone' => ['nullable', 'string', 'max:60'],
            'organization' => ['nullable', 'string', 'max:255'],
        ]);

        $client = null;
        if (! empty($data['client_id'])) {
            $client = Client::query()->forCompany($companyId)->whereKey($data['client_id'])->firstOrFail();
        }

        $portalUser = PortalUser::query()->create([
            'company_id' => $companyId,
            'client_id' => $client?->id,
            'user_type' => $data['user_type'],
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'organization' => $data['organization'] ?? $client?->name,
            'status' => 'active',
            'invited_by' => $this->user($request)->id,
        ]);

        return response()->json(['portal_user' => $portalUser->load('client')], 201);
    }

    public function grantAccess(Request $request, PortalUser $portalUser): JsonResponse
    {
        $this->assertTenant($request, $portalUser);

        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'access_level' => ['nullable', Rule::in(['view', 'comment', 'approve'])],
            'disciplines' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $project = $this->projectForTenant($request, $data['project_id']);

        $access = PortalAccess::query()->updateOrCreate(
            [
                'portal_user_id' => $portalUser->id,
                'project_id' => $project->id,
            ],
            [
                'company_id' => $portalUser->company_id,
                'access_level' => $data['access_level'] ?? 'view',
                'disciplines' => $data['disciplines'] ?? [],
                'expires_at' => $data['expires_at'] ?? null,
                'granted_by' => $this->user($request)->id,
            ],
        );

        return response()->json(['access' => $access->load(['portalUser', 'project'])], 201);
    }

    public function storeClientApproval(Request $request, int $project): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        $data = $request->validate([
            'portal_user_id' => ['nullable', 'integer'],
            'drawing_id' => ['nullable', 'integer'],
            'document_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (! empty($data['portal_user_id'])) {
            PortalUser::query()->forCompany($projectModel->company_id)->whereKey($data['portal_user_id'])->firstOrFail();
        }

        if (! empty($data['drawing_id'])) {
            Drawing::query()->forCompany($projectModel->company_id)->whereKey($data['drawing_id'])->firstOrFail();
        }

        if (! empty($data['document_id'])) {
            Document::query()->forCompany($projectModel->company_id)->whereKey($data['document_id'])->firstOrFail();
        }

        $approval = ClientApproval::query()->create([
            'company_id' => $projectModel->company_id,
            'portal_user_id' => $data['portal_user_id'] ?? null,
            'project_id' => $projectModel->id,
            'drawing_id' => $data['drawing_id'] ?? null,
            'document_id' => $data['document_id'] ?? null,
            'approval_number' => $this->nextNumber('CAP', ClientApproval::class, 'approval_number', $projectModel->company_id),
            'title' => $data['title'],
            'status' => 'submitted',
            'due_date' => $data['due_date'] ?? null,
            'submitted_at' => now(),
            'created_by' => $this->user($request)->id,
        ]);

        return response()->json(['client_approval' => $approval->load(['portalUser', 'project', 'drawing', 'document'])], 201);
    }

    public function reviewClientApproval(Request $request, ClientApproval $approval): JsonResponse
    {
        $this->assertTenant($request, $approval);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'changes_required'])],
            'decision_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        abort_if(! in_array($approval->status, ['submitted', 'changes_required'], true), 422, 'Approval is not awaiting review.');

        $approval->update([
            'status' => $data['status'],
            'decision_notes' => $data['decision_notes'] ?? null,
            'reviewed_at' => now(),
        ]);

        return response()->json(['client_approval' => $approval->fresh(['portalUser', 'project', 'drawing', 'document'])]);
    }

    public function storeConsultantSubmittal(Request $request, int $project): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        $data = $request->validate([
            'portal_user_id' => ['nullable', 'integer'],
            'drawing_id' => ['nullable', 'integer'],
            'document_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'discipline' => ['nullable', Rule::in(['architectural', 'structural', 'mep', 'civil', 'landscape', 'interiors', 'other'])],
            'due_date' => ['nullable', 'date'],
            'comments' => ['nullable', 'string', 'max:4000'],
        ]);

        if (! empty($data['portal_user_id'])) {
            PortalUser::query()->forCompany($projectModel->company_id)->whereKey($data['portal_user_id'])->firstOrFail();
        }

        if (! empty($data['drawing_id'])) {
            Drawing::query()->forCompany($projectModel->company_id)->whereKey($data['drawing_id'])->firstOrFail();
        }

        if (! empty($data['document_id'])) {
            Document::query()->forCompany($projectModel->company_id)->whereKey($data['document_id'])->firstOrFail();
        }

        $submittal = ConsultantSubmittal::query()->create([
            'company_id' => $projectModel->company_id,
            'portal_user_id' => $data['portal_user_id'] ?? null,
            'project_id' => $projectModel->id,
            'drawing_id' => $data['drawing_id'] ?? null,
            'document_id' => $data['document_id'] ?? null,
            'submittal_number' => $this->nextNumber('SUB', ConsultantSubmittal::class, 'submittal_number', $projectModel->company_id),
            'title' => $data['title'],
            'discipline' => $data['discipline'] ?? 'architectural',
            'status' => 'submitted',
            'due_date' => $data['due_date'] ?? null,
            'submitted_at' => now(),
            'comments' => $data['comments'] ?? null,
            'created_by' => $this->user($request)->id,
        ]);

        return response()->json(['consultant_submittal' => $submittal->load(['portalUser', 'project', 'drawing', 'document'])], 201);
    }

    public function reviewConsultantSubmittal(Request $request, ConsultantSubmittal $submittal): JsonResponse
    {
        $this->assertTenant($request, $submittal);

        $data = $request->validate([
            'status' => ['required', Rule::in(['in_review', 'approved', 'revise_and_resubmit', 'rejected'])],
            'comments' => ['nullable', 'string', 'max:4000'],
        ]);

        abort_if(! in_array($submittal->status, ['submitted', 'in_review', 'revise_and_resubmit'], true), 422, 'Submittal is not awaiting review.');

        $submittal->update([
            'status' => $data['status'],
            'comments' => $data['comments'] ?? $submittal->comments,
            'reviewed_by' => $this->user($request)->id,
            'reviewed_at' => $data['status'] === 'in_review' ? null : now(),
        ]);

        return response()->json(['consultant_submittal' => $submittal->fresh(['portalUser', 'project', 'drawing', 'document'])]);
    }

    private function assertTenant(Request $request, object $model): void
    {
        abort_if((int) $model->company_id !== $this->companyId($request), 404);
    }
}
